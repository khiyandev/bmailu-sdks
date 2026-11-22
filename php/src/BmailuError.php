<?php

declare(strict_types=1);

namespace Bmailu;

/** An error answer from the API (or a network failure, status 0). */
class BmailuError extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly mixed $body = null,
    ) {
        parent::__construct($message, $status);
    }
}
