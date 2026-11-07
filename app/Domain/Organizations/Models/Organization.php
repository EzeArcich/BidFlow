<?php

namespace App\Domain\Organizations\Models;

use App\Domain\Shared\Models\DomainModel;
use App\Domain\Suppliers\Models\OrganizationSupplier;
use App\Domain\Tenders\Models\Tender;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends DomainModel
{
    protected $table = 'organizations';

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class, 'organization_id');
    }

    public function tenders(): HasMany
    {
        return $this->hasMany(Tender::class, 'organization_id');
    }

    public function supplierLinks(): HasMany
    {
        return $this->hasMany(OrganizationSupplier::class, 'organization_id');
    }

    public function apiClients(): HasMany
    {
        return $this->hasMany(ApiClient::class, 'organization_id');
    }
}
