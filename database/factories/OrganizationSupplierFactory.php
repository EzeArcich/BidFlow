<?php

namespace Database\Factories;

use App\Domain\Organizations\TenantContext;
use App\Domain\Suppliers\Models\OrganizationSupplier;
use App\Domain\Suppliers\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrganizationSupplier> */
class OrganizationSupplierFactory extends Factory
{
    protected $model = OrganizationSupplier::class;

    public function definition(): array
    {
        return [
            'organization_id' => fn () => app(TenantContext::class)->id(),
            'supplier_id' => Supplier::factory(),
            'status' => 'active',
        ];
    }
}
