<?php

namespace Database\Factories;

use App\Domain\Suppliers\Models\Supplier;
use App\Domain\Suppliers\Models\SupplierUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierUserFactory extends Factory
{
    protected $model = SupplierUser::class;

    public function definition(): array
    {
        return ['supplier_id' => Supplier::factory(), 'name' => fake()->name(), 'email' => fake()->unique()->userName().'@example.test', 'password' => 'supplier-test-password', 'active' => true];
    }
}
