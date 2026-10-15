<?php

namespace Tests\Feature;

use App\Support\SafeFilename;
use Tests\TestCase;

class SafeFilenameTest extends TestCase
{
    public function test_replaces_slashes_with_dash(): void
    {
        $this->assertSame(
            'KIM-FT-TGL-2026-10-0001.pdf',
            SafeFilename::make('KIM/FT-TGL/2026/10/0001.pdf')
        );
    }

    public function test_replaces_backslashes(): void
    {
        $this->assertSame('bukti-foto.jpg', SafeFilename::make('bukti\\foto.jpg'));
    }

    public function test_never_returns_slash_or_backslash(): void
    {
        $samples = [
            'KIM/FT-TGL/2026/10/0001.pdf',
            'a/b\\c/d.pdf',
            '///.pdf',
            '..',
        ];

        foreach ($samples as $sample) {
            $result = SafeFilename::make($sample);

            $this->assertStringNotContainsString('/', $result, "Gagal pada: {$sample}");
            $this->assertStringNotContainsString('\\', $result, "Gagal pada: {$sample}");
        }
    }

    public function test_strips_unsafe_characters(): void
    {
        $this->assertSame('weird-name.pdf', SafeFilename::make('weird/**name?.pdf'));
    }

    public function test_uses_fallback_when_name_becomes_empty(): void
    {
        $this->assertSame('berkas.pdf', SafeFilename::make('...pdf'));
        $this->assertSame('fallback.pdf', SafeFilename::make('///.pdf', 'fallback'));
    }

    public function test_preserves_extension(): void
    {
        $this->assertSame('dokumen.png', SafeFilename::make('dokumen.png'));
        $this->assertSame('dokumen.jpg', SafeFilename::make('dokumen.jpg'));
    }

    public function test_handles_filename_without_extension(): void
    {
        $this->assertSame('no-extension', SafeFilename::make('no-extension'));
    }
}
