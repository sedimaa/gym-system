<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Typed access to the key/value settings, loaded once per request.
 * Unknown keys throw, so typos are caught early.
 */
class Settings
{
    private ?array $stored = null;

    public function get(string $key): mixed
    {
        $definition = $this->definition($key);
        $this->load();

        $raw = array_key_exists($key, $this->stored) ? $this->stored[$key] : null;

        if ($raw === null || $raw === '') {
            return $definition['default'];
        }

        return match ($definition['type']) {
            'int' => (int) $raw,
            'bool' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            default => (string) $raw,
        };
    }

    /** @param  array<string, mixed>  $values */
    public function set(array $values): void
    {
        foreach ($values as $key => $value) {
            $definition = $this->definition($key);
            $stored = match (true) {
                $value === null => null,
                $definition['type'] === 'bool' => $value ? '1' : '0',
                default => (string) $value,
            };

            Setting::updateOrCreate(['key' => $key], ['value' => $stored]);
        }

        $this->stored = null;
    }

    /** @return array<string, mixed> every setting with its effective value */
    public function all(): array
    {
        return collect(array_keys(config('gym.settings')))
            ->mapWithKeys(fn ($key) => [$key => $this->get($key)])
            ->all();
    }

    private function load(): void
    {
        if ($this->stored !== null) {
            return;
        }

        // Before migrations have run (fresh install) fall back to defaults.
        $this->stored = Schema::hasTable('settings')
            ? Setting::pluck('value', 'key')->all()
            : [];
    }

    private function definition(string $key): array
    {
        return config("gym.settings.{$key}") ?? throw new InvalidArgumentException("Unknown setting [{$key}].");
    }
}
