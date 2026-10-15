<?php

namespace App\Services\Setting;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    private const CACHE_KEY = 'okim.settings';

    public const GROUP_KIM = 'kim';

    /** Nilai default setiap pengaturan. */
    private const DEFAULTS = [
        'kim.validity_months' => '6',
        'kim.number_prefix' => 'KIM/FT-TGL',
        'kim.number_period_format' => 'Y/m',
        'kim.number_sequence_digits' => '4',
    ];

    /**
     * Ambil nilai pengaturan (string mentah).
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $values = $this->all();

        if (array_key_exists($key, $values)) {
            return $values[$key];
        }

        return $default ?? self::DEFAULTS[$key] ?? null;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function set(string $key, ?string $value, string $group = 'general'): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group]
        );

        $this->flush();
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public function setMany(array $values, string $group = 'general'): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => $group]
            );
        }

        $this->flush();
    }

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return Setting::query()->pluck('value', 'key')->all();
        });
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    // ---------------------------------------------------------------
    // Pengaturan KIM
    // ---------------------------------------------------------------

    public function kimValidityMonths(): int
    {
        return $this->getInt('kim.validity_months', 6);
    }

    public function kimNumberPrefix(): string
    {
        return (string) $this->get('kim.number_prefix', 'KIM/FT-TGL');
    }

    public function kimNumberPeriodFormat(): string
    {
        return (string) $this->get('kim.number_period_format', 'Y/m');
    }

    public function kimNumberSequenceDigits(): int
    {
        return $this->getInt('kim.number_sequence_digits', 4);
    }
}
