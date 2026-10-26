<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Components\BaseApi;
use Dskripchenko\LaravelApi\Services\Linter\LintIssue;
use Dskripchenko\LaravelApi\Services\Linter\OpenApiLinter;
use Tests\Fixtures\OpenApi\TemplateApi;
use Tests\Fixtures\Versions\v1\Controllers\OpenController;

/** Declares its templates and schemes under the neutral names only. */
class NeutralNamesApi extends BaseApi
{
    public static $useResponseTemplates = true;

    public static function getMethods(): array
    {
        return ['controllers' => ['open' => ['controller' => NeutralNamesController::class, 'actions' => ['ping']]]];
    }

    public static function getSchemaTemplates(): array
    {
        return ['Pong' => ['pong' => 'boolean!']];
    }

    public static function getSecuritySchemes(): array
    {
        return ['Token' => ['type' => 'apiKey', 'name' => 'X-Token', 'in' => 'header']];
    }
}

/** Overrides both names: the neutral one is what the package reads. */
class BothNamesApi extends NeutralNamesApi
{
    public static function getOpenApiTemplates(): array
    {
        return ['Legacy' => ['id' => 'integer']];
    }
}

class NeutralNamesController extends OpenController
{
    /**
     * Ping
     * @output {Pong}
     * @security Token
     */
    public function ping(): \Illuminate\Http\JsonResponse
    {
        return parent::ping();
    }
}

it('answers with the OpenAPI-era hooks when the neutral ones are not overridden', function () {
    expect(TemplateApi::getSchemaTemplates())->toBe(TemplateApi::getOpenApiTemplates());
    expect(TemplateApi::getSecuritySchemes())->toBe(TemplateApi::getOpenApiSecurityDefinitions());
});

it('reads templates and security schemes declared under the neutral names', function () {
    $spec = NeutralNamesApi::getOpenApiConfig('v1');

    expect($spec['components']['schemas'])->toHaveKey('Pong');
    expect($spec['components']['securitySchemes'])->toHaveKey('Token');
    expect($spec['paths']['/v1/open/ping']['post']['responses']['200']['content']['application/json']['schema'])
        ->toBe(['$ref' => '#/components/schemas/Pong']);

    $issues = (new OpenApiLinter())->lintVersionList(['v1' => NeutralNamesApi::class]);
    expect(array_map(static fn (LintIssue $i): string => $i->rule, $issues))->toBe([]);
});

it('takes the neutral name when both are overridden', function () {
    $schemas = BothNamesApi::getOpenApiConfig('v1')['components']['schemas'];

    expect($schemas)->toHaveKey('Pong');
    expect($schemas)->not->toHaveKey('Legacy');
});
