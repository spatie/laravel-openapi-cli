<?php

namespace Spatie\OpenApiCli;

class CommandConfiguration
{
    protected ?string $baseUrl = null;

    protected ?string $bearerToken = null;

    protected ?string $apiKeyHeader = null;

    protected ?string $apiKeyValue = null;

    protected ?string $basicUsername = null;

    protected ?string $basicPassword = null;

    /** @var callable|null */
    protected $authCallable = null;

    /** @var callable|null */
    protected $onErrorCallable = null;

    /** @var string|callable|null */
    protected $banner = null;

    protected bool $useOperationIds = false;

    protected bool $cacheEnabled = false;

    protected int $cacheTtl = 60;

    protected ?string $cacheStore = null;

    protected string $cachePrefix = 'openapi-cli-spec:';

    protected bool $showHtmlBody = false;

    protected bool $jsonOutput = false;

    protected bool $yamlOutput = false;

    protected bool $followRedirects = false;

    public function __construct(
        protected string $specPath,
        protected string $namespace = ''
    ) {}

    public function getSpecPath(): string
    {
        return $this->specPath;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function hasNamespace(): bool
    {
        return $this->namespace !== '';
    }

    public function baseUrl(string $url): self
    {
        $this->baseUrl = $url;

        return $this;
    }

    public function getBaseUrl(): ?string
    {
        return $this->baseUrl;
    }

    public function bearer(string $token): self
    {
        $this->bearerToken = $token;

        return $this;
    }

    public function getBearerToken(): ?string
    {
        return $this->bearerToken;
    }

    public function apiKey(string $headerName, string $value): self
    {
        $this->apiKeyHeader = $headerName;
        $this->apiKeyValue = $value;

        return $this;
    }

    public function getApiKeyHeader(): ?string
    {
        return $this->apiKeyHeader;
    }

    public function getApiKeyValue(): ?string
    {
        return $this->apiKeyValue;
    }

    public function basic(string $username, string $password): self
    {
        $this->basicUsername = $username;
        $this->basicPassword = $password;

        return $this;
    }

    public function getBasicUsername(): ?string
    {
        return $this->basicUsername;
    }

    public function getBasicPassword(): ?string
    {
        return $this->basicPassword;
    }

    public function auth(callable $callable): self
    {
        $this->authCallable = $callable;

        return $this;
    }

    public function getAuthCallable(): ?callable
    {
        return $this->authCallable;
    }

    public function onError(callable $callable): self
    {
        $this->onErrorCallable = $callable;

        return $this;
    }

    public function getOnErrorCallable(): ?callable
    {
        return $this->onErrorCallable;
    }

    public function banner(string|callable $banner): self
    {
        $this->banner = $banner;

        return $this;
    }

    public function getBanner(): string|callable|null
    {
        return $this->banner;
    }

    public function useOperationIds(): self
    {
        $this->useOperationIds = true;

        return $this;
    }

    public function shouldUseOperationIds(): bool
    {
        return $this->useOperationIds;
    }

    public function cache(int $ttl = 60, ?string $store = null, string $prefix = 'openapi-cli-spec:'): self
    {
        $this->cacheEnabled = true;
        $this->cacheTtl = $ttl;
        $this->cacheStore = $store;
        $this->cachePrefix = $prefix;

        return $this;
    }

    public function shouldCache(): bool
    {
        return $this->cacheEnabled;
    }

    public function getCacheTtl(): int
    {
        return $this->cacheTtl;
    }

    public function getCacheStore(): ?string
    {
        return $this->cacheStore;
    }

    public function getCachePrefix(): string
    {
        return $this->cachePrefix;
    }

    public function showHtmlBody(): self
    {
        $this->showHtmlBody = true;

        return $this;
    }

    public function shouldShowHtmlBody(): bool
    {
        return $this->showHtmlBody;
    }

    public function jsonOutput(): self
    {
        $this->jsonOutput = true;

        return $this;
    }

    public function shouldOutputJson(): bool
    {
        return $this->jsonOutput;
    }

    public function yamlOutput(): self
    {
        $this->yamlOutput = true;

        return $this;
    }

    public function shouldOutputYaml(): bool
    {
        return $this->yamlOutput;
    }

    public function followRedirects(): self
    {
        $this->followRedirects = true;

        return $this;
    }

    public function shouldFollowRedirects(): bool
    {
        return $this->followRedirects;
    }
}
