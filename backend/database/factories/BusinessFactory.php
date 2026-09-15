<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

class BusinessFactory extends Factory
{
    protected $model = Business::class;

    public function definition(): array
    {
        return [
            'org_id' => 1,
            'carrier_id' => 1,
            'status' => 1,
            'code' => fake()->unique()->numerify('BIZ-#####'),
            'name' => fake()->company(),
            'short_name' => fake()->companySuffix(),
            'contact_person' => fake()->name(),
            'contact_phone' => fake()->phoneNumber(),
            'contents' => fake()->optional()->sentence(),
            'address' => fake()->address(),
            'created_by' => 1,
            'updated_by' => null,
            'deleted_by' => null,
        ];
    }
}
