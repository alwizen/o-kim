<?php

namespace App\Filament\Resources\KimCards\Pages;

use App\Filament\Resources\KimCards\KimCardResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewKimCard extends ViewRecord
{
    protected static string $resource = KimCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label('Unduh PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(fn (): string => route('kim.download', $this->record)),
            Action::make('download_image')
                ->label('Unduh Gambar (Kartu ATM)')
                ->icon('heroicon-o-photo')
                ->color('gray')
                ->visible(fn (): bool => (bool) config('kim.image_download_enabled'))
                ->tooltip('PNG 85,6 × 54 mm — cetak aktual 100%, jangan "fit to page".')
                ->url(fn (): string => route('kim.download-image', $this->record)),
        ];
    }
}
