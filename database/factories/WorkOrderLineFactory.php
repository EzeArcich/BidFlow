<?php

namespace Database\Factories;

use App\Domain\Awards\Models\AwardLine;
use App\Domain\Organizations\TenantContext;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Domain\WorkOrders\Models\WorkOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkOrderLine> */
class WorkOrderLineFactory extends Factory
{
    protected $model = WorkOrderLine::class;

    public function definition(): array
    {
        return [
            'organization_id' => fn () => app(TenantContext::class)->id(),
            'award_line_id' => AwardLine::factory(),
            'award_id' => fn (array $a) => AwardLine::findOrFail($a['award_line_id'])->award_id,
            'supplier_id' => fn (array $a) => AwardLine::findOrFail($a['award_line_id'])->supplier_id,
            'work_order_id' => fn (array $a) => WorkOrder::factory()->create(['award_id' => $a['award_id'], 'supplier_id' => $a['supplier_id'], 'tender_id' => AwardLine::findOrFail($a['award_line_id'])->tender_id])->id,
            'quantity' => fn (array $a) => AwardLine::findOrFail($a['award_line_id'])->quantity,
            'unit_price' => fn (array $a) => AwardLine::findOrFail($a['award_line_id'])->unit_price,
            'subtotal' => fn (array $a) => AwardLine::findOrFail($a['award_line_id'])->subtotal,
            'currency' => 'ARS', 'snapshot' => ['is_demo' => true],
        ];
    }
}
