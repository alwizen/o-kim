<?php

namespace App\Filament\Widgets;

use App\Actions\Document\VerifyVehicleDocument;
use App\Enums\DocumentVerificationStatus;
use App\Models\VehicleDocument;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Antrean dokumen kendaraan yang menunggu verifikasi admin.
 */
class PendingDocumentVerification extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Dokumen Menunggu Verifikasi';

    public static function canView(): bool
    {
        $user = auth()->user();

        if ($user === null || ! $user->can('verify_documents')) {
            return false;
        }

        // Khusus admin verifikasi & super admin; FTM punya widget sendiri.
        return $user->hasRole('super_admin') || $user->hasRole('admin_verifikasi');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                VehicleDocument::query()
                    ->with(['vehicle.transporter', 'documentType'])
                    ->whereNull('replaced_by_id')
                    ->where('verification_status', DocumentVerificationStatus::Pending->value)
            )
            ->description('Dokumen yang belum diperiksa, diurutkan dari yang paling lama diunggah.')
            ->defaultSort('created_at', 'asc')
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('vehicle.transporter.company_name')
                    ->label('Perusahaan')
                    ->limit(28)
                    ->wrap(),
                TextColumn::make('vehicle.registration_number')
                    ->label('NOPOL')
                    ->badge(),
                TextColumn::make('documentType.name')
                    ->label('Jenis Dokumen')
                    ->badge(),
                TextColumn::make('expires_at')
                    ->label('Kedaluwarsa')
                    ->date('d M Y')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Diunggah')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Pratinjau')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (VehicleDocument $record): string => route('documents.preview', $record))
                    ->openUrlInNewTab(),
                Action::make('verify')
                    ->label('Verifikasi')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (VehicleDocument $record): void {
                        app(VerifyVehicleDocument::class)->execute($record, auth()->user());
                    }),
            ])
            ->emptyStateHeading('Tidak ada dokumen menunggu')
            ->emptyStateDescription('Semua dokumen kendaraan sudah diverifikasi.');
    }
}
