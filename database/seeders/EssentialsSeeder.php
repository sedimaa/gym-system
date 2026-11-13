<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * For going live without demo data:
 *   php artisan migrate:fresh --seeder=EssentialsSeeder
 * Creates the admin and staff logins and the five plans only.
 */
class EssentialsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([UserSeeder::class, PlanSeeder::class]);
    }
}
