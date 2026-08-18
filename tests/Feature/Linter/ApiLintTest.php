<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Facades\ApiModule;
use Dskripchenko\LaravelApi\Services\Linter\LintIssue;
use Dskripchenko\LaravelApi\Services\Linter\OpenApiLinter;
use Tests\Fixtures\Linter\BrokenApi;
use Tests\Fixtures\Linter\BrokenModule;
use Tests\Fixtures\Linter\WarningOnlyModule;
use Tests\Fixtures\Versions\v1\TestApi;

/**
 * @return LintIssue[]
 */
function lintBroken(bool $unrouted = false): array
{
    return (new OpenApiLinter())
        ->withUnroutedMethods($unrouted)
        ->lintVersionList(['v1' => BrokenApi::class]);
}

/**
 * @param  LintIssue[]  $issues
 * @return LintIssue[]
 */
function issuesOfRule(array $issues, string $rule): array
{
    return array_values(array_filter($issues, static fn (LintIssue $i): bool => $i->rule === $rule));
}

/**
 * Swaps the module the command reads. The facade caches whatever it resolved
 * first, so a plain rebind would be ignored and the test would pass against
 * the wrong fixture.
 */
function useModule(string $moduleClass): void
{
    app()->bind('api_module', static fn () => new $moduleClass());
    ApiModule::clearResolvedInstance('api_module');
}

it('reports nothing for markup that is correct', function () {
    $issues = (new OpenApiLinter())->lintVersionList(['v1' => TestApi::class]);

    expect($issues)->toBe([]);
});

it('catches an action whose method was renamed away', function () {
    // The defect this whole command exists for: at runtime it is a 404
    // indistinguishable from a wrong URL.
    $found = issuesOfRule(lintBroken(), 'action.missing-method');

    expect($found)->toHaveCount(1);
    expect($found[0]->where)->toBe('v1 · broken.renamed');
    expect($found[0]->isError())->toBeTrue();
});

it('catches an action pointing at a method that cannot be called', function () {
    $found = issuesOfRule(lintBroken(), 'action.unreachable-method');

    expect($found)->toHaveCount(1);
    expect($found[0]->where)->toBe('v1 · broken.protectedTarget');
});

it('catches a controller class that does not exist', function () {
    expect(issuesOfRule(lintBroken(), 'controller.missing-class'))->toHaveCount(1);
});

it('catches a controller entry with no controller key', function () {
    expect(issuesOfRule(lintBroken(), 'controller.missing-key'))->toHaveCount(1);
});

it('catches an HTTP verb the router does not serve', function () {
    $found = issuesOfRule(lintBroken(), 'action.unknown-http-method');

    expect($found)->toHaveCount(1);
    expect($found[0]->message)->toContain('fetch');
});

it('catches a middleware class that is not there', function () {
    expect(issuesOfRule(lintBroken(), 'middleware.missing-class'))->toHaveCount(1);
});

it('accepts a middleware written with Laravel parameter syntax', function () {
    // `Middleware:argument` — the class is the part before the colon. Checking
    // the whole string turned every parameterised middleware in a real
    // application into a false positive.
    $messages = array_map(
        static fn (LintIssue $i): string => $i->message,
        issuesOfRule(lintBroken(), 'middleware.missing-class')
    );

    expect(implode("\n", $messages))->not->toContain('PassingMiddleware');
});

it('catches an unknown type that would silently become string', function () {
    $found = issuesOfRule(lintBroken(), 'tag.unknown-type');

    expect($found)->toHaveCount(1);
    expect($found[0]->message)->toContain('datetime');
    expect($found[0]->isError())->toBeFalse();
});

it('catches nesting whose parent was never declared', function () {
    $found = issuesOfRule(lintBroken(), 'tag.orphan-nesting');

    expect($found)->toHaveCount(1);
    expect($found[0]->where)->toBe('v1 · broken.orphanNesting');
});

it('catches a parent declared as the wrong kind of container', function () {
    $found = issuesOfRule(lintBroken(), 'tag.nesting-type-mismatch');

    expect($found)->toHaveCount(1);
    expect($found[0]->message)->toContain('array');
});

