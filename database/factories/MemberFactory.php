<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MemberFactory extends Factory
{
    public function definition(): array
    {
        $gender = fake()->randomElement(['male', 'female']);

        return [
            'first_name' => fake()->firstName($gender),
            'middle_name' => fake()->optional()->lastName(),
            'last_name' => fake()->lastName(),
            'gender' => $gender,
            'birthdate' => fake()->dateTimeBetween('-55 years', '-16 years')->format('Y-m-d'),
            'phone' => '09'.fake()->numerify('#########'),
            'address' => fake()->streetAddress(),
            'joined_on' => today()->subMonths(2),
            'is_active' => true,
        ];
    }

    public function deactivated(): static
    {
        return $this->state(['is_active' => false, 'deactivated_at' => now()]);
    }
}
