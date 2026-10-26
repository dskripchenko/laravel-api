<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Components\BaseApi;
use Dskripchenko\LaravelApi\Operations\Descriptor\OperationDescriptor;
use Dskripchenko\LaravelApi\Operations\DescriptorBuilder;
use Dskripchenko\LaravelApi\Operations\Schema\SchemaRef;
use Dskripchenko\LaravelApi\Operations\Schema\SchemaRegistry;
use Dskripchenko\LaravelApi\Operations\Schema\Shorthand;
use Dskripchenko\LaravelApi\Services\OpenApi\OperationContext;

/** An API with templates, serving DescriptorTestController under `d`. */
class DescriptorTestApi extends BaseApi
{
    public static $useResponseTemplates = true;

    public static array $actions = [];

    public static function getMethods(): array
    {
        return ['controllers' => ['d' => ['controller' => DescriptorTestController::class, 'actions' => static::$actions]]];
    }

    public static function getOpenApiTemplates(): array
    {
        return ['User' => ['id' => 'integer!', 'name' => 'string'], 'Active' => ['since' => 'string']];
    }
}

class DescriptorTestController
{
    /**
     * Flat
     * @input string $name Name
     * @input integer ?$page Page [1,2,3]
     * @default $page 1
     * @output integer $id Id
     */
    public function flat()
    {
    }

    /**
     * Nested
     * @input object $address Address
     * @input string $address.city City
     */
    public function nested()
    {
    }

    /**
     * A body named by a template
     * @input @User
     * @output @User $user The user
     */
    public function model()
    {
    }

    /**
     * Dynamic
     * @input string $name Name
     * @input [inputSchema]
     * @output [outputLines]
     */
    public function dynamic()
    {
    }

    public function inputSchema(OperationContext $context): array
    {
        return ['type' => 'object', 'properties' => ['for' => ['type' => 'string', 'enum' => [$context->httpMethod]]]];
    }

    public function outputLines(): array
    {
        return ['integer $total Total'];
    }

    /**
     * Explicit answers
     * @response 200 {User}
     * @response 404 Not found
     * @security BearerAuth
     * @deprecated
     */
    public function answers()
    {
    }

    /**
     * A field whose description happens to name a template
     * @output string $status The status, e.g. {Active}
     */
    public function braces()
    {
    }

    /**
     * Lines that do not parse
     * @input {User} $user A template form, which @input does not take
     * @input @not-a-model $x A broken reference
     * @input string $kept Kept
     */
    public function garbage()
    {
    }
}

/** @return OperationDescriptor[] */
function describeAction(string $action, array $definition = []): array
{
    DescriptorTestApi::$actions = [$action => $definition];
    (new ReflectionProperty(BaseApi::class, 'preparedMethods'))->setValue(null, []);

    $operation = DescriptorTestApi::operations('v1')->find('d', $action);

    return DescriptorBuilder::for(DescriptorTestApi::class)->build($operation);
}

it('describes each HTTP method of an action on its own', function () {
    $descriptors = describeAction('dynamic', ['method' => ['get', 'POST']]);

    expect(array_map(static fn (OperationDescriptor $d): string => $d->httpMethod, $descriptors))->toBe(['get', 'POST']);
    expect(array_map(static fn (OperationDescriptor $d): string => $d->operationId, $descriptors))->toBe(['d_dynamic', 'd_dynamic_post']);
    expect($descriptors[1]->context->httpMethod)->toBe('post');

    // The [method] is asked once per HTTP method, and may answer differently.
    expect($descriptors[0]->input->schema()['properties']['for']['enum'])->toBe(['get']);
    expect($descriptors[1]->input->schema()['properties']['for']['enum'])->toBe(['post']);
});

it('keeps flat lines as fields, and as one object schema', function () {
    [$descriptor] = describeAction('flat');
    $input = $descriptor->input;

    expect($input->isNested())->toBeFalse();
    expect($input->fields())->toBe([
        ['name' => 'name', 'description' => 'Name', 'required' => true, 'type' => 'string'],
        ['name' => 'page', 'description' => 'Page', 'required' => false, 'type' => 'integer', 'enum' => ['1', '2', '3'], 'default' => 1],
    ]);
    expect($input->schema())->toBe([
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string', 'description' => 'Name'],
            'page' => ['type' => 'integer', 'enum' => ['1', '2', '3'], 'default' => 1, 'description' => 'Page'],
        ],
        'required' => ['name'],
    ]);
});

