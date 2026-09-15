<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'parent_id' => 0,
            'level' => 1,
            'province_code' => fake()->numerify('##0000'),
            'city_code' => fake()->numerify('####00'),
            'district_code' => fake()->numerify('######'),
            'street_code' => fake()->optional()->numerify('#########'),
            'name' => fake()->company(),
            'short_name' => fake()->companySuffix(),
            'social_credit_code' => fake()->unique()->numerify('##################'),
            'type' => fake()->numberBetween(1, 5),
            'status' => 1,
            'address' => fake()->address(),
            'contact_person' => fake()->name(),
            'contact_phone' => fake()->phoneNumber(),
            'legal_representative' => fake()->name(),
            'remark' => fake()->optional()->sentence(),
        ];
    }

    public function root(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => 0,
            'level' => 1,
        ]);
    }

    public function child(int $parentId, int $level): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parentId,
            'level' => $level,
        ]);
    }
}
