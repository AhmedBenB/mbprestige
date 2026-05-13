<?php

namespace App\Services;

use App\Models\AdminSetting;
use App\Models\Organization;
use Illuminate\Support\Facades\Schema;

class AdminSettingsService
{
    public const KEYS = [
        'pricing',
        'thresholds',
        'schedule',
        'matching',
        'routing',
    ];

    public function defaults(): array
    {
        return [
            'pricing' => [
                'platform_fee' => 0,
                'transport_cost' => 0,
                'prep_cost' => 0,
                'admin_cost' => 0,
                'warranty_reserve' => 0,
                'safety_buffer' => 0,
            ],
            'thresholds' => [
                'reject_below_margin' => 2000,
                'candidate_from_margin' => 2000,
                'priority_from_margin' => 3000,
            ],
            'schedule' => [
                'scan_morning' => '08:00',
                'scan_evening' => '17:00',
                'timezone' => 'Europe/Paris',
            ],
            'matching' => [
                'high_priority_score' => 85,
                'max_results' => 100,
                'mileage_tolerance_default' => 10000,
            ],
            'routing' => [
                'selected_organization_ids' => [],
            ],
        ];
    }

    public function all(): array
    {
        $settings = $this->defaults();

        if (!Schema::hasTable('admin_settings')) {
            $settings['connector'] = [
                'mode' => config('ecarstrade.mode'),
                'connector' => config('ecarstrade.connector'),
                'base_url' => config('ecarstrade.base_url'),
                'search_zone' => config('ecarstrade.search_zone'),
                'timeout' => config('ecarstrade.timeout'),
            ];

            return $settings;
        }

        AdminSetting::query()
            ->whereIn('key', self::KEYS)
            ->get()
            ->each(function (AdminSetting $setting) use (&$settings): void {
                $settings[$setting->key] = array_replace(
                    $settings[$setting->key] ?? [],
                    $setting->value ?? []
                );
            });

        $settings['connector'] = [
            'mode' => config('ecarstrade.mode'),
            'connector' => config('ecarstrade.connector'),
            'base_url' => config('ecarstrade.base_url'),
            'search_zone' => config('ecarstrade.search_zone'),
            'timeout' => config('ecarstrade.timeout'),
        ];

        return $settings;
    }

    public function save(array $payload): array
    {
        $current = $this->all();

        if (!Schema::hasTable('admin_settings')) {
            return $current;
        }

        foreach (self::KEYS as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $value = array_replace($current[$key] ?? [], $payload[$key] ?? []);
            $value = $this->normalizeValue($key, $value);

            AdminSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        return $this->all();
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function normalizeValue(string $key, array $value): array
    {
        if ($key !== 'routing') {
            return $value;
        }

        $ids = collect($value['selected_organization_ids'] ?? [])
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $value['selected_organization_ids'] = Organization::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();

        return $value;
    }
}
