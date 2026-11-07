<?php

namespace App\Domain\Bids\Models;

use App\Domain\Awards\Models\Award;
use App\Domain\Awards\Models\AwardLine;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Shared\Enums\BidStatus;
use App\Domain\Shared\Models\TenantModel;
use App\Domain\Suppliers\Models\Supplier;
use App\Domain\Tenders\Models\Tender;
use App\Domain\Tenders\Models\TenderInvitation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Bid extends TenantModel
{
    protected $table = 'bids';

    protected function casts(): array
    {
        return [
            'status' => BidStatus::class,
            'total_amount' => 'decimal:2',
            'submitted_at' => 'immutable_datetime', 'submitted_snapshot' => 'array',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(TenderInvitation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BidItem::class, 'bid_id');
    }

    public function award(): HasOne
    {
        return $this->hasOne(Award::class);
    }

    public function awardLines(): HasMany
    {
        return $this->hasMany(AwardLine::class);
    }
}
