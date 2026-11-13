<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Demo logins. Change these passwords before real use.
     */
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin@gym.test'], [
            'name' => 'Rodel Santos',
            'password' => 'password',
            'role' => Role::Admin,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        User::updateOrCreate(['email' => 'staff@gym.test'], [
            'name' => 'Jessa Mae Dela Cruz',
            'password' => 'password',
            'role' => Role::Staff,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}