it('builds nested objects from dot-notation', function () {
    [$descriptor] = describeAction('nested');

    expect($descriptor->input->isNested())->toBeTrue();
    expect($descriptor->input->schema()['properties']['address'])->toBe([
        'type' => 'object',
        'description' => 'Address',
        'properties' => ['city' => ['type' => 'string', 'description' => 'City']],
        'required' => ['city'],
    ]);
});

it('takes a template named by @input as the whole input', function () {
    [$descriptor] = describeAction('model');

    expect($descriptor->input->refs)->toBe(['User']);
    expect($descriptor->input->schema())->toBe(SchemaRef::to('User'));
    expect($descriptor->output->schema['properties']['user'])->toBe(SchemaRef::to('User') + ['description' => 'The user']);
});

it('describes the output payload without the envelope', function () {
    DescriptorTestApi::$responseEnvelope = true;
    try {
        [$descriptor] = describeAction('flat');
    } finally {
        DescriptorTestApi::$responseEnvelope = false;
    }

    expect($descriptor->output->description)->toBe('Response payload');
    expect($descriptor->output->schema)->toBe([
        'type' => 'object',
        'properties' => ['id' => ['type' => 'integer', 'name' => 'id', 'description' => 'Id']],
        'required' => ['id'],
    ]);
});

it('unfolds the lines a [method] returns for @output', function () {
    [$descriptor] = describeAction('dynamic');

    expect(array_keys($descriptor->output->schema['properties']))->toBe(['total']);
});

it('lists @response instead of describing an output', function () {
    [$descriptor] = describeAction('answers');

    expect($descriptor->hasExplicitResponses())->toBeTrue();
    expect($descriptor->output)->toBeNull();
    expect(array_map(static fn ($r): array => [$r->code, $r->template, $r->description], $descriptor->responses))
        ->toBe([['200', 'User', ''], ['404', null, 'Not found']]);
    expect($descriptor->security)->toBe([['BearerAuth' => []]]);
    expect($descriptor->deprecated)->toBeTrue();
});

it('prefers the security of getMethods() over @security', function () {
    [$descriptor] = describeAction('answers', ['security' => [['ApiKey' => []]]]);

    expect($descriptor->security)->toBe([['ApiKey' => []]]);
});

it('reads {Template} on @output only as the whole line', function () {
    // It used to be matched anywhere in the line: with templates on, a field
    // mentioning `{Active}` in its description turned the whole response into
    // a $ref to Active.
    [$descriptor] = describeAction('braces');

    expect($descriptor->output->schema['properties'])->toHaveKey('status');
    expect($descriptor->output->schema)->not->toHaveKey('$ref');
});

it('drops the input lines the linter calls malformed', function () {
    [$descriptor] = describeAction('garbage');

    expect(array_column($descriptor->input->fields(), 'name'))->toBe(['kept']);
    expect($descriptor->input->refs)->toBe([]);
});

it('knows the templates of an API class and its envelope', function () {
    $registry = SchemaRegistry::for(DescriptorTestApi::class);

    expect($registry->enabled())->toBeTrue();
    expect($registry->has('User'))->toBeTrue();
    expect($registry->has('Error'))->toBeTrue();
    expect($registry->has('Nope'))->toBeFalse();
    expect($registry->wrap(['type' => 'string']))->toBe(['type' => 'string']);
});

it('unfolds the shorthand and names references', function () {
    expect(Shorthand::parse('string(email)! Contact'))->toBe(['type' => 'string', 'format' => 'email', 'required' => true, 'description' => 'Contact']);
    expect(Shorthand::parse('@User[]'))->toBe(['type' => 'array', 'items' => SchemaRef::to('User')]);
    expect(SchemaRef::name(SchemaRef::PREFIX . 'User'))->toBe('User');
});
