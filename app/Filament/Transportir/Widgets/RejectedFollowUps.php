<?php

namespace App\Filament\Transportir\Widgets;

use App\Enums\FindingStatus;
use App\Filament\Transportir\Resources\Findings\FindingResource;
use App\Models\Finding;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Temuan yang tindak lanjutnya ditolak dan perlu dikirim ulang transportir.
 */
class RejectedFollowUps extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Tindak Lanjut Ditolak';

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('transportir') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Finding::query()
                    ->with(['application.vehicle', 'verifications'])
                    ->where('status', FindingStatus::Rejected->value)
                    ->whereHas('application', fn ($query) => $query->whereIn(
                        'transporter_id',
                        $this->transporterIds(),
                    ))
            )
            ->description('Tindak lanjut Anda ditolak petugas. Perbaiki lalu kirim ulang bukti.')
            ->defaultSort('updated_at', 'desc')
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('application.application_number')
                    ->label('No. Pengajuan'),
                TextColumn::make('application.vehicle.registration_number')
                    ->label('NOPOL')
                    ->badge(),
                TextColumn::make('department')
                    ->label('Fungsi')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('description')
                    ->label('Temuan')
                    ->limit(50)
                    ->wrap(),
                TextColumn::make('latest_rejection')
                    ->label('Catatan Penolakan')
                    ->state(fn (Finding $record): ?string => $record->verifications->last()?->notes)
                    ->placeholder('—')
                    ->limit(60)
                    ->wrap(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Kirim Ulang')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->url(fn (Finding $record): string => FindingResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading('Tidak ada tindak lanjut ditolak')
            ->emptyStateDescription('Semua tindak lanjut Anda diterima atau belum diperiksa.');
    }

    /**
     * @return array<int, int>
     */
    private function transporterIds(): array
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user === null
            ? []
            : $user->transporters()->pluck('transporters.id')->all();
    }
}
