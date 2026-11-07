<?php

namespace Database\Factories;

use App\Domain\Integrations\Models\IdempotencyRecord;
use App\Domain\Organizations\Models\ApiClient;
use App\Domain\Organizations\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IdempotencyRecord> */
class IdempotencyRecordFactory extends Factory
{
    protected $model = IdempotencyRecord::class;

    public function definition(): array
    {
        return [
            'organization_id' => fn () => app(TenantContext::class)->id(),
            'api_client_id' => ApiClient::factory(),
            'operation' => 'tenders.create',
            'key' => fake()->uuid(),
            'request_hash' => hash('sha256', '{}'),
            'status' => 'processing',
            'expires_at' => now()->addDay(),
        ];
    }
}
