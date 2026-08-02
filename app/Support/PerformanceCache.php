<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class PerformanceCache
{
    public const LOOKUP_TTL = 600;
    public const DASHBOARD_TTL = 60;

    public static function key(string $name): string
    {
        return 'branch:'.(auth()->user()?->branch_id ?? 'global').':'.$name;
    }

    public static function forgetLookups(): void
    {
        foreach (['active-products', 'active-services', 'rate-chart-products', 'rate-chart-services'] as $key) {
            Cache::forget(self::key($key));
        }
    }
}
