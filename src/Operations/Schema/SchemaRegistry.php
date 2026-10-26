<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Operations\Schema;

/**
 * The templates of an API class, and the envelope its responses travel in.
 *
 * getOpenApiTemplates() unfolded from the shorthand, with the two templates
 * the package always provides — `Error` and `Success` — merged in, and
 * `$responseEnvelope` read as a schema. Built once per class.
 */
final class SchemaRegistry
{
    /** The placeholder in a custom envelope that the response schema replaces. */
    public const ENVELOPE_PAYLOAD = '{payload}';

    /** @var array<class-string, self> */
    private static array $instances = [];

    /** @var array<string, array>|null */
    private ?array $templates = null;

    /** @param class-string $apiClass */
    private function __construct(public readonly string $apiClass)
    {
    }

    /** @param class-string $apiClass */
    public static function for(string $apiClass): self
    {
        return self::$instances[$apiClass] ??= new self($apiClass);
    }

    public static function flush(): void
    {
        self::$instances = [];
    }

    /** Whether `$useResponseTemplates` is on — `@Model` and `{Template}` resolve only then. */
    public function enabled(): bool
    {
        return (bool) $this->apiClass::$useResponseTemplates;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->all());
    }

    /**
     * Every template as a full object schema, the shorthand unfolded and the
     * two the package always provides merged in.
     *
     * @return array<string, array>
     */
    public function all(): array
    {
        if ($this->templates !== null) {
            return $this->templates;
        }

        $templates = [];
        foreach ((array) $this->apiClass::getOpenApiTemplates() as $name => $fields) {
            $templates[(string) $name] = Shorthand::fields(is_array($fields) ? $fields : [], true);
        }

        $errorFields = [
            'errorKey' => ['type' => 'string'],
            'message' => ['type' => 'string'],
        ];

        if ($this->hasEnvelope()) {
            // Every template describes a payload here, the two defaults too:
            // an own `Error` or `Success` is the payload of that answer, and
            // the envelope comes from $responseEnvelope like everywhere else.
            $errorPayload = $templates['Error']
                ?? Shorthand::fields($errorFields, true);
            $successPayload = $templates['Success']
                ?? ['type' => 'object', 'description' => 'Response payload'];

            $templates['Error'] = $this->wrap($errorPayload);
            $templates['Success'] = $this->wrap($successPayload);
        } else {
            $defaults = [
                'Error' => Shorthand::fields(['success' => ['type' => 'boolean']] + $errorFields, true),
                'Success' => Shorthand::fields([
                    'success' => ['type' => 'boolean'],
                    'payload' => ['type' => 'object', 'description' => 'Response payload'],
                ], true),
            ];

            // An own `Error` or `Success` adds to the default rather than
            // replacing it — the historical merge, kept as it was.
            foreach ($defaults as $name => $default) {
                if (!isset($templates[$name])) {
                    $templates[$name] = $default;
                    continue;
                }
                $templates[$name]['properties'] = array_merge($default['properties'], $templates[$name]['properties']);
                $templates[$name]['required'] = array_values(array_unique(array_merge(
                    $default['required'],
                    $templates[$name]['required']
                )));
            }
        }

        return $this->templates = $templates;
    }

    public function hasEnvelope(): bool
    {
        return $this->envelopeFields() !== null;
    }

    /**
     * The envelope as a map of fields in the template shorthand, or null when
     * there is none.
     *
     * @return array<string, mixed>|null
     */
    public function envelopeFields(): ?array
    {
        $envelope = $this->apiClass::$responseEnvelope;

        if ($envelope === true) {
            return ['success!' => 'boolean', 'payload!' => self::ENVELOPE_PAYLOAD];
        }

        if (is_array($envelope) && $envelope !== []) {
            return $envelope;
        }

        return null;
    }

    /**
     * Wraps a body schema in the response envelope; the schema itself when
     * there is no envelope.
     */
    public function wrap(array $schema): array
    {
        $fields = $this->envelopeFields();

        if ($fields === null) {
            return $schema;
        }

        $envelope = Shorthand::fields($fields, true);
        $envelope['properties'] = self::replacePayload($envelope['properties'], $schema);

        return $envelope;
    }

    /**
     * Puts the body schema where the envelope says `{payload}` — at any depth,
     * so a payload under `data.result` is as good as one at the top.
     */
    private static function replacePayload(array $properties, array $schema): array
    {
        foreach ($properties as $name => $definition) {
            if (!is_array($definition)) {
                continue;
            }

            if (($definition['type'] ?? null) === self::ENVELOPE_PAYLOAD) {
                $properties[$name] = $schema;
                continue;
            }

            if (isset($definition['properties']) && is_array($definition['properties'])) {
                $properties[$name]['properties'] = self::replacePayload($definition['properties'], $schema);
            }
        }

        return $properties;
    }
}
