<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Services\Linter;

use Dskripchenko\LaravelApi\Facades\ApiModule;
use Dskripchenko\LaravelApi\Services\OpenApi\DocPatterns;
use Illuminate\Support\Arr;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlockFactory;

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

    private ?DocBlockFactory $docBlockFactory = null;

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

        $methods = $apiClass::getPreparedMethods();
        $templates = $this->templateNames($apiClass);
        $securitySchemes = array_keys((array) $apiClass::getOpenApiSecurityDefinitions());

        $this->lintTemplates($version, $apiClass, $templates);

        foreach (Arr::get($methods, 'controllers', []) as $controllerKey => $options) {
            $this->lintController(
                $version,
                $apiClass,
                (string) $controllerKey,
                (array) $options,
                $templates,
                $securitySchemes
            );
        }
    }

    /**
     * @param  class-string  $apiClass
     * @param  array<string, mixed>  $options
     * @param  string[]  $templates
     * @param  string[]  $securitySchemes
     */
    private function lintController(
        string $version,
        string $apiClass,
        string $controllerKey,
        array $options,
        array $templates,
        array $securitySchemes
    ): void {
        $where = "{$version} · {$controllerKey}";
        $controllerClass = Arr::get($options, 'controller');

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

        $this->lintMiddlewareList($where, 'controller', (array) Arr::get($options, 'middleware', []));

        foreach ((array) Arr::get($options, 'actions', []) as $key => $value) {
            // `false` disables an action inherited from an earlier version —
            // it is an instruction, not an endpoint.
            if ($value === false) {
                continue;
            }

            $actionKey = is_numeric($key) ? $value : $key;
            if (! is_string($actionKey)) {
                continue;
            }

            $methodName = $this->resolveMethodName($actionKey, $value);
            $routedMethods[] = $methodName;

            $this->lintAction(
                $version,
                $apiClass,
                $controllerKey,
                $actionKey,
                $methodName,
                is_array($value) ? $value : [],
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
     * The generator's own rule for turning an action entry into a method name:
     * a string value is an alias, an array may carry `action`, otherwise the
     * key is the name.
     *
     * @param  mixed  $value
     */
    private function resolveMethodName(string $actionKey, $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value) && isset($value['action']) && is_string($value['action'])) {
            return $value['action'];
        }

        return $actionKey;
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
        $docBlock = $this->docBlock($method->getDocComment());

        $this->lintTags($where, $docBlock, $reflection, $templates, $securitySchemes, $middlewareList, $apiClass);
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
     * @param  array<int, string>  $middlewareList
     * @param  class-string  $apiClass
     */
    private function lintTags(
        string $where,
        DocBlock $docBlock,
        \ReflectionClass $reflection,
        array $templates,
        array $securitySchemes,
        array $middlewareList,
        string $apiClass
    ): void {
        $inputVariables = $this->lintParameterTags($where, $docBlock, 'input', $reflection, $templates);
        $this->lintParameterTags($where, $docBlock, 'output', $reflection, $templates);
        $this->lintParameterTags($where, $docBlock, 'header', $reflection, $templates);

        $this->lintResponseTags($where, $docBlock, $templates);
        $this->lintSecurityTags($where, $docBlock, $securitySchemes);
        $this->lintDefaultExampleTags($where, $docBlock, $inputVariables, $middlewareList);
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
        DocBlock $docBlock,
        string $tagName,
        \ReflectionClass $reflection,
        array $templates
    ): array {
        $variables = [];
        $declaredTypes = [];

        foreach ($docBlock->getTagsByName($tagName) as $tag) {
            $body = trim((string) $tag);

            if ($body === '') {
                $this->issues[] = LintIssue::warning(
                    'tag.empty',
                    $where,
                    "An empty @{$tagName} tag.",
                );

                continue;
            }

            // `[methodName]` — inputs assembled at runtime.
            if (preg_match(DocPatterns::inputsCallable(), $body, $callable)) {
                if ($tagName !== 'input') {
                    $this->issues[] = LintIssue::warning(
                        'tag.callable-misplaced',
                        $where,
                        "@{$tagName} does not support the [method] form; only @input does.",
                    );

                    continue;
                }

                if (! $reflection->hasMethod($callable['callable'])) {
                    $this->issues[] = LintIssue::error(
                        'tag.callable-missing',
                        $where,
                        "@input [{$callable['callable']}] refers to a method the controller does not have.",
                    );
                }

                continue;
            }

            // `@Model`, `@Model[]` — a reference to a template.
            if (str_starts_with($body, '@')) {
                if (preg_match(DocPatterns::modelRef(), $body, $ref)) {
                    if (! in_array($ref['model'], $templates, true)) {
                        $this->issues[] = LintIssue::error(
                            'tag.unknown-template',
                            $where,
                            "@{$tagName} refers to the template `{$ref['model']}`, which is not defined.",
                            'Add it to getOpenApiTemplates(), or the spec gets a $ref pointing nowhere.'
                        );
                    }

                    if (($ref['variable'] ?? '') !== '') {
                        $variables[] = $ref['variable'];
                    }

                    continue;
                }

                $this->issues[] = LintIssue::error(
                    'tag.malformed',
                    $where,
                    "@{$tagName} {$body} — the model reference does not parse.",
                    'Expected @Model, @Model[] or @Model $variable.'
                );

                continue;
            }

            // `{Template}` — only @output carries this form.
            if (str_starts_with($body, '{')) {
                if ($tagName !== 'output') {
                    $this->issues[] = LintIssue::warning(
                        'tag.template-misplaced',
                        $where,
                        "@{$tagName} does not support the {Template} form; use @response or @output.",
                    );

                    continue;
                }

                if (preg_match(DocPatterns::inputOutputTemplate(), $body, $tpl)
                    && ! in_array($tpl['template'], $templates, true)
                ) {
                    $this->issues[] = LintIssue::error(
                        'tag.unknown-template',
                        $where,
                        "@output {{$tpl['template']}} refers to a template that is not defined.",
                    );
                }

                continue;
            }

            if (! preg_match(DocPatterns::inputOutput(), $body, $parsed)) {
                $this->issues[] = LintIssue::error(
                    'tag.malformed',
                    $where,
                    "@{$tagName} {$body} — does not parse, and the generator drops it silently.",
                    'Expected: type ?$name Description.'
                );

                continue;
            }

            $type = $parsed['type'];
            $variable = $parsed['variable'];

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
    private function lintResponseTags(string $where, DocBlock $docBlock, array $templates): void
    {
        $seenCodes = [];

        foreach ($docBlock->getTagsByName('response') as $tag) {
            $body = trim((string) $tag);

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
    private function lintSecurityTags(string $where, DocBlock $docBlock, array $securitySchemes): void
    {
        foreach ($docBlock->getTagsByName('security') as $tag) {
            $name = trim((string) $tag);

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
     * @param  array<int, string>  $middlewareList
     */
    private function lintDefaultExampleTags(
        string $where,
        DocBlock $docBlock,
        array $inputVariables,
        array $middlewareList
    ): void {
        // A middleware may contribute inputs of its own, and @default for one
        // of those is legitimate. Collect them before accusing anyone.
        $known = array_merge($inputVariables, $this->middlewareInputVariables($middlewareList));

        foreach (['default', 'example'] as $tagName) {
            foreach ($docBlock->getTagsByName($tagName) as $tag) {
                $body = trim((string) $tag);

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
     * @param  array<int, string>  $middlewareList
     * @return string[]
     */
    private function middlewareInputVariables(array $middlewareList): array
    {
        $variables = [];

        foreach ($middlewareList as $middleware) {
            if (! is_string($middleware) || ! class_exists($middleware)) {
                continue;
            }

            $reflection = new \ReflectionClass($middleware);

            foreach (['run', 'handle'] as $candidate) {
                if (! $reflection->hasMethod($candidate)) {
                    continue;
                }

                $docBlock = $this->docBlock($reflection->getMethod($candidate)->getDocComment());

                foreach ($docBlock->getTagsByName('input') as $tag) {
                    if (preg_match(DocPatterns::inputOutput(), trim((string) $tag), $parsed)) {
                        $variables[] = $parsed['variable'];
                    }
                }

                break;
            }
        }

        return $variables;
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
        foreach ((array) $apiClass::getOpenApiTemplates() as $name => $properties) {
            $where = "{$version} · template {$name}";

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
                    $referenced = rtrim(ltrim($ref, '@'), '[]');

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
            array_map('strval', array_keys((array) $apiClass::getOpenApiTemplates()))
        )));
    }

    /**
     * @param  string|false|null  $comment
     */
    private function docBlock($comment): DocBlock
    {
        if ($this->docBlockFactory === null) {
            $this->docBlockFactory = DocBlockFactory::createInstance();
        }

        return $this->docBlockFactory->create($comment ?: ' ');
    }
}
