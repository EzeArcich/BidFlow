<?php

namespace App\Domain\Organizations;

use Closure;
use LogicException;

final class TenantContext
{
    private ?string $organizationId = null;

    public function id(): string
    {
        return $this->organizationId ?? throw new LogicException('Organization context is required.');
    }

    public function run(string $organizationId, Closure $callback): mixed
    {
        $previous = $this->organizationId;
        $this->organizationId = $organizationId;

        try {
            return $callback();
        } finally {
            $this->organizationId = $previous;
        }
    }
}
