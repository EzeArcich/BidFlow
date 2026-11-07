<?php

namespace App\Domain\Organizations\Models;

use App\Domain\Shared\Enums\MembershipRole;
use App\Domain\Shared\Models\TenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationMembership extends TenantModel
{
    protected $table = 'organization_memberships';

    protected function casts(): array
    {
        return [
            'role' => MembershipRole::class,
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
