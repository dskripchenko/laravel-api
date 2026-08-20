<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelApi\Services\OpenApi;

use Dskripchenko\LaravelApi\Facades\ApiModule;

/**
 * Deep links into the reference page — `/api/doc#<anchor>`.
 *
 * The page is rendered by Scalar, and Scalar addresses an operation by a hash
 * it builds itself:
 *
 *     {document}/tag/{tag}/{METHOD}{path}
 *
 * Every segment of that is something this package decides. The document is an
 * API version, the tag is a controller key, the path is the URI pattern filled
 * in, and the method is the one `getMethods()` declares. So the anchor is
 * derivable without asking the browser — which is the point: a link to an
 * endpoint is wanted from an editor, a README or a ticket, none of which can
 * run the page and read its URL bar.
 *
 * Slugs are Scalar's rule, reimplemented here for one reason: whatever we pass
 * it as a document slug, it slugifies again. The only safe thing to send is
 * something that survives the trip unchanged, and the cheapest such thing is
 * the output of the same rule. `ApiDocumentationController::index()` hands the
 * slug to the page so that it does not have to guess.
 */
final class DocLink
{
    /**
     * Scalar's slug rule, as of `@scalar/api-reference` 1.x.
     *
     * Lowercase, drop everything that is not a letter, a mark, a digit or a
     * separator, then collapse the separators into single hyphens and trim
     * them off the ends. Unicode letters survive: a Cyrillic version name
     * stays readable instead of collapsing into nothing.
     *
     * Idempotent by construction — `slug(slug($s)) === slug($s)` — and that is
     * the property the whole scheme rests on.
     */
    public static function slug(string $text): string
    {
        $text = mb_substr(trim($text), 0, 255);

        if (class_exists(\Normalizer::class)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
        }

        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{M}\p{N}\s_-]/u', '', $text) ?? '';
        $text = preg_replace('/[\s_-]+/u', '-', $text) ?? '';

        return trim($text, '-');
    }

    /**
     * The slug a version is addressed by on the page.
     *
     * Scalar's own rule drops a dot rather than replacing it, so `v1.1` would
     * become `v11` — legal, but it reads as a different version and it sits one
     * typo away from colliding with a real `v11`. Dots and slashes become
     * hyphens first; the result then passes through Scalar's rule unchanged,
     * which is the only requirement, since Scalar slugifies whatever we send.
     */
    public static function documentSlug(string $version): string
    {
        return static::slug(str_replace(['.', '/', '\\'], '-', $version));
    }

    /**
     * The hash of one endpoint, without the leading `#`.
     *
     * One method per call on purpose: an action declaring both GET and POST is
     * two operations on the page and two anchors, and folding them into one
     * link would send half the readers to the wrong one.
     */
    public static function anchor(string $version, string $controller, string $action, string $httpMethod): string
    {
        $document = static::documentSlug($version);
        $tag      = static::slug($controller);
        $method   = strtoupper($httpMethod);
        $path     = static::path($version, $controller, $action);

        return "{$document}/tag/{$tag}/{$method}{$path}";
    }

    /**
     * The URL of the reference page, with the endpoint's anchor on it.
     *
     * Built from the named route, so an application that moved its API prefix
     * or put the docs behind a subdomain gets the address it actually serves.
     */
    public static function url(string $version, string $controller, string $action, string $httpMethod): string
    {
        $anchor = static::anchor($version, $controller, $action, $httpMethod);

        return route('api-doc') . '#' . $anchor;
    }

    /**
     * The endpoint's path as the spec spells it — the URI pattern filled in,
     * without the API prefix, which the spec carries in `servers`.
     */
    public static function path(string $version, string $controller, string $action): string
    {
        $pattern = ApiModule::getApiUriPattern();

        $filled = str_replace(
            ['{version}', '{controller}', '{action}'],
            [$version, $controller, $action],
            $pattern
        );

        return '/' . ltrim($filled, '/');
    }
}
