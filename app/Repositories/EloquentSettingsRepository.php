<?php

namespace App\Repositories;

use App\Models\Setting;
use Illuminate\Support\Collection;

class EloquentSettingsRepository implements SettingsRepositoryInterface
{
    public function allForGroup(string $group): Collection
    {
        return Setting::query()->where('group', $group)->get();
    }

    public function find(string $group, string $key): ?Setting
    {
        return Setting::query()->where('group', $group)->where('key', $key)->first();
    }

    public function set(string $group, string $key, mixed $value): Setting
    {
        return Setting::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $value]
        );
    }
}
