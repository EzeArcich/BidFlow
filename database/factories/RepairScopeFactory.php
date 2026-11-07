<?php

namespace Database\Factories;

use App\Domain\Organizations\TenantContext;
use App\Domain\Tenders\Models\RepairScope;
use App\Domain\Tenders\Models\Tender;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RepairScope> */
class RepairScopeFactory extends Factory
{
    protected $model = RepairScope::class;

    public function definition(): array
    {
        return [
            'organization_id' => fn () => app(TenantContext::class)->id(),
            'tender_id' => Tender::factory()->state(['type' => 'repair']),
            'tender_type' => 'repair',
            'vehicle' => ['make' => 'Toyota', 'model' => 'Hilux', 'year' => 2022, 'is_demo' => true],
            'damage_description' => 'DEMO Daños frontales ficticios',
            'location' => ['city' => 'Zárate', 'province' => 'Buenos Aires', 'country' => 'AR'],
        ];
    }
}
