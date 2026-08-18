<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Services\Linter\LintIssue;
use Dskripchenko\LaravelApi\Services\Linter\OpenApiLinter;
use Dskripchenko\LaravelApiExample\Versions\v1\Api as V1;
use Dskripchenko\LaravelApiExample\Versions\v1_1\Api as V1_1;
use Dskripchenko\LaravelApiExample\Versions\v1_2\Api as V1_2;
use Dskripchenko\LaravelApiExample\Versions\v2\Api as V2;

/**
 * The example shipped with the package is the closest thing to a real
 * application the test suite has — a linter that only ever sees its own
 * fixtures proves very little.
 *
 * @return LintIssue[]
 */
function lintExample(bool $unrouted = false): array
{
    return (new OpenApiLinter())
        ->withUnroutedMethods($unrouted)
        ->lintVersionList([
            'v1' => V1::class,
            'v1.1' => V1_1::class,
            'v1.2' => V1_2::class,
            'v2' => V2::class,
        ]);
}

it('finds nothing wrong with the bundled example', function () {
    expect(lintExample())->toBe([]);
});

it('does not mistake inherited framework methods for forgotten endpoints', function () {
    // Every controller inherits callAction(), middleware(), getMiddleware()
    // from Laravel and success()/error() from this package. Reporting those
    // buried the two real findings under sixty lines of noise.
    $messages = array_map(
        static fn (LintIssue $i): string => $i->message,
        lintExample(unrouted: true)
    );

    foreach (['callAction', 'middleware', 'getMiddleware', 'success', 'notFound'] as $inherited) {
        expect(implode("\n", $messages))->not->toContain("::{$inherited}()");
    }
});

it('reports the method left behind when an action was disabled', function () {
    // v1.1 turns off 'a' => false, and AController::a() stays where it was:
    // public, and no longer reachable through any route.
    $messages = array_map(
        static fn (LintIssue $i): string => "{$i->where} {$i->message}",
        lintExample(unrouted: true)
    );

    expect($messages)->toHaveCount(2);
    expect($messages[0])->toContain('v1.1 · a');
    expect($messages[0])->toContain('AController::a()');
});
