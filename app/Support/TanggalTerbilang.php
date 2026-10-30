<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Ubah tanggal menjadi kalimat terbilang bahasa Indonesia.
 *
 * Contoh: 2026-10-09 → "tanggal Sembilan bulan Oktober tahun Dua Ribu Dua Puluh Enam".
 *
 * Dipakai pada dokumen resmi (Berita Acara) agar sesuai kaidah penulisan
 * tanggal yang lazim pada dokumen negara.
 */
class TanggalTerbilang
{
    private const SATUAN = [
        0 => '',
        1 => 'Satu',
        2 => 'Dua',
        3 => 'Tiga',
        4 => 'Empat',
        5 => 'Lima',
        6 => 'Enam',
        7 => 'Tujuh',
        8 => 'Delapan',
        9 => 'Sembilan',
        10 => 'Sepuluh',
        11 => 'Sebelas',
    ];

    private const BULAN = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    /**
     * Terbilang angka (tanpa kata "rupiah").
     */
    public static function angka(int $angka): string
    {
        if ($angka < 0) {
            return 'Minus '.self::angka(-$angka);
        }

        if ($angka < 12) {
            return self::SATUAN[$angka];
        }

        if ($angka < 20) {
            return self::SATUAN[$angka - 10].' Belas';
        }

        if ($angka < 100) {
            $puluh = intdiv($angka, 10);
            $sisa = $angka % 10;

            return self::SATUAN[$puluh].' Puluh'.($sisa > 0 ? ' '.self::angka($sisa) : '');
        }

        if ($angka < 200) {
            return 'Seratus'.($angka > 100 ? ' '.self::angka($angka - 100) : '');
        }

        if ($angka < 1000) {
            $ratus = intdiv($angka, 100);
            $sisa = $angka % 100;

            return self::SATUAN[$ratus].' Ratus'.($sisa > 0 ? ' '.self::angka($sisa) : '');
        }

        if ($angka < 2000) {
            return 'Seribu'.($angka > 1000 ? ' '.self::angka($angka - 1000) : '');
        }

        if ($angka < 1_000_000) {
            $ribu = intdiv($angka, 1000);
            $sisa = $angka % 1000;

            return self::angka($ribu).' Ribu'.($sisa > 0 ? ' '.self::angka($sisa) : '');
        }

        if ($angka < 1_000_000_000) {
            $juta = intdiv($angka, 1_000_000);
            $sisa = $angka % 1_000_000;

            return self::angka($juta).' Juta'.($sisa > 0 ? ' '.self::angka($sisa) : '');
        }

        $miliar = intdiv($angka, 1_000_000_000);
        $sisa = $angka % 1_000_000_000;

        return self::angka($miliar).' Miliar'.($sisa > 0 ? ' '.self::angka($sisa) : '');
    }

    /**
     * Nama bulan bahasa Indonesia.
     */
    public static function bulan(int $bulan): string
    {
        return self::BULAN[$bulan] ?? '';
    }

    /**
     * Kalimat lengkap: "tanggal Sembilan bulan Oktober tahun Dua Ribu Dua Puluh Enam".
     */
    public static function lengkap(Carbon $tanggal): string
    {
        return sprintf(
            'tanggal %s bulan %s tahun %s',
            self::angka($tanggal->day),
            self::bulan($tanggal->month),
            self::angka($tanggal->year),
        );
    }
}
