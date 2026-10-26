<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Services\Linter;

use Dskripchenko\LaravelApi\Facades\ApiModule;
use Dskripchenko\LaravelApi\Operations\ControllerDefinition;
use Dskripchenko\LaravelApi\Operations\DescriptorBuilder;
use Dskripchenko\LaravelApi\Operations\Doc\DocLine;
use Dskripchenko\LaravelApi\Operations\Doc\OperationDoc;
use Dskripchenko\LaravelApi\Operations\Doc\OperationDocParser;
use Dskripchenko\LaravelApi\Operations\OperationRegistry;
use Dskripchenko\LaravelApi\Services\OpenApi\DocPatterns;
use Illuminate\Support\Arr;

/**
 * Checks the route map and the docblock markup for the mistakes that otherwise
 * say nothing.
 *
 * This package fails quietly by design, and that is the reason the linter
 * exists. An action whose method was renamed answers 404 — the same 404 as a
 * wrong URL. A type nobody recognises becomes `string`. A template that is not
 * defined becomes a dangling `$ref` in a spec that still validates. In every
 * one of those cases the application starts, the tests pass and the mistake
 * surfaces as someone else's bug report.
 *
 * Nothing here parses the markup a second time: the shapes come from
 * {@see DocPatterns}, the same ones the generator uses. A linter that agreed
 * with itself but not with the generator would be worse than none.
 */
final class OpenApiLinter
{
    /** @var LintIssue[] */
    private array $issues = [];

    /**
     * Whether to report public controller methods that no action points at.
     *
     * Off by default, and deliberately so: a controller may hold helpers that
     * were never meant to be endpoints, and a linter that cries about them is
     * a linter people stop reading. The command says out loud when the check
     * was skipped — a silently narrowed scope reads as "everything is fine".
     */
    private bool $checkUnroutedMethods = false;

    public function withUnroutedMethods(bool $enabled = true): self
    {
        $this->checkUnroutedMethods = $enabled;

        return $this;
    }

    /**
     * Lints every registered API version, or only the one named.
     *
     * @return LintIssue[]
     */
    public function lint(?string $onlyVersion = null): array
    {
        return $this->lintVersionList(ApiModule::getApiVersionList(), $onlyVersion);
    }

    /**
     * Lints an explicit version → Api class map.
     *
     * The workhorse, and public on purpose: it lets a test — or a host with
     * more than one module — check a set of classes without going through the
     * registered module.
     *
     * @param  array<string, class-string>  $versions
     * @return LintIssue[]
     */
    public function lintVersionList(array $versions, ?string $onlyVersion = null): array
    {
        $this->issues = [];

        foreach ($versions as $version => $apiClass) {
            if ($onlyVersion !== null && $onlyVersion !== (string) $version) {
                continue;
            }

            $this->lintVersion((string) $version, $apiClass);
        }

        return $this->issues;
    }

    /**
     * @param  class-string  $apiClass
     */
    private function lintVersion(string $version, string $apiClass): void
    {
        if (! class_exists($apiClass)) {
            $this->issues[] = LintIssue::error(
                'api.missing-class',
                $version,
                "The version is mapped to {$apiClass}, and there is no such class.",
                'Check getApiVersionList() in the module.'
            );

            return;
        }

        $templates = $this->templateNames($apiClass);
        $securitySchemes = array_keys((array) $apiClass::getSecuritySchemes());

        $this->lintTemplates($version, $apiClass, $templates);

        foreach (OperationRegistry::for($apiClass, $version)->controllers() as $definition) {
            $this->lintController(
                $version,
                $apiClass,
                $definition,
                $templates,
                $securitySchemes
            );
        }
    }

