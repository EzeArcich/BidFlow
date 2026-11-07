<?php

namespace App\Domain\Integrations\Models;

use App\Domain\Organizations\Models\Organization;
use App\Domain\Shared\Models\TenantModel;
use App\Domain\Tenders\Models\TenderInvitation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvitationAccessToken extends TenantModel
{
    protected $table = 'invitation_access_tokens';

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'last_used_at' => 'immutable_datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(TenderInvitation::class);
    }
}
