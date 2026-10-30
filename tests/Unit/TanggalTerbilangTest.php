<?php

namespace Tests\Unit;

use App\Support\TanggalTerbilang;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TanggalTerbilangTest extends TestCase
{
    #[DataProvider('angkaProvider')]
    public function test_angka_diterbilangkan(int $angka, string $harapan): void
    {
        $this->assertSame($harapan, TanggalTerbilang::angka($angka));
    }

    public static function angkaProvider(): array
    {
        return [
            [0, ''],
            [1, 'Satu'],
            [7, 'Tujuh'],
            [9, 'Sembilan'],
            [10, 'Sepuluh'],
            [11, 'Sebelas'],
            [12, 'Dua Belas'],
            [19, 'Sembilan Belas'],
            [20, 'Dua Puluh'],
            [21, 'Dua Puluh Satu'],
            [31, 'Tiga Puluh Satu'],
            [100, 'Seratus'],
            [101, 'Seratus Satu'],
            [200, 'Dua Ratus'],
            [999, 'Sembilan Ratus Sembilan Puluh Sembilan'],
            [1000, 'Seribu'],
            [1001, 'Seribu Satu'],
            [2026, 'Dua Ribu Dua Puluh Enam'],
            [2000, 'Dua Ribu'],
            [1_000_000, 'Satu Juta'],
        ];
    }

    public function test_nama_bulan(): void
    {
        $this->assertSame('Januari', TanggalTerbilang::bulan(1));
        $this->assertSame('Agustus', TanggalTerbilang::bulan(8));
        $this->assertSame('Oktober', TanggalTerbilang::bulan(10));
        $this->assertSame('Desember', TanggalTerbilang::bulan(12));
    }

    public function test_lengkap_menghasilkan_kalimat_indonesia(): void
    {
        $tanggal = Carbon::create(2026, 10, 9);

        $this->assertSame(
            'tanggal Sembilan bulan Oktober tahun Dua Ribu Dua Puluh Enam',
            TanggalTerbilang::lengkap($tanggal),
        );
    }

    public function test_lengkap_untuk_berbagai_tanggal(): void
    {
        $this->assertSame(
            'tanggal Satu bulan Januari tahun Dua Ribu Dua Puluh Lima',
            TanggalTerbilang::lengkap(Carbon::create(2025, 1, 1)),
        );

        $this->assertSame(
            'tanggal Tiga Puluh Satu bulan Desember tahun Seribu Sembilan Ratus Sembilan Puluh Sembilan',
            TanggalTerbilang::lengkap(Carbon::create(1999, 12, 31)),
        );

        $this->assertSame(
            'tanggal Sebelas bulan November tahun Dua Ribu Dua Puluh Enam',
            TanggalTerbilang::lengkap(Carbon::create(2026, 11, 11)),
        );
    }
}