    /**
     * @param  class-string  $apiClass
     * @param  string[]  $templates
     * @param  string[]  $securitySchemes
     */
    private function lintController(
        string $version,
        string $apiClass,
        ControllerDefinition $definition,
        array $templates,
        array $securitySchemes
    ): void {
        $where = "{$version} · {$definition->key}";
        $controllerClass = $definition->class;

        if (! is_string($controllerClass) || $controllerClass === '') {
            $this->issues[] = LintIssue::error(
                'controller.missing-key',
                $where,
                'The controller entry has no `controller` key.',
                "Add 'controller' => SomeController::class."
            );

            return;
        }

        if (! class_exists($controllerClass)) {
            $this->issues[] = LintIssue::error(
                'controller.missing-class',
                $where,
                "The map points at {$controllerClass}, and there is no such class."
            );

            return;
        }

        $reflection = new \ReflectionClass($controllerClass);
        $routedMethods = [];

        $this->lintMiddlewareList($where, 'controller', (array) Arr::get($definition->options, 'middleware', []));

        foreach ($definition->operations as $operation) {
            $routedMethods[] = $operation->controllerMethod;

            $this->lintAction(
                $version,
                $apiClass,
                $operation->controllerKey,
                $operation->actionKey,
                $operation->controllerMethod,
                $operation->options,
                $reflection,
                $templates,
                $securitySchemes
            );
        }

        if ($this->checkUnroutedMethods) {
            $this->lintUnroutedMethods($where, $reflection, $routedMethods);
        }
    }

    /**
     * @param  class-string  $apiClass
     * @param  array<string, mixed>  $actionOptions
     * @param  \ReflectionClass<object>  $reflection
     * @param  string[]  $templates
     * @param  string[]  $securitySchemes
     */
    private function lintAction(
        string $version,
        string $apiClass,
        string $controllerKey,
        string $actionKey,
        string $methodName,
        array $actionOptions,
        \ReflectionClass $reflection,
        array $templates,
        array $securitySchemes
    ): void {
        $where = "{$version} · {$controllerKey}.{$actionKey}";

        if (array_key_exists('action', $actionOptions) && ! is_string($actionOptions['action'])) {
            $this->issues[] = LintIssue::error(
                'action.invalid-method-name',
                $where,
                'The `action` key has to name a method, and it is ' . get_debug_type($actionOptions['action']) . '.',
                "The action falls back to {$reflection->getName()}::{$actionKey}(). Name the method, or drop the key."
            );
        }

        if (! $reflection->hasMethod($methodName)) {
            $this->issues[] = LintIssue::error(
                'action.missing-method',
                $where,
                "The action points at {$reflection->getName()}::{$methodName}(), and there is no such method.",
                'At runtime this answers 404 — the same 404 as a wrong URL, which is why it goes unnoticed.'
            );

            return;
        }

        $method = $reflection->getMethod($methodName);

        if (! $method->isPublic() || $method->isStatic() || $method->isAbstract()) {
            $this->issues[] = LintIssue::error(
                'action.unreachable-method',
                $where,
                "{$reflection->getName()}::{$methodName}() cannot serve an action: it has to be a public non-static method.",
            );

            return;
        }

        $this->lintHttpMethods($where, $actionOptions);
        $this->lintMiddlewareList($where, 'action', (array) Arr::get($actionOptions, 'middleware', []));
        $this->lintMiddlewareList($where, 'exclude-middleware', (array) Arr::get($actionOptions, 'exclude-middleware', []));

        $this->lintActionSecurity($where, (array) Arr::get($actionOptions, 'security', []), $securitySchemes);

        $middlewareList = $apiClass::getMiddlewareByControllerAndActionKey($controllerKey, $actionKey);
        $doc = OperationDocParser::parse($method->getDocComment(), $middlewareList);

        $this->lintTags($where, $doc, $reflection, $templates, $securitySchemes);
        $this->lintUndeclaredInput($where, $doc, $method);
        $this->lintDescriptors($where, $version, $apiClass, $controllerKey, $actionKey, $doc, $reflection);
    }

