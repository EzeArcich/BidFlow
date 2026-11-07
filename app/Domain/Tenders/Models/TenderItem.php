<?php

namespace App\Domain\Tenders\Models;

use App\Domain\Awards\Models\AwardLine;
use App\Domain\Bids\Models\BidItem;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Shared\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenderItem extends TenantModel
{
    protected $table = 'tender_items';

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'attributes' => 'array',
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

    public function bidItems(): HasMany
    {
        return $this->hasMany(BidItem::class, 'tender_item_id');
    }

    public function awardLines(): HasMany
    {
        return $this->hasMany(AwardLine::class);
    }
}
