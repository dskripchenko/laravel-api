<?php

declare(strict_types=1);

namespace Tests\Fixtures\Linter;

use Dskripchenko\LaravelApi\Components\BaseApi;

/**
 * Broken API
 *
 * A route map with the mistakes that answer 404 instead of complaining.
 */
class BrokenApi extends BaseApi
{
    public static $useResponseTemplates = true;

    public static function getMethods(): array
    {
        return [
            'controllers' => [
                'broken' => [
                    'controller' => BrokenController::class,
                    'actions' => [
                        'fine' => ['action' => 'fine', 'method' => ['post']],
                        'unknownType' => ['action' => 'unknownType'],
                        'orphanNesting' => ['action' => 'orphanNesting'],
                        'nestingTypeMismatch' => ['action' => 'nestingTypeMismatch'],
                        'danglingRefs' => ['action' => 'danglingRefs'],
                        'malformed' => ['action' => 'malformed'],
                        'duplicatesAndStrays' => ['action' => 'duplicatesAndStrays'],
                        'callableMissing' => ['action' => 'callableMissing'],
                        'duplicateResponses' => ['action' => 'duplicateResponses'],

                        // The method was renamed and the map was not: at runtime
                        // this is a 404 indistinguishable from a wrong URL.
                        'renamed' => ['action' => 'methodThatWentAway'],

                        // Points at a method that exists and cannot be called.
                        'protectedTarget' => ['action' => 'hidden'],

                        // A verb the router does not serve.
                        'badVerb' => ['action' => 'fine', 'method' => ['fetch']],

                        // A middleware class that is not there.
                        'ghostMiddleware' => [
                            'action' => 'fine',
                            'middleware' => ['Tests\Fixtures\Linter\NoSuchMiddleware'],
                        ],

                        // An action-level security scheme nobody defined.
                        'ghostSecurity' => ['action' => 'fine', 'security' => ['GhostScheme']],

                        // Laravel's parameter syntax: the class is only the
                        // part before the colon, and it does exist.
                        'parameterisedMiddleware' => [
                            'action' => 'fine',
                            'middleware' => [PassingMiddleware::class . ':some.permission'],
                        ],
                    ],
                ],

                'missing' => [
                    'controller' => 'Tests\Fixtures\Linter\NoSuchController',
                    'actions' => ['whatever'],
                ],

                'noControllerKey' => [
                    'actions' => ['whatever'],
                ],
            ],
        ];
    }

    public static function getOpenApiTemplates(): array
    {
        return [
            'KnownTemplate' => [
                'id' => 'integer!',
                'name' => 'string',
            ],
            'RefersToNothing' => [
                'child' => '@AbsentTemplate',
            ],
            'RefersWithDescription' => [
                // The shorthand allows prose after the reference; the name is
                // the first token, not the whole string.
                'items' => '@KnownTemplate[] What to send when printing',
            ],
        ];
    }

    public static function getOpenApiSecurityDefinitions(): array
    {
        return [
            'KnownScheme' => [
                'type' => 'apiKey',
                'name' => 'Authorization',
                'in' => 'header',
            ],
        ];
    }
}
