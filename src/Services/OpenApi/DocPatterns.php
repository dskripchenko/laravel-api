<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Services\OpenApi;

/**
 * The grammar of the docblock markup, in one place.
 *
 * These expressions used to live as private methods on OpenApiTrait, where
 * only the generator could see them. The linter has to read the markup exactly
 * as the generator does — a second copy of these patterns would drift, and the
 * drift would be invisible: the linter would pass what the generator mangles,
 * which is worse than having no linter at all.
 *
 * The trait still owns the semantics; this class owns only the shapes.
 */
final class DocPatterns
{
    /**
     * `type(format) ?$variable Description` — the body of @input, @output and
     * @header.
     */
    public static function inputOutput(): string
    {
        return '/^(?<type>[\S]*?)(?:\((?<format>[a-zA-Z0-9\-]+)\))?[\s]*+(?<optional>\?)?\$(?<variable>[\S]*+)([\s]*?(?<description>\S[\S\s]*?))?$/';
    }

    /** `{TemplateName} Description` — an @output referring to a template. */
    public static function inputOutputTemplate(): string
    {
        return '/{(?<template>[\S]*?)}(?<description>[\s\S]*?)$/';
    }

    /** `[methodName]` — inputs supplied dynamically by a controller method. */
    public static function inputsCallable(): string
    {
        return '/^\[(?<callable>[\S]*?)\]$/';
    }

    /** `@Model`, `@Model[]`, optionally bound to a variable. */
    public static function modelRef(): string
    {
        return '/^@(?<model>[\w]+)(?<isArray>\[\])?\s*(?:(?<optional>\?)?\$(?<variable>[\S]+)(?:\s+(?<description>.+))?)?$/';
    }

    /** `404 {Template}` or `404 Description`. */
    public static function response(): string
    {
        return '/^(?<code>\d{3})\s+(?:(?<template>\{[\S]*?\})|(?<description>.+))$/';
    }

    /** `$variable value` — the body of @default and @example. */
    public static function defaultExample(): string
    {
        return '/^\$(?<variable>[\S]+)\s+(?<value>.+)$/';
    }

    /**
     * The types the generator understands. Anything else is silently turned
     * into `string` — which is precisely why the linter reports it.
     *
     * @return string[]
     */
    public static function availableDataTypes(): array
    {
        return ['string', 'file', 'number', 'integer', 'boolean', 'array', 'object'];
    }
}
