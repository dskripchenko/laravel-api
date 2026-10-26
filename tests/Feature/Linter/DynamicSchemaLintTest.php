<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Components\BaseApi;
use Dskripchenko\LaravelApi\Services\Linter\LintIssue;
use Dskripchenko\LaravelApi\Services\Linter\OpenApiLinter;

class DynamicLintApi extends BaseApi
{
    public static $useResponseTemplates = true;

    public static array $actions = [];

    public static function getMethods(): array
    {
        return ['controllers' => ['d' => ['controller' => DynamicLintController::class, 'actions' => static::$actions]]];
    }

    public static function getOpenApiTemplates(): array
    {
        return ['User' => ['id' => 'integer!']];
    }
}

class DynamicLintNoTemplatesApi extends DynamicLintApi
{
    public static $useResponseTemplates = false;
}

class DynamicLintController
{
    /**
     * Throws
     * @input [throws]
     */
    public function throwing()
    {
    }

    /**
     * Not an array
     * @input [notArray]
     */
    public function notArrayAction()
    {
    }

    /**
     * Not strings
     * @input [notStrings]
     */
    public function notStringsAction()
    {
    }

    /**
     * Nonsense
     * @input [nonsense]
     * @output [nonsense]
     */
    public function nonsenseAction()
    {
    }

    /**
     * Unknown templates
     * @input [missingModel]
     * @output [missingTemplate]
     */
    public function unknownTemplates()
    {
    }

    /**
     * A model naming no field
     * @output [bareModel]
     */
    public function bareModelAction()
    {
    }

    /**
     * Fine
     * @input [fine]
     * @output [fineSchema]
     */
    public function fineAction()
    {
    }

    /**
     * Missing
     * @input [noSuchMethod]
     */
    public function missing()
    {
    }

    public function throws(): array
    {
        throw new RuntimeException('the database is not there');
    }

    public function notArray(): string
    {
        return 'string $name Name';
    }

    public function notStrings(): array
    {
        return ['string $ok Ok', 42];
    }

    public function nonsense(): array
    {
        return ['this is not a line'];
    }

    public function missingModel(): array
    {
        return ['@Missing'];
    }

    public function missingTemplate(): array
    {
        return ['{Missing}'];
    }

    public function bareModel(): array
    {
        return ['@User'];
    }

    public function fine(): array
    {
        return ['string $name Name', '@User'];
    }

    public function fineSchema(): array
    {
        return ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']]];
    }
}

/** @return array<int, array{0: string, 1: string}> rule and message of each issue the action gets */
function lintDynamic(string $action, array $definition = [], string $api = DynamicLintApi::class): array
{
    DynamicLintApi::$actions = [$action => $definition];
    BaseApi::flushPreparedMethods();

    $issues = (new OpenApiLinter())->lintVersionList(['v1' => $api]);

    return array_values(array_map(
        static fn (LintIssue $i): array => [$i->rule, $i->message],
        array_filter($issues, static fn (LintIssue $i): bool => str_starts_with($i->where, "v1 · d.{$action}"))
    ));
}

it('reports a [method] that throws, instead of failing like the spec does', function () {
    $issues = lintDynamic('throwing');

    expect($issues)->toHaveCount(1);
    expect($issues[0][0])->toBe('operation.describe-failed');
    expect($issues[0][1])->toContain('RuntimeException: the database is not there');
});

it('reports a [method] that returns something other than an array', function () {
    expect(lintDynamic('notArrayAction'))->toBe([[
        'callable.not-array',
        '[notArray] returned string, and it is ignored: it has to return docblock lines or an object schema.',
    ]]);
});

it('reports what a [method] returns that is not a string', function () {
    expect(lintDynamic('notStringsAction'))->toBe([[
        'callable.non-string',
        '[notStrings] returned int at [1], and it is ignored: the lines have to be strings.',
    ]]);
});

it('reports the lines a [method] returns that do not parse, for @input and @output', function () {
    expect(array_column(lintDynamic('nonsenseAction'), 1))->toBe([
        '[nonsense] returned `this is not a line` for @input, which does not parse, and it is dropped.',
        '[nonsense] returned `this is not a line` for @output, which does not parse, and it is dropped.',
    ]);
});

it('reports the templates a [method] refers to that do not exist', function () {
    expect(lintDynamic('unknownTemplates'))->toBe([
        ['callable.unknown-template', '[missingModel] returned `@Missing` for @input: it refers to the template `Missing`, which is not defined.'],
        ['callable.unknown-template', '[missingTemplate] returned `{Missing}` for @output: it refers to the template `Missing`, which is not defined.'],
    ]);
});

it('reports a template a [method] refers to while templates are off', function () {
    $issues = lintDynamic('fineAction', [], DynamicLintNoTemplatesApi::class);

    expect($issues)->toBe([[
        'callable.unknown-template',
        '[fine] returned `@User` for @input: it refers to the template `User`, and $useResponseTemplates is off.',
    ]]);
});

it('reports an @output model that names no field', function () {
    expect(lintDynamic('bareModelAction'))->toBe([[
        'callable.malformed-line',
        '[bareModel] returned `@User` for @output, which does not parse, and it is dropped.',
    ]]);
});

it('says it once for an action serving several HTTP methods', function () {
    expect(lintDynamic('nonsenseAction', ['method' => ['get', 'post']]))->toHaveCount(2);
});

it('finds nothing wrong with a [method] that returns lines and schemas', function () {
    expect(lintDynamic('fineAction'))->toBe([]);
});

it('does not call a [method] the controller does not have', function () {
    expect(array_column(lintDynamic('missing'), 0))->toBe(['tag.callable-missing']);
});
