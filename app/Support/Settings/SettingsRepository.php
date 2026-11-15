<?php

namespace App\Support\Settings;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Reads and writes database-driven settings, addressed as "group.key".
 *
 * All rows are cached as one array; any write clears the cache. Encrypted
 * values are decrypted on read and are never included in public().
 */
class SettingsRepository
{
    private const CACHE_KEY = 'settings.all';

    /** @var array<string, array{value: mixed, public: bool}>|null */
    private ?array $loaded = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key]['value'] : $default;
    }

    /**
     * @return array<string, mixed> keys without the group prefix
     */
    public function group(string $group): array
    {
        $result = [];

        foreach ($this->all() as $key => $row) {
            if (str_starts_with($key, "{$group}.")) {
                $result[substr($key, strlen($group) + 1)] = $row['value'];
            }
        }

        return $result;
    }

    public function set(string $key, mixed $value, bool $encrypted = false, ?bool $public = null): void
    {
        [$group, $name] = $this->split($key);

        $setting = Setting::firstOrNew(['group' => $group, 'key' => $name]);
        $setting->value = $encrypted && $value !== null ? Crypt::encryptString(json_encode($value, JSON_THROW_ON_ERROR)) : $value;
        $setting->is_encrypted = $encrypted;

        if ($public !== null) {
            $setting->is_public = $public && ! $encrypted;
        }

        $setting->save();

        $this->flush();
    }

    /**
     * Settings flagged public, nested by group. Safe to send to the browser.
     *
     * @return array<string, array<string, mixed>>
     */
    public function public(): array
    {
        $result = [];

        foreach ($this->all() as $key => $row) {
            if ($row['public']) {
                Arr::set($result, $key, $row['value']);
            }
        }

        return $result;
    }

    public function flush(): void
    {
        $this->loaded = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, array{value: mixed, public: bool}>
     */
    private function all(): array
    {
        return $this->loaded ??= Cache::rememberForever(self::CACHE_KEY, function () {
            return Setting::query()->get()->mapWithKeys(fn (Setting $s) => [
                "{$s->group}.{$s->key}" => [
                    'value' => $s->is_encrypted && is_string($s->value)
                        ? json_decode(Crypt::decryptString($s->value), true)
                        : $s->value,
                    'public' => $s->is_public && ! $s->is_encrypted,
                ],
            ])->all();
        });
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function split(string $key): array
    {
        $group = strstr($key, '.', true);

        if ($group === false || $group === '' || $group === $key || str_ends_with($key, '.')) {
            throw new \InvalidArgumentException("Setting key [{$key}] must be in \"group.key\" form.");
        }

        return [$group, substr($key, strlen($group) + 1)];
    }
}
