<?php

namespace Database\Factories;

use App\Domain\Integrations\Models\AuditLog;
use App\Domain\Organizations\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AuditLog> */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'organization_id' => fn () => app(TenantContext::class)->id(),
            'action' => 'demo.created',
            'entity_type' => 'demo',
            'entity_id' => fn () => (string) Str::ulid(),
            'origin' => 'demo',
            'occurred_at' => now(),
        ];
    }
}
