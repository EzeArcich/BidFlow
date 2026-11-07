<?php

namespace Database\Factories;

use App\Domain\Integrations\Models\OutboxEvent;
use App\Domain\Integrations\Models\WebhookDelivery;
use App\Domain\Integrations\Models\WebhookEndpoint;
use App\Domain\Organizations\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WebhookDelivery> */
class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    public function definition(): array
    {
        return [
            'organization_id' => fn () => app(TenantContext::class)->id(),
            'webhook_endpoint_id' => WebhookEndpoint::factory(),
            'outbox_event_id' => OutboxEvent::factory(),
            'attempts' => 0,
        ];
    }
}
