<?php

namespace App\Domain\Bids\Models;

use App\Domain\Awards\Models\AwardLine;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Shared\Models\TenantModel;
use App\Domain\Tenders\Models\TenderItem;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BidItem extends TenantModel
{
    protected $table = 'bid_items';

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function bid(): BelongsTo
    {
        return $this->belongsTo(Bid::class);
    }

    public function tenderItem(): BelongsTo
    {
        return $this->belongsTo(TenderItem::class);
    }

    public function awardLines(): HasMany
    {
        return $this->hasMany(AwardLine::class);
    }
}
