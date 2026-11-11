<?php

namespace App\Services\Setting;

use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    private const CACHE_KEY = 'okim.settings';

    public const GROUP_KIM = 'kim';

    public const GROUP_NOTIFICATION = 'notification';

    public const GROUP_DOCUMENT = 'document';

    /**
     * Format periode khusus: bulan dalam angka Romawi diikuti tahun (XI/2026).
     */
    public const PERIOD_ROMAN = 'roman';

    /** Nilai default setiap pengaturan. */
    private const DEFAULTS = [
        'kim.validity_months' => '6',
        'kim.number_prefix' => 'KIM/FT-TGL',
        'kim.number_period_format' => 'Y/m',
        'kim.number_sequence_digits' => '4',
        'application.number_prefix' => 'APP',
        'application.number_period_format' => 'Ymd',
        'application.number_sequence_digits' => '4',
        'notification.expiry_warning_days' => '60',
        'document.ba_qr_enabled' => '1',
        'document.kim_qr_enabled' => '1',
        'document.legal_reference' => 'Undang-Undang Nomor 11 Tahun 2008 jo. Undang-Undang Nomor 19 Tahun 2016 tentang Informasi dan Transaksi Elektronik.',
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

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
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

    // ---------------------------------------------------------------
    // Pengaturan Penomoran Pengajuan KIM
    // ---------------------------------------------------------------

    public function applicationNumberPrefix(): string
    {
        return (string) $this->get('application.number_prefix', 'APP');
    }

    public function applicationNumberPeriodFormat(): string
    {
        return (string) $this->get('application.number_period_format', 'Ymd');
    }

    public function applicationNumberSequenceDigits(): int
    {
        return $this->getInt('application.number_sequence_digits', 4);
    }

    // ---------------------------------------------------------------
    // Pengaturan Notifikasi
    // ---------------------------------------------------------------

    /**
     * Ambang hari peringatan dokumen/KIM akan kedaluwarsa.
     */
    public function expiryWarningDays(): int
    {
        $days = $this->getInt('notification.expiry_warning_days', 60);

        return max(1, $days);
    }

    // ---------------------------------------------------------------
    // Pengaturan Dokumen (QR tanda tangan/validasi)
    // ---------------------------------------------------------------

    /**
     * Apakah QR tanda tangan elektronik ditampilkan pada Berita Acara.
     */
    public function baQrEnabled(): bool
    {
        return $this->getBool('document.ba_qr_enabled', true);
    }

    /**
     * Apakah QR validasi ditampilkan pada kartu KIM (PDF & gambar).
     */
    public function kimQrEnabled(): bool
    {
        return $this->getBool('document.kim_qr_enabled', true);
    }

    /**
     * Dasar hukum tanda tangan elektronik yang dirujuk pada Berita Acara.
     */
    public function legalReference(): string
    {
        $value = trim((string) $this->get('document.legal_reference', ''));

        return $value !== '' ? $value : (string) self::DEFAULTS['document.legal_reference'];
    }

    /**
     * Susun contoh nomor dari komponen pengaturan.
     * Format: {prefix}/{periode}/{urutan} — periode dilewati bila 'none'.
     */
    public function buildNumberPreview(
        string $prefix,
        string $periodFormat,
        int $sequenceDigits,
        string $sequence = '1',
    ): string {
        $segments = [trim($prefix, '/')];

        $period = $this->formatPeriod($periodFormat);

        if ($period !== null) {
            $segments[] = $period;
        }

        $segments[] = str_pad($sequence, max($sequenceDigits, 1), '0', STR_PAD_LEFT);

        return implode('/', array_filter($segments, fn ($segment) => $segment !== ''));
    }

    /**
     * Terjemahkan format periode menjadi teks periode.
     * Mengembalikan null bila periode tidak dipakai.
     *
     * Nilai khusus 'roman' menghasilkan bulan dalam angka Romawi diikuti tahun,
     * contoh: XI/2026.
     */
    public function formatPeriod(string $periodFormat, ?CarbonInterface $date = null): ?string
    {
        if ($periodFormat === '' || $periodFormat === 'none') {
            return null;
        }

        $date ??= now();

        if ($periodFormat === self::PERIOD_ROMAN) {
            return $this->romanMonth((int) $date->month).'/'.$date->format('Y');
        }

        return $date->format($periodFormat);
    }

    /**
     * Angka Romawi untuk bulan 1–12.
     */
    public function romanMonth(int $month): string
    {
        $numerals = [
            1 => 'I',
            2 => 'II',
            3 => 'III',
            4 => 'IV',
            5 => 'V',
            6 => 'VI',
            7 => 'VII',
            8 => 'VIII',
            9 => 'IX',
            10 => 'X',
            11 => 'XI',
            12 => 'XII',
        ];

        return $numerals[$month] ?? (string) $month;
    }
}
