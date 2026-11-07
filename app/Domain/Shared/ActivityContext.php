<?php

namespace App\Domain\Shared;

use Closure;

final class ActivityContext
{
    public string $origin = 'api';

    public function run(string $origin, Closure $callback): mixed
    {
        $previous = $this->origin;
        $this->origin = $origin;
        try {
            return $callback();
        } finally {
            $this->origin = $previous;
        }
    }
}
