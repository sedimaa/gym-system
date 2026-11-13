<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Module 1 seeds the two demo logins. Plans, members, payments, attendance
     * and walk-ins are added in Module 9 (Seeders and polish).
     */
    public function run(): void
    {
        $this->call(UserSeeder::class);
    }
}
