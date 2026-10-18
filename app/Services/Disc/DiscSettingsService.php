<?php

namespace App\Services\Disc;

use App\Models\DiscSetting;

class DiscSettingsService
{
    /** @var array<string, string>|null */
    private ?array $settings = null;

    public function float(string $key, float $fallback): float
    {
        $this->settings ??= DiscSetting::query()->pluck('value', 'key')->all();

        return isset($this->settings[$key]) ? (float) $this->settings[$key] : $fallback;
    }
}
