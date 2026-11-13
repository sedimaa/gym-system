<?php

namespace Database\Seeders;

use App\Support\Settings;
use Illuminate\Database\Seeder;

/** Sample gym details for the demo. The admin changes these under Settings. */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        app(Settings::class)->set([
            'gym_name' => 'Barangay Fitness Gym',
            'gym_address' => 'Purok 3, Brgy. San Roque',
            'gym_phone' => '0917 123 4567',
            'receipt_footer' => 'Salamat po! Please keep this receipt.',
            'gym_rules' => implode("\n", [
                '1. Present your member code at the front desk every visit.',
                '2. Wear proper gym attire and closed shoes.',
                '3. Wipe down equipment after use and return weights to the rack.',
                '4. No dropping of dumbbells and barbells.',
                '5. Memberships are personal and cannot be shared or transferred.',
                '6. Payments are non-refundable.',
            ]),
        ]);
    }
}
