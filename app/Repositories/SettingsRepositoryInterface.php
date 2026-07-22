<?php

namespace App\Repositories;

use App\Models\Setting;
use Illuminate\Support\Collection;

interface SettingsRepositoryInterface
{
    public function allForGroup(string $group): Collection;

    public function find(string $group, string $key): ?Setting;

    public function set(string $group, string $key, mixed $value): Setting;
}
