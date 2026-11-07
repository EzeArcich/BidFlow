<?php

namespace App\Domain\Suppliers\Queries;

use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\TenantContext;
use App\Domain\Suppliers\Models\OrganizationSupplier;
use App\Domain\Suppliers\Models\SupplierUser;
use App\Domain\Tenders\Models\TenderInvitation;

class PortalAccess
{
    public function invitation(SupplierUser $user, string $id, bool $lock = false): TenderInvitation
    {
        abort_unless(Organization::whereKey(app(TenantContext::class)->id())->where('status', 'active')->exists(), 403);
        abort_unless($user->active && $user->supplier()->where('status', 'active')->exists(), 403);
        abort_unless(OrganizationSupplier::where('supplier_id', $user->supplier_id)->where('status', 'active')->exists(), 403);

        return TenderInvitation::where('status', '!=', 'revoked')->whereHas('tender', fn ($q) => $q->where('status', '!=', 'draft'))->where('supplier_id', $user->supplier_id)->whereKey($id)->when($lock, fn ($q) => $q->lockForUpdate())->firstOrFail();
    }
}
