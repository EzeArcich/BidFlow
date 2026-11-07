<?php

namespace Database\Factories;

use App\Domain\Integrations\Models\WebhookEndpoint;
use App\Domain\Organizations\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WebhookEndpoint> */
class WebhookEndpointFactory extends Factory
{
    protected $model = WebhookEndpoint::class;

    public function definition(): array
    {
        return [
            'organization_id' => fn () => app(TenantContext::class)->id(),
            'url' => 'https://example.test/bidflow',
            'events' => ['tender.awarded'],
            'secret' => bin2hex(random_bytes(32)),
            'enabled' => false,
        ];
    }
}
