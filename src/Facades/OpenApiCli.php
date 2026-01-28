<?php

namespace Spatie\OpenApiCli\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Spatie\OpenApiCli\OpenApiCli
 */
class OpenApiCli extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Spatie\OpenApiCli\OpenApiCli::class;
    }
}
