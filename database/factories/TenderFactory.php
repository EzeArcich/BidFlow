<?php

namespace Database\Factories;

use App\Domain\Organizations\TenantContext;
use App\Domain\Tenders\Models\Tender;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tender> */
class TenderFactory extends Factory
{
    protected $model = Tender::class;

    public function definition(): array
    {
        return [
            'organization_id' => fn () => app(TenantContext::class)->id(),
            'type' => 'parts',
            'status' => 'draft',
            'title' => 'DEMO Solicitud de repuestos',
            'currency' => 'ARS',
            'deadline_at' => now()->addDays(7),
            'created_by' => User::factory(),
            'is_demo' => true,
        ];
    }
}
