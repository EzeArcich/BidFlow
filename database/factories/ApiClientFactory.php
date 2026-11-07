<?php

namespace Database\Factories;

use App\Domain\Organizations\Models\ApiClient;
use App\Domain\Organizations\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ApiClient> */
class ApiClientFactory extends Factory
{
    protected $model = ApiClient::class;

    public function definition(): array
    {
        return [
            'organization_id' => fn () => app(TenantContext::class)->id(),
            'name' => 'DEMO client',
            'token_hash' => hash('sha256', random_bytes(32)),
            'scopes' => ['tenders:read'],
        ];
    }
}
