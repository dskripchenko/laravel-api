<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Services\OpenApi\DocLink;

/**
 * The anchor format is Scalar's, and these tests are what pins it.
 *
 * It is not ours to change: the page builds the hash, we only have to spell it
 * the same way from the outside. A test naming a real, working URL is the only
 * thing standing between a bundle upgrade and a hundred links that quietly go
 * nowhere.
 */
it('builds the anchor of an endpoint the way the page addresses it', function () {
    // Verbatim from a live reference page:
    // https://.../api/doc#printable-integration-api/tag/template/GET/integration/template/contract
    // — with the document slug now passed explicitly, so it is the version.
    expect(DocLink::anchor('integration', 'template', 'contract', 'get'))
        ->toBe('integration/tag/template/GET/integration/template/contract');
});

it('uppercases the method and keeps the path as the spec spells it', function () {
    expect(DocLink::anchor('v1', 'user', 'list', 'post'))
        ->toBe('v1/tag/user/POST/v1/user/list');
});

it('follows the URI pattern rather than assuming one', function () {
    config()->set('laravel-api.uri_pattern', '{controller}/{action}/{version}');

    expect(DocLink::path('v1', 'user', 'list'))->toBe('/user/list/v1');
});

it('slugifies the way Scalar does', function () {
    // Lowercased, punctuation dropped, separators collapsed.
    expect(DocLink::slug('Printable integration API.'))->toBe('printable-integration-api');
    expect(DocLink::slug('  Order  items '))->toBe('order-items');
    expect(DocLink::slug('print_form'))->toBe('print-form');
    expect(DocLink::slug('--edge--'))->toBe('edge');
});

it('keeps unicode letters instead of collapsing a name into nothing', function () {
    expect(DocLink::slug('Печать документов'))->toBe('печать-документов');
});

it('is idempotent — the property the whole scheme rests on', function () {
    // Scalar slugifies whatever it is handed. A slug that changed on the second
    // pass would address a page that does not exist.
    foreach (['Printable integration API.', 'v1.1', 'print_form', 'Печать'] as $text) {
        $once = DocLink::slug($text);

        expect(DocLink::slug($once))->toBe($once);
    }
});

it('keeps a dotted version readable instead of running it together', function () {
    // Scalar's own rule would drop the dot and leave `v11` — a different
    // version to anyone reading the URL.
    expect(DocLink::documentSlug('v1.1'))->toBe('v1-1');
    expect(DocLink::slug(DocLink::documentSlug('v1.1')))->toBe('v1-1');
});

it('points at the page the application actually serves', function () {
    // Built from the named route rather than from a guess, so an application
    // that moved its API prefix or put the docs on another host gets the
    // address it serves and not the one this package would have chosen.
    $url = DocLink::url('v1', 'user', 'list', 'get');

    expect($url)->toBe(route('api-doc') . '#v1/tag/user/GET/v1/user/list')
        ->and($url)->toContain('/doc#');
});
