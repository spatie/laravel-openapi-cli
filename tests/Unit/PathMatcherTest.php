<?php

use Spatie\OpenApiCli\PathMatcher;

it('converts simple path to regex', function () {
    $matcher = new PathMatcher;
    $regex = $matcher->convertToRegex('/projects');

    expect($regex)->toBe('~^/projects/?$~');
});

it('converts path with single parameter to regex', function () {
    $matcher = new PathMatcher;
    $regex = $matcher->convertToRegex('/projects/{id}');

    expect($regex)->toBe('~^/projects/(?P<id>[^/]+)/?$~');
});

it('converts path with multiple parameters to regex', function () {
    $matcher = new PathMatcher;
    $regex = $matcher->convertToRegex('/projects/{projectId}/errors/{errorId}');

    expect($regex)->toBe('~^/projects/(?P<projectId>[^/]+)/errors/(?P<errorId>[^/]+)/?$~');
});

it('handles path with underscores in parameter names', function () {
    $matcher = new PathMatcher;
    $regex = $matcher->convertToRegex('/teams/{team_id}');

    expect($regex)->toBe('~^/teams/(?P<team_id>[^/]+)/?$~');
});

it('handles path with multiple segments and parameters', function () {
    $matcher = new PathMatcher;
    $regex = $matcher->convertToRegex('/teams/{team_id}/users/{user_id}');

    expect($regex)->toBe('~^/teams/(?P<team_id>[^/]+)/users/(?P<user_id>[^/]+)/?$~');
});

it('escapes special regex characters in static segments', function () {
    $matcher = new PathMatcher;
    $regex = $matcher->convertToRegex('/projects/{id}/error-count');

    expect($regex)->toBe('~^/projects/(?P<id>[^/]+)/error\-count/?$~');
});

it('handles paths without leading slash', function () {
    $matcher = new PathMatcher;
    $regex = $matcher->convertToRegex('projects');

    expect($regex)->toBe('~^projects/?$~');
});

it('matches simple paths correctly', function () {
    $matcher = new PathMatcher;
    $regex = $matcher->convertToRegex('/projects');

    expect(preg_match($regex, '/projects'))->toBe(1);
    expect(preg_match($regex, '/projects/'))->toBe(1);
});

it('matches paths with parameters correctly', function () {
    $matcher = new PathMatcher;
    $regex = $matcher->convertToRegex('/projects/{id}');

    expect(preg_match($regex, '/projects/123'))->toBe(1);
    expect(preg_match($regex, '/projects/abc-def'))->toBe(1);
    expect(preg_match($regex, '/projects/123/'))->toBe(1);
});

it('extracts named parameters from matches', function () {
    $matcher = new PathMatcher;
    $regex = $matcher->convertToRegex('/projects/{id}');

    preg_match($regex, '/projects/123', $matches);

    expect($matches['id'])->toBe('123');
});

it('extracts multiple named parameters from matches', function () {
    $matcher = new PathMatcher;
    $regex = $matcher->convertToRegex('/projects/{projectId}/errors/{errorId}');

    preg_match($regex, '/projects/456/errors/789', $matches);

    expect($matches['projectId'])->toBe('456');
    expect($matches['errorId'])->toBe('789');
});

it('does not match paths with extra segments', function () {
    $matcher = new PathMatcher;
    $regex = $matcher->convertToRegex('/projects');

    expect(preg_match($regex, '/projects/123'))->toBe(0);
});

it('does not match paths with missing segments', function () {
    $matcher = new PathMatcher;
    $regex = $matcher->convertToRegex('/projects/{id}/errors');

    expect(preg_match($regex, '/projects/123'))->toBe(0);
});

it('parameter values cannot contain slashes', function () {
    $matcher = new PathMatcher;
    $regex = $matcher->convertToRegex('/projects/{id}');

    // This should not match because parameter values cannot contain /
    expect(preg_match($regex, '/projects/123/extra'))->toBe(0);
});

// Tests for matchPath method
it('matches simple path without parameters', function () {
    $matcher = new PathMatcher;
    $specPaths = [
        '/projects' => ['get' => [], 'post' => []],
    ];

    $matches = $matcher->matchPath('projects', $specPaths);

    expect($matches)->toHaveCount(1);
    expect($matches[0]['path'])->toBe('/projects');
    expect($matches[0]['parameters'])->toBe([]);
    expect($matches[0]['methods'])->toBe(['GET', 'POST']);
    expect($matches[0]['isExact'])->toBeTrue();
});

it('matches path with leading slash', function () {
    $matcher = new PathMatcher;
    $specPaths = [
        '/projects' => ['get' => []],
    ];

    $matches = $matcher->matchPath('/projects', $specPaths);

    expect($matches)->toHaveCount(1);
    expect($matches[0]['path'])->toBe('/projects');
});

