<?php

/*
| Defaults for the admin-editable settings (stored as key/value rows in the `settings` table).
| A setting that has never been saved falls back to the value here.
*/

return [
    'settings' => [
        'gym_name' => ['default' => env('APP_NAME', 'Gym Management System'), 'type' => 'string'],
        'gym_address' => ['default' => '', 'type' => 'string'],
        'gym_phone' => ['default' => '', 'type' => 'string'],
        'gym_logo' => ['default' => null, 'type' => 'string'], // file name under storage/app/private/branding
        'receipt_header_note' => ['default' => '', 'type' => 'string'],
        'receipt_footer' => ['default' => 'Thank you! Please keep this receipt.', 'type' => 'string'],
        'gym_rules' => ['default' => '', 'type' => 'string'],

        // Membership rules
        'grace_days' => ['default' => 0, 'type' => 'int'],
        'expiring_soon_days' => ['default' => 7, 'type' => 'int'],
        'renewal_start_mode' => ['default' => 'from_payment_date', 'type' => 'string'], // or from_old_expiry

        // Walk-ins: false = always the day-pass price; true = staff may change the amount
        'walkin_price_editable' => ['default' => false, 'type' => 'bool'],

        // Backups: empty = storage/app/backups inside the project
        'backup_path' => ['default' => '', 'type' => 'string'],
    ],

    'renewal_start_modes' => [
        'from_payment_date' => 'Start on the payment date',
        'from_old_expiry' => 'Start the day after the old expiry (if it has not passed yet)',
    ],
];
