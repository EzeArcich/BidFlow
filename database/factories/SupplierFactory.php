<?php

namespace Database\Factories;

use App\Domain\Suppliers\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Supplier> */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'type' => 'mixed',
            'legal_name' => 'DEMO '.fake()->company(),
            'trade_name' => 'DEMO '.fake()->company(),
            'status' => 'active',
            'is_demo' => true,
        ];
    }
}
