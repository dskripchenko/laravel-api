<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Transports\JsonRpc;

use Dskripchenko\LaravelApi\Operations\DescriptorBuilder;
use Dskripchenko\LaravelApi\Operations\Doc\OperationDocParser;
use Dskripchenko\LaravelApi\Operations\Schema\JsonSchemas;
use Dskripchenko\LaravelApi\Operations\Schema\SchemaRef;
use Dskripchenko\LaravelApi\Operations\Schema\SchemaRegistry;
use Dskripchenko\LaravelApi\Transports\NamedOperations;

/**
 * The methods of a version as an OpenRPC document — what `rpc.discover`
 * answers with.
 *
 * One method per action, by name: every `@input` field is a parameter, the
 * success payload is the result. Templates go to `components.schemas`, where
 * OpenRPC looks for them under the same `#/components/schemas/…` the model
 * already spells references with.
 */
final class OpenRpcDocument
{
    public const VERSION = '1.3.2';

    public function __construct(private readonly NamedOperations $operations)
    {
    }

    public function toArray(): array
    {
        $apiClass = $this->operations->apiClass;
        $builder = DescriptorBuilder::for($apiClass);
        $doc = OperationDocParser::parse((new \ReflectionClass($apiClass))->getDocComment());

        $methods = [];
        foreach ($this->operations->all() as $name => $operation) {
            [$descriptor] = $builder->build($operation);

            $method = [
                'name' => $name,
                'summary' => trim(strtok($descriptor->summary(), "\n") ?: '') ?: $name,
                'paramStructure' => 'by-name',
                'params' => self::params($descriptor->input->schema()),
                'result' => [
                    'name' => 'result',
                    // An empty schema is `{}` — anything — not a list.
                    'schema' => ($descriptor->output?->schema ?? self::explicitResult($descriptor->responses))
                        ?: new \stdClass(),
                ],
            ];

            $description = trim($descriptor->summary() . "\n\n" . $descriptor->description());
            if ($description !== '') {
                $method['description'] = $description;
            }
            if ($descriptor->deprecated) {
                $method['deprecated'] = true;
            }

            $methods[] = $method;
        }

        $document = [
            'openrpc' => self::VERSION,
            'info' => [
                'title' => $doc->summary !== '' ? $doc->summary : $apiClass,
                'version' => $this->operations->version,
            ],
            'methods' => $methods,
        ];

        if ($doc->description !== '') {
            $document['info']['description'] = $doc->description;
        }

        $schemas = SchemaRegistry::for($apiClass)->all();
        if ($schemas !== []) {
            $document['components'] = ['schemas' => $schemas];
        }

        return JsonSchemas::encodable($document);
    }

    /**
     * The fields of the input object, one parameter each. An input named by a
     * template as a whole is one parameter carrying its fields — by-name
     * params are an object either way.
     *
     * @return array[]
     */
    private static function params(array $schema): array
    {
        if (isset($schema['$ref'])) {
            return [['name' => 'params', 'required' => true, 'schema' => $schema]];
        }

        $required = (array) ($schema['required'] ?? []);
        $params = [];
        foreach ((array) ($schema['properties'] ?? []) as $name => $property) {
            $property = (array) $property;
            $param = ['name' => (string) $name];

            if (isset($property['description']) && $property['description'] !== '') {
                $param['description'] = $property['description'];
            }
            unset($property['description']);

            $param['required'] = in_array($name, $required, true);
            $param['schema'] = $property;
            $params[] = $param;
        }

        return $params;
    }

    /**
     * With `@response` listing the answers, the result is the template the
     * 200 answer carries, if any.
     *
     * @param  \Dskripchenko\LaravelApi\Operations\Descriptor\ResponseDeclaration[]  $responses
     */
    private static function explicitResult(array $responses): array
    {
        foreach ($responses as $response) {
            if ($response->code === '200' && $response->template !== null) {
                return SchemaRef::to($response->template);
            }
        }

        return [];
    }
}
