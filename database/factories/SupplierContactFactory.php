<?php

namespace Database\Factories;

use App\Domain\Suppliers\Models\Supplier;
use App\Domain\Suppliers\Models\SupplierContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupplierContact> */
class SupplierContactFactory extends Factory
{
    protected $model = SupplierContact::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->userName().'@example.test',
        ];
    }
}
