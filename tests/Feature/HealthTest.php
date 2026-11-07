<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_and_initial_blade_page(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertExactJson(['status' => 'ok', 'service' => 'bidflow']);
        $this->get('/up')->assertOk();
        $this->get('/')->assertOk()->assertSee('Bidflow');
        $this->getJson('/api/v1/tenders')->assertUnauthorized();
    }
}
