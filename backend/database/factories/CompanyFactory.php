<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => null, // wird automatisch generiert
            'join_password' => \Illuminate\Support\Facades\Hash::make('password'),
            'owner_id' => \App\Models\User::factory(),
            'logo_path' => null,
        ];
    }
}
