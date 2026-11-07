<?php

namespace App\Domain\Awards\Models;

use App\Domain\Bids\Models\Bid;
use App\Domain\Bids\Models\BidItem;
use App\Domain\Shared\Models\TenantModel;
use App\Domain\Suppliers\Models\Supplier;
use App\Domain\Tenders\Models\TenderItem;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AwardLine extends TenantModel
{
    protected $table = 'award_lines';

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'subtotal' => 'decimal:2', 'snapshot' => 'array'];
    }

    public function award(): BelongsTo
    {
        return $this->belongsTo(Award::class);
    }

    public function bid(): BelongsTo
    {
        return $this->belongsTo(Bid::class);
    }

    public function bidItem(): BelongsTo
    {
        return $this->belongsTo(BidItem::class);
    }

    public function tenderItem(): BelongsTo
    {
        return $this->belongsTo(TenderItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
