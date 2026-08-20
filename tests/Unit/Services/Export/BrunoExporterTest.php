<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Services\Export\BrunoExporter;
use Tests\Fixtures\OpenApi\ExtendedApi;

/**
 * A Bruno collection is a directory of plain files, and that is the reason to
 * generate one: it lives in the repository beside the code, and a diff of it
 * reads. These tests hold it to the shape Bruno actually opens.
 */
function brunoSpec(): array
{
    return [
        'info' => ['title' => 'Test API', 'description' => ''],
        'servers' => [['url' => 'http://localhost/api']],
        'components' => [
            'securitySchemes' => [
                'BearerAuth' => ['type' => 'apiKey', 'name' => 'Authorization', 'in' => 'header'],
            ],
        ],
        'paths' => [
            '/v1/user/list' => [
                'get' => [
                    'summary' => 'List users',
                    'tags' => ['user'],
                    'operationId' => 'user_list',
                    'security' => [['BearerAuth' => []]],
                    'parameters' => [
                        ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer'], 'example' => 1],
                        ['name' => 'X-Request-Id', 'in' => 'header', 'schema' => ['type' => 'string'], 'example' => 'abc'],
                    ],
                    'responses' => [],
                ],
            ],
            '/v1/user/save' => [
                'post' => [
                    'summary' => 'Save a user',
                    'tags' => ['user'],
                    'requestBody' => [
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'properties' => [
                                        'name' => ['type' => 'string', 'example' => 'Ada'],
                                        'age' => ['type' => 'integer'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'responses' => [],
                ],
                'put' => [
                    'summary' => 'Replace a user',
                    'tags' => ['user'],
                    'responses' => [],
                ],
            ],
        ],
    ];
}

it('writes a manifest, an environment and one file per request', function () {
    $files = (new BrunoExporter())->files(brunoSpec(), 'v1');

    expect($files)->toHaveKey('bruno.json')
        ->and($files)->toHaveKey('environments/default.bru')
        ->and($files)->toHaveKey('user/list.bru');

    expect(json_decode($files['bruno.json'], true))
        ->toMatchArray(['version' => '1', 'type' => 'collection']);
});

it('keeps two methods of one path in two files', function () {
    $files = (new BrunoExporter())->files(brunoSpec(), 'v1');

    // Two files called `save.bru` in one folder is a collection nobody trusts.
    expect($files)->toHaveKey('user/save-post.bru')
        ->and($files)->toHaveKey('user/save-put.bru');
});

it('builds a request Bruno can open', function () {
    $files = (new BrunoExporter())->files(brunoSpec(), 'v1');
    $request = $files['user/list.bru'];

    expect($request)->toContain('meta {')
        ->and($request)->toContain('name: List users')
        ->and($request)->toContain('get {')
        ->and($request)->toContain('url: {{baseUrl}}/v1/user/list?page=1')
        ->and($request)->toContain('params:query {');
});

it('carries the declared security scheme as a variable, never a secret', function () {
    $request = (new BrunoExporter())->files(brunoSpec(), 'v1')['user/list.bru'];

    // An exported collection is a thing people commit.
    expect($request)->toContain('Authorization: {{token}}')
        ->and($request)->toContain('X-Request-Id: abc');
});

it('writes the request body as JSON, with the documented examples in it', function () {
    $request = (new BrunoExporter())->files(brunoSpec(), 'v1')['user/save-post.bru'];

    expect($request)->toContain('body: json')
        ->and($request)->toContain('body:json {')
        ->and($request)->toContain('"name": "Ada"')
        ->and($request)->toContain('"age": 0');
});

it('says a request has no body rather than inventing one', function () {
    $request = (new BrunoExporter())->files(brunoSpec(), 'v1')['user/save-put.bru'];

    expect($request)->toContain('body: none')
        ->and($request)->not->toContain('body:json');
});

it('does not let a wrapped summary break the block it sits in', function () {
    $spec = brunoSpec();
    $spec['paths']['/v1/user/list']['get']['summary'] = "A summary\nthat wrapped";

    $request = (new BrunoExporter())->files($spec, 'v1')['user/list.bru'];

    // A newline inside a Bruno value ends the value: the name would truncate
    // and the rest would be read as a key.
    expect($request)->toContain('name: A summary that wrapped');
});

it('numbers the requests within their folder, as Bruno orders them', function () {
    $files = (new BrunoExporter())->files(brunoSpec(), 'v1');

    expect($files['user/list.bru'])->toContain('seq: 1')
        ->and($files['user/save-post.bru'])->toContain('seq: 2');
});

it('exports a single-endpoint spec as one document', function () {
    $spec = brunoSpec();
    $spec['paths'] = ['/v1/user/list' => $spec['paths']['/v1/user/list']];

    $document = (new BrunoExporter())->export($spec, 'v1');

    expect($document)->toContain('meta {')->and($document)->toContain('get {');
});

it('exports a real generated spec without falling over', function () {
    $files = (new BrunoExporter())->files(ExtendedApi::getOpenApiConfig('v1'), 'v1');

    expect($files)->toHaveKey('bruno.json')
        ->and(count($files))->toBeGreaterThan(2);
});
