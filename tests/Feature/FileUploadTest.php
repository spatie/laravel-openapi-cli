<?php

use Illuminate\Support\Facades\Http;
use Spatie\OpenApiCli\Facades\OpenApiCli;

beforeEach(function () {
    OpenApiCli::clearRegistrations();

    $spec = [
        'openapi' => '3.0.0',
        'info' => ['title' => 'Test API', 'version' => '1.0.0'],
        'servers' => [
            ['url' => 'https://api.example.com'],
        ],
        'paths' => [
            '/upload' => [
                'post' => [
                    'summary' => 'Upload a file',
                    'requestBody' => [
                        'content' => [
                            'multipart/form-data' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'file' => ['type' => 'string', 'format' => 'binary'],
                                        'name' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/documents' => [
                'post' => [
                    'summary' => 'Upload multiple documents',
                ],
            ],
        ],
    ];

    $this->specPath = sys_get_temp_dir().'/file-upload-test-'.uniqid().'.json';
    file_put_contents($this->specPath, json_encode($spec));

    // Create test files
    $this->testFile1 = sys_get_temp_dir().'/test-file-1-'.uniqid().'.txt';
    $this->testFile2 = sys_get_temp_dir().'/test-file-2-'.uniqid().'.txt';
    file_put_contents($this->testFile1, 'Test file content 1');
    file_put_contents($this->testFile2, 'Test file content 2');

    Http::fake([
        'api.example.com/*' => Http::response(['success' => true], 200),
    ]);

    OpenApiCli::register($this->specPath, 'test-api');
});

afterEach(function () {
    if (isset($this->specPath) && file_exists($this->specPath)) {
        unlink($this->specPath);
    }
    if (isset($this->testFile1) && file_exists($this->testFile1)) {
        unlink($this->testFile1);
    }
    if (isset($this->testFile2) && file_exists($this->testFile2)) {
        unlink($this->testFile2);
    }
    \Spatie\OpenApiCli\OpenApiCli::clearRegistrations();
});

it('uploads a file using @ prefix', function () {
    $this->artisan('test-api:post-upload', [
        '--field' => ["file=@{$this->testFile1}"],
    ])->assertSuccessful();

    Http::assertSentCount(1);

    Http::assertSent(function ($request) {
        $body = $request->body();

        return $request->url() === 'https://api.example.com/upload'
            && $request->method() === 'POST'
            && $request->isMultipart()
            && str_contains($body, 'name="file"')
            && str_contains($body, 'Test file content 1');
    });
});

it('uploads a file with regular fields', function () {
    $this->artisan('test-api:post-upload', [
        '--field' => ["file=@{$this->testFile1}", 'name=MyDocument'],
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        $body = $request->body();

        return $request->url() === 'https://api.example.com/upload'
            && $request->method() === 'POST'
            && $request->isMultipart()
            && str_contains($body, 'name="file"')
            && str_contains($body, 'Test file content 1')
            && str_contains($body, 'name="name"')
            && str_contains($body, 'MyDocument');
    });
});

it('uploads multiple files', function () {
    $this->artisan('test-api:post-documents', [
        '--field' => ["document1=@{$this->testFile1}", "document2=@{$this->testFile2}"],
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        $body = $request->body();

        return $request->url() === 'https://api.example.com/documents'
            && $request->method() === 'POST'
            && $request->isMultipart()
            && str_contains($body, 'name="document1"')
            && str_contains($body, 'Test file content 1')
            && str_contains($body, 'name="document2"')
            && str_contains($body, 'Test file content 2');
    });
});

it('uploads multiple files with mixed regular fields', function () {
    $this->artisan('test-api:post-documents', [
        '--field' => [
            "file1=@{$this->testFile1}",
            'title=Important Documents',
            "file2=@{$this->testFile2}",
            'category=reports',
        ],
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        $body = $request->body();

        return $request->url() === 'https://api.example.com/documents'
            && $request->method() === 'POST'
            && $request->isMultipart()
            && str_contains($body, 'name="file1"')
            && str_contains($body, 'Test file content 1')
            && str_contains($body, 'name="file2"')
            && str_contains($body, 'Test file content 2')
            && str_contains($body, 'name="title"')
            && str_contains($body, 'Important Documents')
            && str_contains($body, 'name="category"')
            && str_contains($body, 'reports');
    });
});

it('shows error when file does not exist', function () {
    $nonExistentFile = '/path/to/nonexistent/file.txt';

    $this->artisan('test-api:post-upload', [
        '--field' => ["file=@{$nonExistentFile}"],
    ])
        ->assertFailed()
        ->expectsOutput("File not found: {$nonExistentFile}");

    Http::assertNothingSent();
});

it('shows error when file is not readable', function () {
    $unreadableFile = sys_get_temp_dir().'/unreadable-'.uniqid().'.txt';
    file_put_contents($unreadableFile, 'content');
    chmod($unreadableFile, 0000);

    try {
        $this->artisan('test-api:post-upload', [
            '--field' => ["file=@{$unreadableFile}"],
        ])
            ->assertFailed()
            ->expectsOutput("File is not readable: {$unreadableFile}");

        Http::assertNothingSent();
    } finally {
        chmod($unreadableFile, 0644);
        unlink($unreadableFile);
    }
})->skipOnWindows();

it('sends file content correctly', function () {
    $customContent = "This is custom file content\nwith multiple lines\nand special chars: !@#$%";
    file_put_contents($this->testFile1, $customContent);

    $this->artisan('test-api:post-upload', [
        '--field' => ["file=@{$this->testFile1}"],
    ])->assertSuccessful();

    Http::assertSent(function ($request) use ($customContent) {
        $body = $request->body();

        return $request->isMultipart()
            && str_contains($body, 'name="file"')
            && str_contains($body, $customContent);
    });
});

it('preserves filename from file path', function () {
    $this->artisan('test-api:post-upload', [
        '--field' => ["file=@{$this->testFile1}"],
    ])->assertSuccessful();

    Http::assertSent(function ($request) {
        $filename = basename($this->testFile1);
        $body = $request->body();

        return $request->isMultipart()
            && str_contains($body, 'name="file"')
            && str_contains($body, 'filename="'.$filename.'"');
    });
});
