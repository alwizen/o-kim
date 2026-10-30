<?php

namespace App\Filament\Transportir\Widgets;

use App\Filament\Transportir\Resources\Findings\FindingResource;
use App\Models\FindingDispensationRequest;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Status permohonan KIM Sementara (dispensasi) milik transportir.
 */
class MyDispensationRequests extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Permohonan KIM Sementara Saya';

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('transportir') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                FindingDispensationRequest::query()
                    ->with(['finding.application.vehicle'])
                    ->whereHas('finding.application', fn ($query) => $query->whereIn(
                        'transporter_id',
                        $this->transporterIds(),
                    ))
            )
            ->description('Permohonan dispensasi yang Anda ajukan beserta keputusan FTM.')
            ->defaultSort('created_at', 'desc')
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('finding.application.vehicle.registration_number')
                    ->label('NOPOL')
                    ->badge(),
                TextColumn::make('finding.department')
                    ->label('Fungsi')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('reason')
                    ->label('Alasan')
                    ->limit(50)
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => $state->color()),
                TextColumn::make('due_date')
                    ->label('Batas Perbaikan')
                    ->date('d M Y')
                    ->placeholder('—'),
                TextColumn::make('decision_notes')
                    ->label('Catatan FTM')
                    ->placeholder('—')
                    ->limit(40)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Diajukan')
                    ->dateTime('d M Y H:i'),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Buka Temuan')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (FindingDispensationRequest $record): string => FindingResource::getUrl('view', ['record' => $record->finding_id])),
            ])
            ->emptyStateHeading('Belum ada permohonan')
            ->emptyStateDescription('Ajukan dispensasi dari halaman detail temuan bila perbaikan butuh waktu.');
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
