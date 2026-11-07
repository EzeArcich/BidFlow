<?php

namespace App\Domain\Awards\Models;

use App\Domain\Bids\Models\Bid;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Shared\Models\TenantModel;
use App\Domain\Tenders\Models\Tender;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Award extends TenantModel
{
    protected $table = 'awards';

    protected function casts(): array
    {
        return [
            'snapshot' => 'array', 'legacy' => 'boolean', 'total_amount' => 'decimal:2',
            'awarded_at' => 'immutable_datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function bid(): BelongsTo
    {
        return $this->belongsTo(Bid::class);
    }

    public function awarder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }

    public function workOrder(): HasOne
    {
        return $this->hasOne(WorkOrder::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AwardLine::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }
}