    /**
     * What a `[method]` returns is known only once it is called — which is
     * what the spec does, so the linter does it too, and reports what the spec
     * would quietly leave out, or what makes it fail.
     *
     * @param  class-string  $apiClass
     * @param  \ReflectionClass<object>  $reflection
     */
    private function lintDescriptors(
        string $where,
        string $version,
        string $apiClass,
        string $controllerKey,
        string $actionKey,
        OperationDoc $doc,
        \ReflectionClass $reflection
    ): void {
        foreach (array_merge(self::ownLines($doc, 'input'), self::ownLines($doc, 'output')) as $line) {
            // Reported already, as tag.callable-missing; calling it would only fail again.
            if ($line->kind() === DocLine::CALLABLE && ! $reflection->hasMethod($line->parts()['callable'])) {
                return;
            }
        }

        $operation = OperationRegistry::for($apiClass, $version)->find($controllerKey, $actionKey);
        if ($operation === null) {
            return;
        }

        try {
            $descriptors = DescriptorBuilder::for($apiClass)->build($operation);
        } catch (\Throwable $e) {
            $this->issues[] = LintIssue::error(
                'operation.describe-failed',
                $where,
                'Describing the action fails — /api/doc and api:cache fail the same way: '
                    . get_class($e) . ': ' . $e->getMessage(),
                'Usually a [method] that throws, or one whose parameters the container cannot resolve.'
            );

            return;
        }

        // One descriptor per HTTP method: what the [method] got wrong for
        // every one of them is said once.
        $seen = [];
        foreach ($descriptors as $descriptor) {
            foreach ($descriptor->notes as $note) {
                $key = "{$note->rule}\0{$note->message}";
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $this->issues[] = LintIssue::error($note->rule, $where, $note->message);
            }
        }
    }

    /**
     * An action that validates its input and declares none of it.
     *
     * The rest of the linter checks the markup that is there; this is the one
     * check for markup that is missing. "No @input at all" on its own would be
     * noise — plenty of actions take nothing — so it is narrowed to the
     * methods that visibly validate something: a `validate()` call, a
     * validator built in the body, or a FormRequest parameter. Whatever the
     * rules are, literal or assembled at runtime, they mean the endpoint has
     * fields, and the spec describes none of them.
     *
     * Only the method's own @input tags count: the ones middleware contribute
     * describe the middleware's input, not the fields this method checks.
     */
    private function lintUndeclaredInput(string $where, OperationDoc $doc, \ReflectionMethod $method): void
    {
        if (self::ownLines($doc, 'input') !== []) {
            return;
        }

        $evidence = $this->validationEvidence($method);
        if ($evidence === null) {
            return;
        }

        $this->issues[] = LintIssue::warning(
            'input.undeclared',
            $where,
            "{$method->getDeclaringClass()->getName()}::{$method->getName()}() validates its input ({$evidence}) and declares no @input.",
            'Describe the fields with @input tags — or with @input [method] when they are only known at runtime.'
        );
    }