it('catches references to templates that are not defined', function () {
    $issues = lintBroken();

    // @input @MissingRequest and @output @MissingModel[]
    expect(issuesOfRule($issues, 'tag.unknown-template'))->toHaveCount(2);
    // @response 404 {MissingTemplate}
    expect(issuesOfRule($issues, 'response.unknown-template'))->toHaveCount(1);
    // a template whose own field points at a template that is absent — and
    // only that one: a reference followed by prose is legitimate shorthand.
    $refs = issuesOfRule($issues, 'template.unknown-ref');
    expect($refs)->toHaveCount(1);
    expect($refs[0]->message)->toContain('AbsentTemplate');
});

it('catches security schemes that are not defined', function () {
    $found = issuesOfRule(lintBroken(), 'security.unknown-scheme');

    // One from @security MissingScheme, one from the action-level 'security' key.
    expect($found)->toHaveCount(2);
});

it('catches markup that does not parse', function () {
    $issues = lintBroken();

    expect(issuesOfRule($issues, 'tag.malformed'))->not->toBeEmpty();
    expect(issuesOfRule($issues, 'response.malformed'))->toHaveCount(1);
    expect(issuesOfRule($issues, 'default.malformed'))->toHaveCount(1);
});

it('catches a duplicated variable and a stray default', function () {
    $issues = lintBroken();

    expect(issuesOfRule($issues, 'tag.duplicate-variable'))->toHaveCount(1);
    expect(issuesOfRule($issues, 'default.unknown-variable'))->toHaveCount(1);
    expect(issuesOfRule($issues, 'example.unknown-variable'))->toHaveCount(1);
});

it('catches dynamic inputs from a method the controller lacks', function () {
    expect(issuesOfRule(lintBroken(), 'tag.callable-missing'))->toHaveCount(1);
});

it('catches a duplicated and an impossible response code', function () {
    $issues = lintBroken();

    expect(issuesOfRule($issues, 'response.duplicate-code'))->toHaveCount(1);
    expect(issuesOfRule($issues, 'response.impossible-code'))->toHaveCount(1);
});

it('does not report unrouted methods unless asked', function () {
    expect(issuesOfRule(lintBroken(), 'controller.unrouted-method'))->toBe([]);

    $found = issuesOfRule(lintBroken(unrouted: true), 'controller.unrouted-method');

    expect($found)->not->toBeEmpty();
    expect(array_map(static fn (LintIssue $i): string => $i->message, $found))
        ->toContain('BrokenController::neverRouted() is public and no action points at it.');
});

it('lints only the version asked for', function () {
    $issues = (new OpenApiLinter())->lintVersionList(
        ['v1' => BrokenApi::class, 'v2' => TestApi::class],
        'v2'
    );

    expect($issues)->toBe([]);
});

it('reports a version mapped to a class that does not exist', function () {
    $issues = (new OpenApiLinter())->lintVersionList(['v9' => 'Tests\Fixtures\Linter\NoSuchApi']);

    expect($issues)->toHaveCount(1);
    expect($issues[0]->rule)->toBe('api.missing-class');
});

it('command passes on a clean module', function () {
    $this->artisan('api:lint')->assertExitCode(0);
});

it('command says out loud that the unrouted check was skipped', function () {
    // A clean report that quietly covered less than the reader assumes is
    // worse than a noisy one.
    $this->artisan('api:lint')
        ->expectsOutputToContain('--unrouted')
        ->assertExitCode(0);
});

it('command exits non-zero when a version is broken', function () {
    useModule(BrokenModule::class);

    $this->artisan('api:lint')->assertExitCode(1);
});

it('command emits json', function () {
    useModule(BrokenModule::class);

    $this->artisan('api:lint --json')->assertExitCode(1);
});

it('command with --strict fails on warnings alone', function () {
    useModule(WarningOnlyModule::class);

    $this->artisan('api:lint')->assertExitCode(0);
    $this->artisan('api:lint --strict')->assertExitCode(1);
});