it('matches path with trailing slash', function () {
    $matcher = new PathMatcher;
    $specPaths = [
        '/projects' => ['get' => []],
    ];

    $matches = $matcher->matchPath('projects/', $specPaths);

    expect($matches)->toHaveCount(1);
    expect($matches[0]['path'])->toBe('/projects');
});

it('matches path with single parameter', function () {
    $matcher = new PathMatcher;
    $specPaths = [
        '/projects/{id}' => ['get' => [], 'put' => [], 'delete' => []],
    ];

    $matches = $matcher->matchPath('projects/123', $specPaths);

    expect($matches)->toHaveCount(1);
    expect($matches[0]['path'])->toBe('/projects/{id}');
    expect($matches[0]['parameters'])->toBe(['id' => '123']);
    expect($matches[0]['methods'])->toBe(['GET', 'PUT', 'DELETE']);
    expect($matches[0]['isExact'])->toBeFalse();
});

it('matches path with multiple parameters', function () {
    $matcher = new PathMatcher;
    $specPaths = [
        '/projects/{projectId}/errors/{errorId}' => ['get' => [], 'patch' => []],
    ];

    $matches = $matcher->matchPath('projects/456/errors/789', $specPaths);

    expect($matches)->toHaveCount(1);
    expect($matches[0]['path'])->toBe('/projects/{projectId}/errors/{errorId}');
    expect($matches[0]['parameters'])->toBe(['projectId' => '456', 'errorId' => '789']);
    expect($matches[0]['methods'])->toBe(['GET', 'PATCH']);
});

it('returns empty array when no paths match', function () {
    $matcher = new PathMatcher;
    $specPaths = [
        '/projects' => ['get' => []],
        '/teams' => ['get' => []],
    ];

    $matches = $matcher->matchPath('users', $specPaths);

    expect($matches)->toBe([]);
});

it('returns HTTP methods for matched path', function () {
    $matcher = new PathMatcher;
    $specPaths = [
        '/projects' => ['get' => [], 'post' => [], 'put' => []],
    ];

    $matches = $matcher->matchPath('projects', $specPaths);

    expect($matches[0]['methods'])->toBe(['GET', 'POST', 'PUT']);
});

it('prioritizes exact matches over parameterized matches', function () {
    $matcher = new PathMatcher;
    $specPaths = [
        '/projects/{id}' => ['get' => []],
        '/projects/active' => ['get' => []],
    ];

    $matches = $matcher->matchPath('projects/active', $specPaths);

    expect($matches)->toHaveCount(2);
    expect($matches[0]['path'])->toBe('/projects/active');
    expect($matches[0]['isExact'])->toBeTrue();
    expect($matches[1]['path'])->toBe('/projects/{id}');
    expect($matches[1]['isExact'])->toBeFalse();
});

it('returns all matching paths when multiple paths match', function () {
    $matcher = new PathMatcher;
    $specPaths = [
        '/projects/{id}' => ['get' => []],
        '/projects/{projectId}' => ['post' => []],
    ];

    // Both patterns match the same input (different parameter names)
    $matches = $matcher->matchPath('projects/123', $specPaths);

    expect($matches)->toHaveCount(2);
    expect($matches[0]['path'])->toBe('/projects/{id}');
    expect($matches[1]['path'])->toBe('/projects/{projectId}');
});

it('handles paths with both leading and trailing slashes', function () {
    $matcher = new PathMatcher;
    $specPaths = [
        '/projects' => ['get' => []],
    ];

    $matches = $matcher->matchPath('/projects/', $specPaths);

    expect($matches)->toHaveCount(1);
    expect($matches[0]['path'])->toBe('/projects');
});

it('extracts parameters with special characters', function () {
    $matcher = new PathMatcher;
    $specPaths = [
        '/projects/{id}' => ['get' => []],
    ];

    $matches = $matcher->matchPath('projects/abc-def-123', $specPaths);

    expect($matches)->toHaveCount(1);
    expect($matches[0]['parameters'])->toBe(['id' => 'abc-def-123']);
});

it('matchPath does not match paths with extra segments', function () {
    $matcher = new PathMatcher;
    $specPaths = [
        '/projects' => ['get' => []],
    ];

    $matches = $matcher->matchPath('projects/123', $specPaths);

    expect($matches)->toBe([]);
});

it('matchPath does not match paths with missing segments', function () {
    $matcher = new PathMatcher;
    $specPaths = [
        '/projects/{id}/errors' => ['get' => []],
    ];

    $matches = $matcher->matchPath('projects/123', $specPaths);

    expect($matches)->toBe([]);
});