    /**
     * What gives away that a method validates its input, or null.
     */
    private function validationEvidence(\ReflectionMethod $method): ?string
    {
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType
                && ! $type->isBuiltin()
                && is_subclass_of($type->getName(), 'Illuminate\\Foundation\\Http\\FormRequest')
            ) {
                return 'a FormRequest parameter';
            }
        }

        $source = $this->methodBodyWithoutComments($method);
        if ($source === null) {
            return null;
        }

        $patterns = [
            '/\bValidator\s*::\s*make\s*\(/' => 'Validator::make()',
            '/(?<![\w$>:])validator\s*\(/' => 'validator()',
            '/->\s*validate(?:WithBag)?\s*\(/i' => 'validate()',
        ];
        foreach ($patterns as $pattern => $label) {
            if (preg_match($pattern, $source) === 1) {
                return $label;
            }
        }

        return null;
    }

    private function methodBodyWithoutComments(\ReflectionMethod $method): ?string
    {
        $file = $method->getFileName();
        $start = $method->getStartLine();
        $end = $method->getEndLine();
        if ($file === false || $start === false || $end === false || ! is_readable($file)) {
            return null;
        }

        $lines = file($file);
        if ($lines === false) {
            return null;
        }

        $code = implode('', array_slice($lines, $start - 1, $end - $start + 1));
        $tokens = token_get_all('<?php ' . $code);
        $result = '';
        foreach ($tokens as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }
                $result .= $token[1];
            } else {
                $result .= $token;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $actionOptions
     */
    private function lintHttpMethods(string $where, array $actionOptions): void
    {
        $declared = Arr::get($actionOptions, 'method');

        if ($declared === null) {
            return;
        }

        $available = array_map(
            'strtolower',
            (array) config('laravel-api.available_methods', ['get', 'post', 'put', 'patch', 'delete'])
        );

        foreach ((array) $declared as $httpMethod) {
            if (! is_string($httpMethod) || ! in_array(strtolower($httpMethod), $available, true)) {
                $value = is_string($httpMethod) ? $httpMethod : gettype($httpMethod);
                $this->issues[] = LintIssue::error(
                    'action.unknown-http-method',
                    $where,
                    "`{$value}` is not among the HTTP methods this application routes.",
                    'Available: ' . implode(', ', $available) . ' (laravel-api.available_methods).'
                );
            }
        }
    }

    /**
     * @param  array<int, mixed>  $middleware
     */
    private function lintMiddlewareList(string $where, string $level, array $middleware): void
    {
        foreach ($middleware as $entry) {
            if (! is_string($entry) || $entry === '') {
                continue;
            }

            // A bare name is a middleware group or an alias — the router owns
            // those, and a linter cannot tell a typo from a group it has never
            // heard of without booting the whole application.
            if (! str_contains($entry, '\\')) {
                continue;
            }

            // `Middleware:argument` is Laravel's parameter syntax, and the
            // class is only the part before the colon. Checking the whole
            // string turned every parameterised middleware in a real
            // application into a false positive.
            $entry = strtok($entry, ':') ?: $entry;

            if (! class_exists($entry)) {
                $this->issues[] = LintIssue::error(
                    'middleware.missing-class',
                    $where,
                    "The {$level} middleware {$entry} does not exist.",
                );
            }
        }
    }

    /**
     * @param  array<int, mixed>  $security
     * @param  string[]  $securitySchemes
     */
    private function lintActionSecurity(string $where, array $security, array $securitySchemes): void
    {
        foreach ($security as $entry) {
            $names = is_array($entry) ? array_keys($entry) : [$entry];

            foreach ($names as $name) {
                if (! is_string($name) || in_array($name, $securitySchemes, true)) {
                    continue;
                }

                $this->issues[] = LintIssue::error(
                    'security.unknown-scheme',
                    $where,
                    "The action asks for the security scheme `{$name}`, which is not defined.",
                    'Add it to getOpenApiSecurityDefinitions() on the Api class.'
                );
            }
        }
    }

    /**
     * @param  \ReflectionClass<object>  $reflection
     * @param  string[]  $templates
     * @param  string[]  $securitySchemes
     */
    private function lintTags(
        string $where,
        OperationDoc $doc,
        \ReflectionClass $reflection,
        array $templates,
        array $securitySchemes
    ): void {
        $inputVariables = $this->lintParameterTags($where, $doc, 'input', $reflection, $templates);
        $this->lintParameterTags($where, $doc, 'output', $reflection, $templates);
        $this->lintParameterTags($where, $doc, 'header', $reflection, $templates);

        $this->lintResponseTags($where, $doc, $templates);
        $this->lintSecurityTags($where, $doc, $securitySchemes);
        $this->lintDefaultExampleTags($where, $doc, $inputVariables);
    }

    /**
     * Walks @input / @output / @header and returns the variables it declared.
     *
     * @param  \ReflectionClass<object>  $reflection
     * @param  string[]  $templates
     * @return string[]
     */
    private function lintParameterTags(
        string $where,
        OperationDoc $doc,
        string $tagName,
        \ReflectionClass $reflection,
        array $templates
    ): array {
        $variables = [];
        $declaredTypes = [];

        // The middleware's own lines are its own business: they are linted
        // nowhere, and reported here they would repeat on every action.
        foreach (self::ownLines($doc, $tagName) as $line) {
            $body = trim($line->body);
            $parts = $line->parts();

            switch ($line->kind()) {
                case DocLine::EMPTY:
                    $this->issues[] = LintIssue::warning(
                        'tag.empty',
                        $where,
                        "An empty @{$tagName} tag.",
                    );

                    continue 2;

                // `[methodName]` — inputs or outputs assembled at runtime.
                case DocLine::CALLABLE:
                    if ($tagName !== 'input' && $tagName !== 'output') {
                        $this->issues[] = LintIssue::warning(
                            'tag.callable-misplaced',
                            $where,
                            "@{$tagName} does not support the [method] form; only @input and @output do.",
                        );

                        continue 2;
                    }

                    if (! $reflection->hasMethod($parts['callable'])) {
                        $this->issues[] = LintIssue::error(
                            'tag.callable-missing',
                            $where,
                            "@{$tagName} [{$parts['callable']}] refers to a method the controller does not have.",
                        );
                    }

                    continue 2;

                // `@Model`, `@Model[]` — a reference to a template.
                case DocLine::MODEL_REF:
                    if (! in_array($parts['model'], $templates, true)) {
                        $this->issues[] = LintIssue::error(
                            'tag.unknown-template',
                            $where,
                            "@{$tagName} refers to the template `{$parts['model']}`, which is not defined.",
                            'Add it to getOpenApiTemplates(), or the spec gets a $ref pointing nowhere.'
                        );
                    }

                    if ($line->variable() !== null) {
                        $variables[] = $line->variable();
                    }

                    continue 2;

                case DocLine::MALFORMED_MODEL_REF:
                    $this->issues[] = LintIssue::error(
                        'tag.malformed',
                        $where,
                        "@{$tagName} {$body} — the model reference does not parse.",
                        'Expected @Model, @Model[] or @Model $variable.'
                    );

                    continue 2;

                // `{Template}` — only @output carries this form.
                case DocLine::TEMPLATE:
                    if ($tagName !== 'output') {
                        $this->issues[] = LintIssue::warning(
                            'tag.template-misplaced',
                            $where,
                            "@{$tagName} does not support the {Template} form; use @response or @output.",
                        );

                        continue 2;
                    }

                    if (isset($parts['template']) && ! in_array($parts['template'], $templates, true)) {
                        $this->issues[] = LintIssue::error(
                            'tag.unknown-template',
                            $where,
                            "@output {{$parts['template']}} refers to a template that is not defined.",
                        );
                    }

                    continue 2;

                case DocLine::MALFORMED:
                    $this->issues[] = LintIssue::error(
                        'tag.malformed',
                        $where,
                        "@{$tagName} {$body} — does not parse, and the generator drops it silently.",
                        'Expected: type ?$name Description.'
                    );

                    continue 2;
            }

            $type = $parts['type'];
            $variable = $parts['variable'];

            if ($type !== '' && ! in_array($type, DocPatterns::availableDataTypes(), true)) {
                $this->issues[] = LintIssue::warning(
                    'tag.unknown-type',
                    $where,
                    "@{$tagName} \${$variable}: the type `{$type}` is unknown and becomes `string`.",
                    'Known types: ' . implode(', ', DocPatterns::availableDataTypes()) . '.'
                );
            }

            if (in_array($variable, $variables, true)) {
                $this->issues[] = LintIssue::warning(
                    'tag.duplicate-variable',
                    $where,
                    "@{$tagName} \${$variable} is declared twice; the last one wins.",
                );
            }

            $variables[] = $variable;
            $declaredTypes[$variable] = $type;
        }

        $this->lintNesting($where, $tagName, $variables, $declaredTypes);

        return $variables;
    }

    /**
     * Dot- and bracket-notation needs its parent declared: `$address.city`
     * without `$address` produces a property hanging off nothing.
     *
     * @param  string[]  $variables
     * @param  array<string, string>  $declaredTypes
     */
    private function lintNesting(string $where, string $tagName, array $variables, array $declaredTypes): void
    {
        foreach ($variables as $variable) {
            if (! str_contains($variable, '.')) {
                continue;
            }

            $parent = substr($variable, 0, (int) strrpos($variable, '.'));
            $parentName = str_replace('[]', '', $parent);
            $isArrayItem = str_ends_with($parent, '[]');

            if (! isset($declaredTypes[$parentName]) && ! in_array($parent, $variables, true)) {
                $this->issues[] = LintIssue::warning(
                    'tag.orphan-nesting',
                    $where,
                    "@{$tagName} \${$variable} is nested under \${$parentName}, which is never declared.",
                    'Declare the parent: @' . $tagName . ' ' . ($isArrayItem ? 'array' : 'object') . " \${$parentName} …"
                );

                continue;
            }

            $parentType = $declaredTypes[$parentName] ?? null;
            $expected = $isArrayItem ? 'array' : 'object';

            if ($parentType !== null && $parentType !== '' && $parentType !== $expected) {
                $this->issues[] = LintIssue::warning(
                    'tag.nesting-type-mismatch',
                    $where,
                    "@{$tagName} \${$variable} needs \${$parentName} to be `{$expected}`, and it is declared `{$parentType}`.",
                );
            }
        }
    }

    /**
     * @param  string[]  $templates
     */
    private function lintResponseTags(string $where, OperationDoc $doc, array $templates): void
    {
        $seenCodes = [];

        foreach ($doc->responses() as $line) {
            $body = trim($line->body);

            if (! preg_match(DocPatterns::response(), $body, $parsed)) {
                $this->issues[] = LintIssue::error(
                    'response.malformed',
                    $where,
                    "@response {$body} — does not parse.",
                    'Expected: @response 200 {Template} or @response 404 Description.'
                );

                continue;
            }

            $code = (int) $parsed['code'];

            if ($code < 100 || $code > 599) {
                $this->issues[] = LintIssue::error(
                    'response.impossible-code',
                    $where,
                    "@response {$code} — not an HTTP status code.",
                );
            }

            if (in_array($code, $seenCodes, true)) {
                $this->issues[] = LintIssue::warning(
                    'response.duplicate-code',
                    $where,
                    "@response {$code} is declared twice; the last one wins.",
                );
            }

            $seenCodes[] = $code;

            $template = trim((string) ($parsed['template'] ?? ''), '{}');

            if ($template !== '' && ! in_array($template, $templates, true)) {
                $this->issues[] = LintIssue::error(
                    'response.unknown-template',
                    $where,
                    "@response {$code} {{$template}} refers to a template that is not defined.",
                    'Add it to getOpenApiTemplates() on the Api class.'
                );
            }
        }
    }

    /**
     * @param  string[]  $securitySchemes
     */
    private function lintSecurityTags(string $where, OperationDoc $doc, array $securitySchemes): void
    {
        foreach ($doc->lines('security') as $line) {
            $name = trim($line->body);

            if ($name === '' || in_array($name, $securitySchemes, true)) {
                continue;
            }

            $this->issues[] = LintIssue::error(
                'security.unknown-scheme',
                $where,
                "@security {$name} — no such scheme is defined.",
                'Add it to getOpenApiSecurityDefinitions() on the Api class.'
            );
        }
    }

    /**
     * @param  string[]  $inputVariables
     */
    private function lintDefaultExampleTags(string $where, OperationDoc $doc, array $inputVariables): void
    {
        // A middleware may contribute inputs of its own, and @default for one
        // of those is legitimate. Collect them before accusing anyone.
        $known = $inputVariables;
        foreach ($doc->inputs() as $line) {
            if (! $line->isOwn() && $line->variable() !== null) {
                $known[] = $line->variable();
            }
        }

        foreach (['default', 'example'] as $tagName) {
            foreach ($doc->lines($tagName) as $line) {
                $body = trim($line->body);

                if (! preg_match(DocPatterns::defaultExample(), $body, $parsed)) {
                    $this->issues[] = LintIssue::error(
                        "{$tagName}.malformed",
                        $where,
                        "@{$tagName} {$body} — does not parse.",
                        "Expected: @{$tagName} \$variable value."
                    );

                    continue;
                }

                if (! in_array($parsed['variable'], $known, true)) {
                    $this->issues[] = LintIssue::warning(
                        "{$tagName}.unknown-variable",
                        $where,
                        "@{$tagName} \${$parsed['variable']} — there is no @input for that variable, so the value is ignored.",
                    );
                }
            }
        }
    }

    /**
     * The lines the method declares itself, without its middleware's.
     *
     * @return DocLine[]
     */
    private static function ownLines(OperationDoc $doc, string $tag): array
    {
        return array_values(array_filter($doc->lines($tag), static fn (DocLine $line): bool => $line->isOwn()));
    }

    /**
     * @param  \ReflectionClass<object>  $reflection
     * @param  string[]  $routedMethods
     */
    private function lintUnroutedMethods(string $where, \ReflectionClass $reflection, array $routedMethods): void
    {
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || str_starts_with($method->getName(), '__')) {
                continue;
            }

            // Whatever the framework and this package put on every controller
            // — `callAction`, `middleware`, `success` — is not an endpoint
            // anyone forgot to route. Only what the application itself declared
            // can be a forgotten endpoint.
            if ($this->isInheritedFromVendor($method)) {
                continue;
            }

            if (in_array($method->getName(), $routedMethods, true)) {
                continue;
            }

            $this->issues[] = LintIssue::warning(
                'controller.unrouted-method',
                $where,
                "{$reflection->getShortName()}::{$method->getName()}() is public and no action points at it.",
                'Either route it or make it non-public — a public method that is not an endpoint reads like one.'
            );
        }
    }

    /**
     * Whether the method came from a base class the application does not own.
     */
    private function isInheritedFromVendor(\ReflectionMethod $method): bool
    {
        $declaring = $method->getDeclaringClass()->getName();

        foreach (['Illuminate\\', 'Symfony\\', 'Dskripchenko\\LaravelApi\\'] as $prefix) {
            if (str_starts_with($declaring, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Templates may refer to one another through `@Other`; a dangling one ends
     * up as a `$ref` into nothing.
     *
     * @param  class-string  $apiClass
     * @param  string[]  $templates
     */
    private function lintTemplates(string $version, string $apiClass, array $templates): void
    {
        $envelopeFields = $this->envelopeFieldNames($apiClass);

        foreach ((array) $apiClass::getSchemaTemplates() as $name => $properties) {
            $where = "{$version} · template {$name}";

            // With the envelope on, a template is a payload. One that carries
            // the envelope's own fields was written before the option existed,
            // and the spec now shows an envelope inside an envelope.
            $fieldNames = array_map(
                static fn ($field): string => rtrim((string) $field, '!'),
                array_keys((array) $properties)
            );

            if ($envelopeFields !== [] && array_diff($envelopeFields, $fieldNames) === []) {
                $list = implode(', ', array_map(static fn (string $f): string => "`{$f}`", $envelopeFields));

                $this->issues[] = LintIssue::warning(
                    'template.envelope-duplicated',
                    $where,
                    "The template declares the envelope's fields ({$list}) itself; with \$responseEnvelope on it is wrapped again.",
                    'Describe the payload alone and let $responseEnvelope add the envelope.'
                );
            }

            foreach ((array) $properties as $field => $definition) {
                $refs = [];

                if (is_string($definition) && str_starts_with(trim($definition), '@')) {
                    $refs[] = trim($definition);
                }

                if (is_array($definition)) {
                    array_walk_recursive($definition, static function ($item) use (&$refs): void {
                        if (is_string($item) && str_starts_with(trim($item), '@')) {
                            $refs[] = trim($item);
                        }
                    });
                }

                foreach ($refs as $ref) {
                    // The shorthand allows a description after the reference —
                    // `'@Item[] What to send'` — so the name is the first token
                    // and the rest is prose. Taking the whole string reported
                    // the description as a missing template.
                    $ref = (string) preg_split('/\s+/', trim($ref))[0];
                    $referenced = rtrim(ltrim($ref, '@'), '[]!');

                    if (! in_array($referenced, $templates, true)) {
                        $this->issues[] = LintIssue::error(
                            'template.unknown-ref',
                            $where,
                            "The field `{$field}` refers to the template `{$referenced}`, which is not defined.",
                        );
                    }
                }
            }
        }
    }

    /**
     * The fields of the Api class's response envelope, without the required
     * marks; empty when there is no envelope.
     *
     * @param  class-string  $apiClass
     * @return string[]
     */
    private function envelopeFieldNames(string $apiClass): array
    {
        if (! property_exists($apiClass, 'responseEnvelope')) {
            return [];
        }

        $envelope = $apiClass::$responseEnvelope;

        if ($envelope === true) {
            return ['success', 'payload'];
        }

        if (! is_array($envelope)) {
            return [];
        }

        return array_map(static fn ($field): string => rtrim((string) $field, '!'), array_keys($envelope));
    }

    /**
     * The names a @response or a @Model may legitimately point at: whatever
     * the Api class declares, plus the two the package always provides.
     *
     * @param  class-string  $apiClass
     * @return string[]
     */
    private function templateNames(string $apiClass): array
    {
        return array_values(array_unique(array_merge(
            ['Error', 'Success'],
            array_map('strval', array_keys((array) $apiClass::getSchemaTemplates()))
        )));
    }

}
