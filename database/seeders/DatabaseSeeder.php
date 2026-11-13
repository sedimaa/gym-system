<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Demo database for the owner demo: logins, plans, sample settings and ~3 months of activity.
 * To start clean for real use instead: php artisan migrate:fresh --seeder=EssentialsSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            SettingsSeeder::class,
            PlanSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
