<?php

namespace Spatie\OpenApiCli\Exceptions;

use RuntimeException;

class AuthenticationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $hint = null,
    ) {
        parent::__construct($message);
    }
}
