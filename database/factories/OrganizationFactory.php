<?php

namespace Database\Factories;

use App\Domain\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Organization> */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'name' => 'DEMO '.fake()->company(),
            'type' => 'study',
            'status' => 'active',
        ];
    }
}
