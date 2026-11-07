<?php

namespace App\Domain\Organizations\Policies;

use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\OrganizationMembership;
use App\Domain\Organizations\TenantContext;
use App\Domain\Shared\Models\TenantModel;
use App\Models\User;

class TenantRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasRole($user, ['owner', 'admin', 'operator', 'viewer']);
    }

    public function view(User $user, TenantModel $record): bool
    {
        return $record->organization_id === app(TenantContext::class)->id() && $this->viewAny($user);
    }

    // Mutations require domain-specific policies/actions in the next phases.
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, TenantModel $record): bool
    {
        return false;
    }

    public function delete(User $user, TenantModel $record): bool
    {
        return false;
    }

    public function hasRole(User $user, array $roles): bool
    {
        return Organization::whereKey(app(TenantContext::class)->id())->where('status', 'active')->exists()
            && OrganizationMembership::where('user_id', $user->id)->whereIn('role', $roles)->exists();
    }
}
