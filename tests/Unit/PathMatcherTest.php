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
