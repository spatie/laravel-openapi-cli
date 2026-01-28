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

    public function __construct(
        protected string $specPath,
        protected string $signature
    ) {}

    public function getSpecPath(): string
    {
        return $this->specPath;
    }

    public function getSignature(): string
    {
        return $this->signature;
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
}
