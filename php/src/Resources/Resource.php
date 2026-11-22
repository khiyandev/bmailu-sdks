<?php

declare(strict_types=1);

namespace Bmailu\Resources;

use Bmailu\Bmailu;

abstract class Resource
{
    public function __construct(protected Bmailu $client)
    {
    }

    /** One URL path segment, fully encoded. */
    protected function seg(string|int $value): string
    {
        return rawurlencode((string) $value);
    }

    /** An empty PHP array would be sent as [], the API wants an object. */
    protected function obj(array $params): array|\stdClass
    {
        return $params === [] ? new \stdClass() : $params;
    }

    /** @return array<string, string> */
    protected function idem(?string $key): array
    {
        return ($key === null || $key === '') ? [] : ['Idempotency-Key' => $key];
    }
}
