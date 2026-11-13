<?php

use App\Support\Settings;

if (! function_exists('setting')) {
    /** Read an admin setting, e.g. setting('grace_days'). */
    function setting(string $key): mixed
    {
        return app(Settings::class)->get($key);
    }
}
