<?php

namespace Database\Factories;

use App\Domain\Suppliers\Models\Supplier;
use App\Domain\Suppliers\Models\SupplierCapability;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupplierCapability> */
class SupplierCapabilityFactory extends Factory
{
    protected $model = SupplierCapability::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'capability' => 'parts',
        ];
    }
}
