<?php

namespace Database\Factories;

use App\Domain\Integrations\Models\OutboxEvent;
use App\Domain\Organizations\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<OutboxEvent> */
class OutboxEventFactory extends Factory
{
    protected $model = OutboxEvent::class;

    public function definition(): array
    {
        return [
            'organization_id' => fn () => app(TenantContext::class)->id(),
            'event_type' => 'demo.created',
            'resource_id' => fn () => (string) Str::ulid(),
            'payload' => ['is_demo' => true],
            'occurred_at' => now(),
        ];
    }
}
