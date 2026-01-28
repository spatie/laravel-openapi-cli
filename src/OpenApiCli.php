<?php

namespace Spatie\OpenApiCli;

class OpenApiCli
{
    /** @var array<CommandConfiguration> */
    protected static array $registrations = [];

    public static function register(string $specPath, string $signature): CommandConfiguration
    {
        $config = new CommandConfiguration($specPath, $signature);

        static::$registrations[] = $config;

        return $config;
    }

    /**
     * @return array<CommandConfiguration>
     */
    public static function getRegistrations(): array
    {
        return static::$registrations;
    }

    public static function clearRegistrations(): void
    {
        static::$registrations = [];
    }
}
