<?php

namespace Database\Factories\Contract;

use App\Models\Contract\MasterContract;
use Illuminate\Database\Eloquent\Factories\Factory;

class MasterContractFactory extends Factory
{
    protected $model = MasterContract::class;

    public function definition(): array
    {
        return [
            'contract_no' => 'MC-'.fake()->unique()->numerify('########'),
            'title' => fake()->sentence(4),
            'org_a_id' => 1,
            'org_b_id' => 1,
            'total_limit' => fake()->randomFloat(2, 10000, 1000000),
            'version' => 1,
            'status' => MasterContract::STATUS_DRAFT,
            'signed_date' => fake()->optional()->date(),
            'effective_date' => fake()->optional()->date(),
            'expiry_date' => fake()->optional()->date(),
            'signer_a' => fake()->name(),
            'contact_a' => fake()->name(),
            'signer_b' => fake()->name(),
            'contact_b' => fake()->name(),
            'contact_a_phone' => fake()->phoneNumber(),
            'contact_b_phone' => fake()->phoneNumber(),
            'summary' => fake()->optional()->paragraph(),
            'remarks' => fake()->optional()->sentence(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MasterContract::STATUS_DRAFT,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MasterContract::STATUS_PENDING,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MasterContract::STATUS_REJECTED,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MasterContract::STATUS_ACTIVE,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MasterContract::STATUS_EXPIRED,
        ]);
    }

    public function terminated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MasterContract::STATUS_TERMINATED,
        ]);
    }

    public function voided(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MasterContract::STATUS_VOID,
        ]);
    }
}
