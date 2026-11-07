<?php

namespace Database\Factories;

use App\Domain\Suppliers\Models\Supplier;
use App\Domain\Suppliers\Models\SupplierLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupplierLocation> */
class SupplierLocationFactory extends Factory
{
    protected $model = SupplierLocation::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'address' => 'DEMO Calle 123',
            'city' => 'Zárate',
            'province' => 'Buenos Aires',
            'country' => 'AR',
        ];
    }
}
