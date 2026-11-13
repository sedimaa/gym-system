<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Admin-only abilities from the role matrix in CLAUDE.md.
     * Staff can do everything else (members, payments, walk-ins, attendance).
     * Staff account management is handled by App\Policies\UserPolicy.
     */
    public const ADMIN_ABILITIES = [
        'manage-plans',        // plans and prices
        'manage-settings',     // gym info, rules, renewal/grace settings
        'manage-backups',      // backups and restore
        'deactivate-members',
        'delete-records',      // delete any record
        'correct-attendance',  // add/correct attendance records (with note)
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach (self::ADMIN_ABILITIES as $ability) {
            Gate::define($ability, fn (User $user) => $user->isAdmin());
        }

        // @peso($amount) -> ₱1,234.00
        Blade::directive('peso', fn (string $expression) => "<?php echo e(\\App\\Support\\Money::peso($expression)); ?>");
    }
}
