<?php

namespace App\Domain\WorkOrders\Models;

use App\Domain\Awards\Models\AwardLine;
use App\Domain\Shared\Models\TenantModel;
use App\Domain\Suppliers\Models\Supplier;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderLine extends TenantModel
{
    protected $table = 'work_order_lines';

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'subtotal' => 'decimal:2', 'snapshot' => 'array'];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function awardLine(): BelongsTo
    {
        return $this->belongsTo(AwardLine::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
