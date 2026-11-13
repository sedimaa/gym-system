<?php

namespace Database\Factories;

use App\Enums\PlanType;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Monthly '.fake()->unique()->numberBetween(1, 99999),
            'type' => PlanType::Monthly,
            'price' => 800,
            'duration_days' => 30,
            'session_count' => null,
            'is_active' => true,
        ];
    }

    public function monthly(): static
    {
        return $this->state(['type' => PlanType::Monthly, 'price' => 800, 'duration_days' => 30]);
    }

    public function quarterly(): static
    {
        return $this->state(['type' => PlanType::Quarterly, 'price' => 2200, 'duration_days' => 90]);
    }

    public function perSession(int $sessions = 10): static
    {
        return $this->state(['type' => PlanType::PerSession, 'price' => 600, 'duration_days' => 60, 'session_count' => $sessions]);
    }

    public function dayPass(float $price = 80): static
    {
        return $this->state(['type' => PlanType::DayPass, 'price' => $price, 'duration_days' => 1]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
