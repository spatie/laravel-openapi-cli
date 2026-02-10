<?php

use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\CommandConfiguration;
use Spatie\OpenApiCli\Facades\OpenApiCli;

beforeEach(function () {
    OpenApiCli::clearRegistrations();

    $this->specContent = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
servers:
  - url: https://api.example.com
paths:
  /projects:
    get:
      summary: List all projects
      responses:
        '200':
          description: Success
YAML;

    $this->specPath = sys_get_temp_dir().'/test-spec-'.uniqid().'.yaml';
    file_put_contents($this->specPath, $this->specContent);
});

afterEach(function () {
    if (file_exists($this->specPath)) {
        unlink($this->specPath);
    }

    \Spatie\OpenApiCli\OpenApiCli::clearRegistrations();
});

it('displays a string banner at the top of list output', function () {
    OpenApiCli::register($this->specPath, 'test-api')
        ->banner('Welcome to Test API');

    $this->artisan('test-api:list')
        ->assertSuccessful()
        ->expectsOutputToContain('Welcome to Test API')
        ->expectsOutputToContain('test-api:get-projects');
});

it('displays a multi-line ASCII art banner', function () {
    $art = <<<'ASCII'
  _____ _
 |_   _| |__   ___
   | | | '_ \ / _ \
   | | | | | |  __/
   |_| |_| |_|\___|
ASCII;

    OpenApiCli::register($this->specPath, 'test-api')
        ->banner($art);

    $this->artisan('test-api:list')
        ->assertSuccessful()
        ->expectsOutputToContain('|_   _| |__   ___');
});

it('executes a callable banner that receives the command instance', function () {
    OpenApiCli::register($this->specPath, 'test-api')
        ->banner(function ($command) {
            $command->info('Custom banner via callable');
        });

    $this->artisan('test-api:list')
        ->assertSuccessful()
        ->expectsOutputToContain('Custom banner via callable');
});

it('does not display a banner when none is configured', function () {
    OpenApiCli::register($this->specPath, 'test-api');

    $this->artisan('test-api:list')
        ->assertSuccessful()
        ->expectsOutputToContain('test-api:get-projects')
        ->doesntExpectOutputToContain('banner');
});

it('does not display the banner in endpoint command output', function () {
    Http::fake([
        'https://api.example.com/*' => Http::response(['data' => 'value'], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api')
        ->baseUrl('https://api.example.com')
        ->banner('This should not appear');

    $this->artisan('test-api:get-projects')
        ->assertSuccessful()
        ->doesntExpectOutputToContain('This should not appear');
});

it('returns self from banner() for method chaining', function () {
    $config = new CommandConfiguration($this->specPath, 'test-api');

    $result = $config->banner('Test');

    expect($result)->toBeInstanceOf(CommandConfiguration::class);
});

it('returns null from getBanner() when not set', function () {
    $config = new CommandConfiguration($this->specPath, 'test-api');

    expect($config->getBanner())->toBeNull();
});
