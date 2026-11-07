<?php

namespace App\Domain\Suppliers\Policies;

use App\Domain\Organizations\Policies\TenantRecordPolicy;
use App\Domain\Suppliers\Models\OrganizationSupplier;
use App\Domain\Suppliers\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return app(TenantRecordPolicy::class)->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $record): bool
    {
        return false;
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }

    public function view(User $user, Model $record): bool
    {
        $supplierId = $record instanceof Supplier ? $record->id : $record->supplier_id;

        return $this->viewAny($user) && OrganizationSupplier::where('supplier_id', $supplierId)->exists();
    }
}
