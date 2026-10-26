<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Components\BaseApi;
use Dskripchenko\LaravelApi\Operations\Doc\DocLine;
use Dskripchenko\LaravelApi\Operations\Doc\OperationDocParser;
use Dskripchenko\LaravelApi\Services\Linter\LintIssue;
use Dskripchenko\LaravelApi\Services\Linter\OpenApiLinter;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Middleware\TestAuthMiddleware;
use Tests\Fixtures\OpenApi\HeaderMiddleware;

function docComment(string ...$lines): string
{
    return "/**\n" . implode('', array_map(static fn (string $l): string => " * {$l}\n", $lines)) . " */";
}

it('reads the summary and the description, sanitized', function () {
    $doc = OperationDocParser::parse(docComment('Show a user', '', 'See {@see \\App\\Models\\User} {@inheritDoc}'));

    expect($doc->summary)->toBe('Show a user');
    expect($doc->description)->toBe('See User');
});

it('reads a missing comment as an empty docblock', function () {
    $doc = OperationDocParser::parse(false);

    expect($doc->summary)->toBe('');
    expect($doc->inputs())->toBe([]);
    expect($doc->isDeprecated())->toBeFalse();
});

it('tells the forms of a line apart', function (string $body, string $kind, array $parts) {
    $line = new DocLine('input', $body);

    expect($line->kind())->toBe($kind);
    foreach ($parts as $key => $value) {
        expect($line->parts()[$key] ?? null)->toBe($value);
    }
})->with([
    'empty' => ['', DocLine::EMPTY, []],
    'callable' => ['[getInputs]', DocLine::CALLABLE, ['callable' => 'getInputs']],
    'model ref' => ['@User[] ?$users The users', DocLine::MODEL_REF, ['model' => 'User', 'isArray' => '[]', 'optional' => '?', 'variable' => 'users']],
    'bare model ref' => ['@User', DocLine::MODEL_REF, ['model' => 'User']],
    'broken model ref' => ['@ not a ref', DocLine::MALFORMED_MODEL_REF, []],
    'template' => ['{UserResponse} The user', DocLine::TEMPLATE, ['template' => 'UserResponse']],
    'field' => ['string(email) ?$email Address', DocLine::FIELD, ['type' => 'string', 'format' => 'email', 'optional' => '?', 'variable' => 'email', 'description' => 'Address']],
    'malformed' => ['string email', DocLine::MALFORMED, []],
]);

it('puts the middleware lines of @input and @header ahead of the method\'s own', function () {
    $doc = OperationDocParser::parse(
        docComment('@input string $name Name', '@header string $X-Own Own'),
        [TestAuthMiddleware::class, HeaderMiddleware::class]
    );

    expect(array_map(static fn (DocLine $l): ?string => $l->variable(), $doc->inputs()))->toBe(['token', 'name']);
    expect($doc->inputs()[0]->middleware)->toBe(TestAuthMiddleware::class);
    expect($doc->inputs()[1]->isOwn())->toBeTrue();

    expect(array_map(static fn (DocLine $l): ?string => $l->variable(), $doc->headers()))->toBe(['X-Auth-Token', 'X-Own']);
});

it('reads middleware groups, and skips the aliases in them', function () {
    Route::middlewareGroup('doc-parser-test', ['throttle:api', TestAuthMiddleware::class]);

    $doc = OperationDocParser::parse(docComment('@input string $name Name'), ['doc-parser-test', 'not-a-group']);

    expect(array_map(static fn (DocLine $l): ?string => $l->variable(), $doc->inputs()))->toBe(['token', 'name']);
});

it('reads @security, @deprecated, @default and @example', function () {
    $doc = OperationDocParser::parse(docComment(
        '@security BearerAuth',
        '@security',
        '@deprecated Use v2',
        '@default $page 1',
        '@example $page 3',
        '@example $ratio 0.5',
        '@default $active true',
        '@example $name John Smith',
    ));

    expect($doc->securitySchemes())->toBe(['BearerAuth']);
    expect($doc->isDeprecated())->toBeTrue();
    expect($doc->defaultsAndExamples())->toBe([
        'page' => ['default' => 1, 'example' => 3],
        'active' => ['default' => true],
        'ratio' => ['example' => 0.5],
        'name' => ['example' => 'John Smith'],
    ]);
});

/** An API with one action whose docblock and middleware the test chooses. */
function docParserApi(array $middleware): string
{
    $api = new class extends BaseApi {
        public static array $middleware = [];

        public static function getMethods(): array
        {
            return ['controllers' => ['doc' => [
                'controller' => DocParserTestController::class,
                'middleware' => static::$middleware,
                'actions' => ['emptyInput', 'groupDefault'],
            ]]];
        }
    };
    $api::$middleware = $middleware;
    (new ReflectionProperty(BaseApi::class, 'preparedMethods'))->setValue(null, []);

    return get_class($api);
}

class DocParserTestController
{
    /**
     * An action with an empty tag
     * @input
     * @input string $name Name
     */
    public function emptyInput()
    {
    }

    /**
     * A default for an input that only the middleware declares
     * @default $token abc
     */
    public function groupDefault()
    {
    }
}

it('no longer fails the spec on an empty @input', function () {
    $spec = docParserApi([])::getOpenApiConfig('v1');

    $schema = $spec['paths']['/v1/doc/emptyInput']['post']['requestBody']['content'];
    expect(json_encode($schema))->toContain('"name"');
});

it('no longer fails the spec on an alias in a middleware group', function () {
    Route::middlewareGroup('doc-parser-alias', ['throttle:api', TestAuthMiddleware::class]);

    $spec = docParserApi(['doc-parser-alias'])::getOpenApiConfig('v1');

    expect(json_encode($spec['paths']['/v1/doc/emptyInput']['post']))->toContain('"token"');
});

it('lets the linter see the inputs of a middleware group', function () {
    Route::middlewareGroup('doc-parser-lint', [TestAuthMiddleware::class]);

    $issues = (new OpenApiLinter())->lintVersionList(['v1' => docParserApi(['doc-parser-lint'])]);
    $rules = array_map(static fn (LintIssue $i): string => "{$i->rule} {$i->where}", $issues);

    expect($rules)->not->toContain('default.unknown-variable v1 · doc.groupDefault');
    expect($rules)->toContain('tag.empty v1 · doc.emptyInput');
});
