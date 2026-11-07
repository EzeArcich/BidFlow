<?php

namespace App\Domain\Tenders\Models;

use App\Domain\Integrations\Models\InvitationAccessToken;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Shared\Enums\InvitationStatus;
use App\Domain\Shared\Models\TenantModel;
use App\Domain\Suppliers\Models\Supplier;
use App\Domain\Suppliers\Models\SupplierContact;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenderInvitation extends TenantModel
{
    protected $table = 'tender_invitations';

    protected function casts(): array
    {
        return [
            'status' => InvitationStatus::class,
            'expires_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'viewed_at' => 'immutable_datetime',
            'responded_at' => 'immutable_datetime',
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

    public function contact(): BelongsTo
    {
        return $this->belongsTo(SupplierContact::class);
    }

    public function accessTokens(): HasMany
    {
        return $this->hasMany(InvitationAccessToken::class, 'invitation_id');
    }
}
