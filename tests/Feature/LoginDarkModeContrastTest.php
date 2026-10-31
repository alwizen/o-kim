<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman login panel admin harus tetap terbaca saat mode gelap aktif.
 *
 * Layout login memakai palet `auth-*` yang hanya punya varian terang. Ketika
 * Filament menambahkan class `dark` pada <html>, komponen form mewarisi warna
 * teks putih sehingga tidak terlihat di atas latar putih. Layout karena itu
 * mengunci halaman login ke tampilan terang lewat aturan ber-`!important`.
 */
class LoginDarkModeContrastTest extends TestCase
{
    use RefreshDatabase;

    private function loginHtml(): string
    {
        return $this->get('/admin/login')->assertSuccessful()->getContent();
    }

    public function test_login_forces_light_color_scheme(): void
    {
        $this->assertStringContainsString('color-scheme: light !important', $this->loginHtml());
    }

    public function test_heading_text_is_dark_blue_not_white(): void
    {
        $html = $this->loginHtml();

        $this->assertStringContainsString(
            '.auth-split .fi-simple-header-heading',
            $html,
        );
        $this->assertStringContainsString('color: #0f2854 !important', $html);
    }

    public function test_field_labels_use_dark_text(): void
    {
        $html = $this->loginHtml();

        // Class label yang benar-benar dipakai markup Filament.
        $this->assertStringContainsString('.auth-split .fi-fo-field-label-content', $html);
        $this->assertStringContainsString('color: #1f2937 !important', $html);
    }

    public function test_input_background_stays_white_with_dark_text(): void
    {
        $html = $this->loginHtml();

        $this->assertStringContainsString('.auth-split .fi-input-wrp', $html);
        $this->assertStringContainsString('background-color: #ffffff !important', $html);
        $this->assertStringContainsString('.auth-split .fi-input', $html);
    }

    public function test_links_remain_readable_blue(): void
    {
        $html = $this->loginHtml();

        $this->assertStringContainsString('.auth-split .fi-link', $html);
        $this->assertStringContainsString('color: #1c4d8d !important', $html);
    }

    public function test_brand_panel_is_locked_to_light_palette(): void
    {
        $html = $this->loginHtml();

        $this->assertStringContainsString('.auth-split aside', $html);
    }
}
