<?php

namespace Database\Factories;

use App\Domain\Awards\Models\Award;
use App\Domain\Organizations\TenantContext;
use App\Domain\WorkOrders\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkOrder> */
class WorkOrderFactory extends Factory
{
    protected $model = WorkOrder::class;

    public function definition(): array
    {
        return [
            'organization_id' => fn () => app(TenantContext::class)->id(),
            'award_id' => Award::factory(),
            'number' => 'DEMO-'.fake()->unique()->numerify('##########'),
            'status' => 'issued',
        ];
    }
}
