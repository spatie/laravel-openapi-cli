<?php

use Spatie\OpenApiCli\RefResolver;

it('resolves a simple reference', function () {
    $document = [
        'components' => [
            'schemas' => [
                'User' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ];

    $resolver = new RefResolver($document);

    $data = ['$ref' => '#/components/schemas/User'];
    $resolved = $resolver->resolve($data);

    expect($resolved)->toBe([
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'name' => ['type' => 'string'],
        ],
    ]);
});

it('resolves nested references', function () {
    $document = [
        'components' => [
            'schemas' => [
                'User' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'address' => ['$ref' => '#/components/schemas/Address'],
                    ],
                ],
                'Address' => [
                    'type' => 'object',
                    'properties' => [
                        'street' => ['type' => 'string'],
                        'city' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ];

    $resolver = new RefResolver($document);

    $data = ['$ref' => '#/components/schemas/User'];
    $resolved = $resolver->resolve($data);

    expect($resolved)->toBe([
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'address' => [
                'type' => 'object',
                'properties' => [
                    'street' => ['type' => 'string'],
                    'city' => ['type' => 'string'],
                ],
            ],
        ],
    ]);
});

it('resolves references in arrays', function () {
    $document = [
        'components' => [
            'schemas' => [
                'User' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ];

    $resolver = new RefResolver($document);

    $data = [
        'items' => [
            '$ref' => '#/components/schemas/User',
        ],
    ];
    $resolved = $resolver->resolve($data);

    expect($resolved)->toBe([
        'items' => [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
            ],
        ],
    ]);
});

it('resolves references in request bodies', function () {
    $document = [
        'components' => [
            'schemas' => [
                'CreateUserRequest' => [
                    'type' => 'object',
                    'required' => ['name', 'email'],
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'email' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ];

    $resolver = new RefResolver($document);

    $data = [
        'requestBody' => [
            'content' => [
                'application/json' => [
                    'schema' => [
                        '$ref' => '#/components/schemas/CreateUserRequest',
                    ],
                ],
            ],
        ],
    ];

    $resolved = $resolver->resolve($data);

    expect($resolved['requestBody']['content']['application/json']['schema'])->toBe([
        'type' => 'object',
        'required' => ['name', 'email'],
        'properties' => [
            'name' => ['type' => 'string'],
            'email' => ['type' => 'string'],
        ],
    ]);
});

it('returns original value when no reference is present', function () {
    $document = [
        'components' => [
            'schemas' => [
                'User' => [
                    'type' => 'object',
                ],
            ],
        ],
    ];

    $resolver = new RefResolver($document);

    $data = [
        'type' => 'string',
        'description' => 'A simple string',
    ];

    $resolved = $resolver->resolve($data);

    expect($resolved)->toBe($data);
});

it('returns primitives unchanged', function () {
    $document = [];
    $resolver = new RefResolver($document);

    expect($resolver->resolve('string'))->toBe('string');
    expect($resolver->resolve(123))->toBe(123);
    expect($resolver->resolve(true))->toBe(true);
    expect($resolver->resolve(null))->toBe(null);
});

it('throws exception on invalid reference pointer', function () {
    $document = [
        'components' => [
            'schemas' => [
                'User' => [
                    'type' => 'object',
                ],
            ],
        ],
    ];

    $resolver = new RefResolver($document);

    $data = ['$ref' => '#/components/schemas/NonExistent'];

    expect(fn () => $resolver->resolve($data))
        ->toThrow(RuntimeException::class, 'Invalid reference pointer: #/components/schemas/NonExistent');
});

it('throws exception on external references', function () {
    $document = [];
    $resolver = new RefResolver($document);

    $data = ['$ref' => 'external.yaml#/components/schemas/User'];

    expect(fn () => $resolver->resolve($data))
        ->toThrow(RuntimeException::class, 'External references are not supported');
});

it('handles deeply nested references', function () {
    $document = [
        'components' => [
            'schemas' => [
                'Level1' => [
                    'type' => 'object',
                    'properties' => [
                        'level2' => ['$ref' => '#/components/schemas/Level2'],
                    ],
                ],
                'Level2' => [
                    'type' => 'object',
                    'properties' => [
                        'level3' => ['$ref' => '#/components/schemas/Level3'],
                    ],
                ],
                'Level3' => [
                    'type' => 'object',
                    'properties' => [
                        'value' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ];

    $resolver = new RefResolver($document);

    $data = ['$ref' => '#/components/schemas/Level1'];
    $resolved = $resolver->resolve($data);

    expect($resolved['properties']['level2']['properties']['level3']['properties']['value'])
        ->toBe(['type' => 'string']);
});

it('handles JSON pointer escaped characters', function () {
    $document = [
        'components' => [
            'schemas' => [
                'User/Admin' => [
                    'type' => 'object',
                    'properties' => [
                        'role~special' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ];

    $resolver = new RefResolver($document);

    // In JSON pointer: ~1 represents / and ~0 represents ~
    $data = ['$ref' => '#/components/schemas/User~1Admin'];
    $resolved = $resolver->resolve($data);

    expect($resolved['properties']['role~special'])->toBe(['type' => 'string']);
});

it('resolves allOf with references', function () {
    $document = [
        'components' => [
            'schemas' => [
                'BaseUser' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                    ],
                ],
                'ExtendedUser' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/BaseUser'],
                        [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $resolver = new RefResolver($document);

    $data = ['$ref' => '#/components/schemas/ExtendedUser'];
    $resolved = $resolver->resolve($data);

    expect($resolved['allOf'][0])->toBe([
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
        ],
    ]);
    expect($resolved['allOf'][1]['properties']['name'])->toBe(['type' => 'string']);
});

it('resolves multiple references in the same structure', function () {
    $document = [
        'components' => [
            'schemas' => [
                'User' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                    ],
                ],
                'Team' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ];

    $resolver = new RefResolver($document);

    $data = [
        'properties' => [
            'owner' => ['$ref' => '#/components/schemas/User'],
            'team' => ['$ref' => '#/components/schemas/Team'],
        ],
    ];

    $resolved = $resolver->resolve($data);

    expect($resolved['properties']['owner'])->toBe([
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
        ],
    ]);
    expect($resolved['properties']['team'])->toBe([
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
        ],
    ]);
});

it('resolves references in array items', function () {
    $document = [
        'components' => [
            'schemas' => [
                'User' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                    ],
                ],
            ],
        ],
    ];

    $resolver = new RefResolver($document);

    $data = [
        'type' => 'array',
        'items' => ['$ref' => '#/components/schemas/User'],
    ];

    $resolved = $resolver->resolve($data);

    expect($resolved['items'])->toBe([
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
        ],
    ]);
});
