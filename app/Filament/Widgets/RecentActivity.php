<?php

namespace App\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Aktivitas terbaru pengguna (dari notifikasi lonceng).
 *
 * Memberi konteks "apa yang berubah" tanpa harus membuka menu satu per satu.
 */
class RecentActivity extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Aktivitas Terbaru';

    public static function canView(): bool
    {
        return auth()->check();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                DatabaseNotification::query()
                    ->where('notifiable_id', auth()->id())
                    ->where('notifiable_type', auth()->user()::class)
                    ->latest('created_at')
                    ->limit(10)
            )
            ->description('10 notifikasi terakhir untuk Anda.')
            ->paginated(false)
            ->columns([
                TextColumn::make('data.title')
                    ->label('Judul')
                    ->formatStateUsing(fn ($state): string => (string) ($state ?? '—'))
                    ->wrap(),
                TextColumn::make('data.body')
                    ->label('Keterangan')
                    ->formatStateUsing(fn ($state): string => (string) ($state ?? '—'))
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->since()
                    ->sortable(),
            ])
            ->emptyStateHeading('Belum ada aktivitas')
            ->emptyStateDescription('Notifikasi terbaru akan muncul di sini.');
    }
}
