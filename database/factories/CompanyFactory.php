<?php

namespace Database\Factories;

use App\Enums\CompanyPhase;
use App\Enums\CompanyStage;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->company(),
            'phase' => CompanyPhase::Intern,
            'stage' => CompanyStage::Interested,
        ];
    }
}
