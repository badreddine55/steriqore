<?php

namespace App\Support\Api;

use RuntimeException;
use Throwable;

/**
 * Thrown for any business-rule failure that needs the API's stable machine
 * `code` (mobile branches on the code, never the message — see CLAUDE.md's
 * API conventions). Framework exceptions (validation, auth, 404s, ...) are
 * mapped to the same envelope separately in ApiExceptionRenderer.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 409,
        public readonly array $details = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
