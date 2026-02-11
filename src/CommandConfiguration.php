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

    protected ?int $cacheTtl = null;

    protected bool $noCache = false;

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

    public function cacheTtl(int $seconds): self
    {
        $this->cacheTtl = $seconds;

        return $this;
    }

    public function getCacheTtl(): ?int
    {
        return $this->cacheTtl;
    }

    public function noCache(): self
    {
        $this->noCache = true;

        return $this;
    }

    public function shouldSkipCache(): bool
    {
        return $this->noCache;
    }
}
