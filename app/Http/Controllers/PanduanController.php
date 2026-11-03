<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Menyajikan panduan penggunaan O-KIM (folder dokumentasi) sebagai halaman web di /panduan.
 *
 * Sumber: file Markdown di dokumentasi/ (versi tanpa gambar).
 * Folder "dokumentasi/dengan gambar" sengaja TIDAK dirender.
 */
class PanduanController extends Controller
{
    /**
     * Versi format cache. Naikkan bila struktur hasil render berubah,
     * agar cache lama (format berbeda) tidak dipakai lagi.
     */
    private const CACHE_VERSION = 'v3';

    /**
     * Peta slug => file Markdown. Urutan = urutan tampil di navigasi.
     */
    public const PANDUAN = [
        'transportir' => [
            'file' => 'transportir.md',
            'judul' => 'Transportir',
            'ringkas' => 'Mendaftar, mengelola kendaraan & dokumen, mengajukan KIM, menindaklanjuti temuan, hingga mengunduh KIM.',
        ],
        'tad' => [
            'file' => 'tad.md',
            'judul' => 'TAD (HSSE / Distribusi / QQ)',
            'ringkas' => 'Mengisi checklist pemeriksaan, membuat temuan, dan memverifikasi tindak lanjut transportir.',
        ],
        'pengawas' => [
            'file' => 'pengawas.md',
            'judul' => 'Pengawas Fungsi',
            'ringkas' => 'Verifikasi tindak lanjut temuan, approval fungsi (HSSE/Distribusi/QQ), dan penerbitan KIM.',
        ],
        'ftm' => [
            'file' => 'ftm.md',
            'judul' => 'FTM (Fuel Terminal Manager)',
            'ringkas' => 'Approval akhir pengajuan dan keputusan dispensasi untuk KIM Sementara.',
        ],
        'admin-verifikasi' => [
            'file' => 'adminverivikasi.md',
            'judul' => 'Admin Verifikasi',
            'ringkas' => 'Verifikasi pendaftaran transportir & dokumen, penjadwalan pemeriksaan, penerbitan KIM.',
        ],
        'super-admin' => [
            'file' => 'superadmin.md',
            'judul' => 'Super Admin',
            'ringkas' => 'Pengelolaan pengguna internal, role & permission, pengaturan sistem, dan notifikasi email.',
        ],
        'kim-sementara' => [
            'file' => 'kim-sementara.md',
            'judul' => 'Alur KIM Sementara',
            'ringkas' => 'Alur lengkap dispensasi FTM: permohonan transportir hingga kartu KIM bertanda SEMENTARA.',
        ],
    ];

    /**
     * Halaman daftar seluruh panduan.
     */
    public function index(): View
    {
        return view('docs.index', [
            'panduan' => self::PANDUAN,
        ]);
    }

    /**
     * Halaman isi satu panduan.
     */
    public function show(string $slug): View
    {
        $meta = self::PANDUAN[$slug] ?? abort(404);

        ['html' => $konten, 'toc' => $toc] = $this->html($meta['file']);

        return view('docs.show', [
            'slug' => $slug,
            'meta' => $meta,
            'panduan' => self::PANDUAN,
            'konten' => $konten,
            'toc' => $toc,
            'prev' => $this->sibling($slug, -1),
            'next' => $this->sibling($slug, +1),
        ]);
    }

    /**
     * Slug panduan sebelum/berikutnya (untuk navigasi bawah halaman).
     */
    private function sibling(string $slug, int $offset): ?string
    {
        $keys = array_keys(self::PANDUAN);
        $index = array_search($slug, $keys, true);

        if ($index === false) {
            return null;
        }

        return $keys[$index + $offset] ?? null;
    }

    /**
     * Konversi Markdown ke HTML (dengan cache per file berdasarkan waktu modifikasi).
     *
     * @return array{html: string, toc: array<int, array{id: string, text: string}>}
     */
    private function html(string $file): array
    {
        return Cache::rememberForever(
            'panduan:'.self::CACHE_VERSION.':'.sha1($file.filemtime($this->path($file))),
            function () use ($file): array {
                $html = $this->converter()->convert($this->markdown($file))->getContent();

                $html = $this->wrapTables($html);

                return $this->addHeadingAnchors($html);
            },
        );
    }

    /**
     * Bungkus tabel agar bisa di-scroll horizontal tanpa merusak layout.
     */
    private function wrapTables(string $html): string
    {
        return (string) preg_replace(
            '#(<table>.*?</table>)#s',
            '<div class="docs-table-wrap">$1</div>',
            $html,
        );
    }

    /**
     * Beri id pada setiap <h2> dan ambil daftar isinya.
     *
     * @return array{html: string, toc: array<int, array{id: string, text: string}>}
     */
    private function addHeadingAnchors(string $html): array
    {
        $toc = [];
        $used = [];

        $html = (string) preg_replace_callback(
            '#<h2>(.*?)</h2>#s',
            function (array $matches) use (&$toc, &$used): string {
                $text = trim(strip_tags($matches[1]));
                $slug = Str::slug($text);

                // Pastikan id unik bila ada judul yang sama.
                $base = $slug !== '' ? $slug : 'bagian';
                $slug = $base;
                $counter = 2;

                while (in_array($slug, $used, true)) {
                    $slug = $base.'-'.$counter++;
                }

                $used[] = $slug;
                $toc[] = ['id' => $slug, 'text' => $text];

                return '<h2 id="'.e($slug).'">'.$matches[1].'</h2>';
            },
            $html,
        );

        return ['html' => $html, 'toc' => $toc];
    }

    private function markdown(string $file): string
    {
        $path = $this->path($file);

        abort_unless(is_file($path), 404, 'Dokumen panduan tidak ditemukan.');

        return (string) file_get_contents($path);
    }

    private function path(string $file): string
    {
        return base_path('dokumentasi/'.$file);
    }

    private function converter(): MarkdownConverter
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
            // Dokumen ditulis dengan baris terpisah untuk tiap poin
            // (mis. di dalam blockquote), jadi baris baru dipertahankan.
            'renderer' => [
                'soft_break' => "<br />\n",
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new TableExtension);

        return new MarkdownConverter($environment);
    }
}
