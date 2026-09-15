<?php

namespace Database\Factories;

use App\Models\OrganizationBank;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationBankFactory extends Factory
{
    protected $model = OrganizationBank::class;

    public function definition(): array
    {
        return [
            'organization_id' => 1,
            'bank_name' => fake()->company().'银行',
            'bank_account' => fake()->bankAccountNumber(),
            'account_name' => fake()->name(),
            'is_default' => 0,
            'status' => 1,
            'remark' => fake()->optional()->sentence(),
        ];
    }
}
