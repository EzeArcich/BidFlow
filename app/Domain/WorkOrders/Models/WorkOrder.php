<?php

namespace App\Domain\WorkOrders\Models;

use App\Domain\Awards\Models\Award;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Shared\Enums\WorkOrderStatus;
use App\Domain\Shared\Models\TenantModel;
use App\Domain\Suppliers\Models\Supplier;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrder extends TenantModel
{
    protected $table = 'work_orders';

    protected function casts(): array
    {
        return [
            'status' => WorkOrderStatus::class,
            'expected_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function award(): BelongsTo
    {
        return $this->belongsTo(Award::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(WorkOrderLine::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
