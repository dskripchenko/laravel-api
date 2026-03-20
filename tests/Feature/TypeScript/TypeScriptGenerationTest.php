<?php

declare(strict_types=1);

use Dskripchenko\LaravelApi\Services\OpenApiTypeScriptGenerator;
use Tests\Fixtures\OpenApi\ExtendedApi;

it('generates types from ExtendedApi spec', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $generator = new OpenApiTypeScriptGenerator();
    $result = $generator->generate($config);

    // Schema interfaces
    expect($result)->toContain('export interface UserResponse {');
    expect($result)->toContain('export interface Error {');
    expect($result)->toContain('export interface OrderCreateRequest {');
    expect($result)->toContain('export interface User {');

    // Operation input/output types (operationId = {controller}_{action})
    expect($result)->toContain('export interface ExtendedHeaderActionInput {');
    expect($result)->toContain('export interface ExtendedHeaderActionOutput {');
    expect($result)->toContain('export interface ExtendedFormatActionOutput {');
    expect($result)->toContain('export interface ExtendedEnumActionOutput {');
});

it('generates correct optional output fields', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $generator = new OpenApiTypeScriptGenerator();
    $result = $generator->generate($config);

    // optionalOutputAction has: id (required), name (required), email (optional), phone (optional)
    expect($result)->toContain('export interface ExtendedOptionalOutputActionOutput {');
    expect($result)->toMatch('/\bid: number;\s/');
    expect($result)->toMatch('/\bname: string;\s/');
    expect($result)->toContain('email?: string;');
    expect($result)->toContain('phone?: string;');
});

it('generates $ref input type for model ref action', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $generator = new OpenApiTypeScriptGenerator();
    $result = $generator->generate($config);

    expect($result)->toContain('export type ExtendedModelRefActionInput = OrderCreateRequest;');
});

it('generates enum union types in output', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $generator = new OpenApiTypeScriptGenerator();
    $result = $generator->generate($config);

    expect($result)->toContain("'active' | 'blocked' | 'pending'");
});

it('generates valid TypeScript syntax', function () {
    $config = ExtendedApi::getOpenApiConfig('v1');
    $generator = new OpenApiTypeScriptGenerator();
    $result = $generator->generate($config);

    // No PHP artifacts
    expect($result)->not->toContain('<?php');
    expect($result)->not->toContain('Array');

    // Has proper structure
    expect($result)->toContain('export interface');
    expect($result)->not->toContain('any');
});
