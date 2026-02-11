<?php

use Spatie\OpenApiCli\CommandNameGenerator;

// fromPath tests

it('generates command name from simple path', function () {
    expect(CommandNameGenerator::fromPath('GET', '/me'))->toBe('get-me');
});

it('generates command name from path with multiple segments', function () {
    expect(CommandNameGenerator::fromPath('GET', '/projects'))->toBe('get-projects');
});

it('generates command name with POST method', function () {
    expect(CommandNameGenerator::fromPath('POST', '/projects'))->toBe('post-projects');
});

it('strips path parameters from command name', function () {
    expect(CommandNameGenerator::fromPath('GET', '/projects/{project_id}/errors'))->toBe('get-projects-errors');
});

it('strips multiple path parameters', function () {
    expect(CommandNameGenerator::fromPath('DELETE', '/teams/{team_id}/users/{user_id}'))->toBe('delete-teams-users');
});

it('handles path parameter at end', function () {
    expect(CommandNameGenerator::fromPath('POST', '/errors/{error_id}/resolve'))->toBe('post-errors-resolve');
});

it('handles path with dashes in segments', function () {
    expect(CommandNameGenerator::fromPath('GET', '/projects/{project_id}/error-count'))->toBe('get-projects-error-count');
});

it('converts underscores to dashes in path segments', function () {
    expect(CommandNameGenerator::fromPath('GET', '/error_occurrences/{id}'))->toBe('get-error-occurrences');
});

it('handles single-segment parameterized path', function () {
    expect(CommandNameGenerator::fromPath('GET', '/error-occurrences/{occurrence_id}'))->toBe('get-error-occurrences');
});

// fromOperationId tests

it('converts simple camelCase operationId', function () {
    expect(CommandNameGenerator::fromOperationId('getProjects'))->toBe('get-projects');
});

it('converts operationId with multiple words', function () {
    expect(CommandNameGenerator::fromOperationId('getProjectErrors'))->toBe('get-project-errors');
});

it('converts operationId with acronyms', function () {
    expect(CommandNameGenerator::fromOperationId('getHTTPErrors'))->toBe('get-http-errors');
});

it('handles already kebab-case operationId', function () {
    expect(CommandNameGenerator::fromOperationId('get-projects'))->toBe('get-projects');
});

it('converts snake_case operationId', function () {
    expect(CommandNameGenerator::fromOperationId('get_projects'))->toBe('get-projects');
});

// parameterToOptionName tests

it('converts snake_case parameter to kebab-case', function () {
    expect(CommandNameGenerator::parameterToOptionName('project_id'))->toBe('project-id');
});

it('converts camelCase parameter to kebab-case', function () {
    expect(CommandNameGenerator::parameterToOptionName('projectId'))->toBe('project-id');
});

it('handles already kebab-case parameter', function () {
    expect(CommandNameGenerator::parameterToOptionName('project-id'))->toBe('project-id');
});

it('handles single word parameter', function () {
    expect(CommandNameGenerator::parameterToOptionName('id'))->toBe('id');
});

// queryParamToOptionName tests

it('converts bracket notation to kebab-case', function () {
    expect(CommandNameGenerator::queryParamToOptionName('filter[id]'))->toBe('filter-id');
});

it('converts page bracket notation', function () {
    expect(CommandNameGenerator::queryParamToOptionName('page[number]'))->toBe('page-number');
});

it('converts nested bracket notation', function () {
    expect(CommandNameGenerator::queryParamToOptionName('page[size]'))->toBe('page-size');
});

it('handles simple query parameter name', function () {
    expect(CommandNameGenerator::queryParamToOptionName('sort'))->toBe('sort');
});

it('passes through include query parameter without renaming', function () {
    expect(CommandNameGenerator::queryParamToOptionName('include'))->toBe('include');
});

it('handles snake_case query parameter', function () {
    expect(CommandNameGenerator::queryParamToOptionName('start_date'))->toBe('start-date');
});

it('handles camelCase query parameter', function () {
    expect(CommandNameGenerator::queryParamToOptionName('startDate'))->toBe('start-date');
});

it('converts filter with snake_case name', function () {
    expect(CommandNameGenerator::queryParamToOptionName('filter[exception_message]'))->toBe('filter-exception-message');
});

// fromPathDisambiguated tests

it('includes trailing path parameter in disambiguated name', function () {
    expect(CommandNameGenerator::fromPathDisambiguated('GET', '/projects/{id}'))->toBe('get-projects-id');
});

it('does not change name when no trailing parameter in disambiguated mode', function () {
    expect(CommandNameGenerator::fromPathDisambiguated('GET', '/projects'))->toBe('get-projects');
});

it('strips middle parameters but keeps trailing in disambiguated mode', function () {
    expect(CommandNameGenerator::fromPathDisambiguated('GET', '/projects/{project_id}/errors'))->toBe('get-projects-errors');
});

it('converts trailing parameter name to kebab-case in disambiguated mode', function () {
    expect(CommandNameGenerator::fromPathDisambiguated('DELETE', '/teams/{team_id}/users/{user_id}'))->toBe('delete-teams-users-user-id');
});

it('handles single-segment parameterized path in disambiguated mode', function () {
    expect(CommandNameGenerator::fromPathDisambiguated('GET', '/error-occurrences/{occurrence_id}'))->toBe('get-error-occurrences-occurrence-id');
});
