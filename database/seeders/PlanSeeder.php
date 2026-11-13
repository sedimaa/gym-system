<?php

namespace Database\Seeders;

use App\Enums\PlanType;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['name' => 'Monthly', 'type' => PlanType::Monthly, 'price' => 800, 'duration_days' => 30, 'description' => 'Unlimited gym use for 30 days.'],
            ['name' => 'Quarterly', 'type' => PlanType::Quarterly, 'price' => 2200, 'duration_days' => 90, 'description' => '3 months, save ₱200.'],
            ['name' => 'Yearly', 'type' => PlanType::Yearly, 'price' => 8000, 'duration_days' => 365, 'description' => '12 months, best value.'],
            ['name' => '10-Session Pass', 'type' => PlanType::PerSession, 'price' => 600, 'duration_days' => 60, 'session_count' => 10, 'description' => '10 visits, use within 60 days.'],
            ['name' => 'Day Pass', 'type' => PlanType::DayPass, 'price' => 80, 'duration_days' => 1, 'description' => 'Walk-in, one day.'],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['name' => $plan['name']], $plan + ['is_active' => true]);
        }
    }
}
