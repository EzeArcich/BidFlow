<?php

namespace App\Domain\Tenders\Models;

use App\Domain\Awards\Models\Award;
use App\Domain\Bids\Models\Bid;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Shared\Enums\TenderStatus;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Shared\Models\TenantModel;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tender extends TenantModel
{
    protected $table = 'tenders';

    protected function casts(): array
    {
        return [
            'type' => TenderType::class,
            'status' => TenderStatus::class,
            'deadline_at' => 'immutable_datetime',
            'conditions' => 'array',
            'is_demo' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TenderItem::class, 'tender_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(TenderInvitation::class, 'tender_id');
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class, 'tender_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TenderAttachment::class, 'tender_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(TenderStatusHistory::class, 'tender_id');
    }

    public function repairScope(): HasOne
    {
        return $this->hasOne(RepairScope::class);
    }

    public function award(): HasOne
    {
        return $this->hasOne(Award::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(Award::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }
}
