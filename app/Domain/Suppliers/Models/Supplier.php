<?php

namespace App\Domain\Suppliers\Models;

use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\TenantContext;
use App\Domain\Shared\Enums\SupplierType;
use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends DomainModel
{
    public function scopeVisibleToCurrentOrganization(Builder $query): void
    {
        $organizationId = app(TenantContext::class)->id();
        $query->whereHas('organizationLinks', fn ($links) => $links->where('organization_id', $organizationId));
    }

    protected $table = 'suppliers';

    protected function casts(): array
    {
        return [
            'type' => SupplierType::class,
            'is_demo' => 'boolean',
        ];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class, 'supplier_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(SupplierLocation::class, 'supplier_id');
    }

    public function capabilities(): HasMany
    {
        return $this->hasMany(SupplierCapability::class, 'supplier_id');
    }

    public function organizationLinks(): HasMany
    {
        return $this->hasMany(OrganizationSupplier::class, 'supplier_id');
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_suppliers')->withPivot('status');
    }
}
