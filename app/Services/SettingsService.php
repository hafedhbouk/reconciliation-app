<?php

namespace App\Services;

use App\Repositories\SettingsRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever(
            "settings.{$group}.{$key}",
            fn () => $this->settings->find($group, $key)?->value ?? $default
        );
    }

    public function set(string $group, string $key, mixed $value): void
    {
        $this->settings->set($group, $key, $value);
        Cache::forget("settings.{$group}.{$key}");
    }
}
