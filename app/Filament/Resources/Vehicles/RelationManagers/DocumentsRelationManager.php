<?php

namespace App\Filament\Resources\Vehicles\RelationManagers;

use App\Actions\Document\RejectVehicleDocument;
use App\Actions\Document\VerifyVehicleDocument;
use App\Enums\DocumentVerificationStatus;
use App\Models\VehicleDocument;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Dokumen Kendaraan';

    protected static ?string $modelLabel = 'Dokumen';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('original_filename')
            ->columns([
                TextColumn::make('documentType.name')
                    ->label('Jenis Dokumen')
                    ->sortable(),
                TextColumn::make('document_number')
                    ->label('Nomor')
                    ->placeholder('—'),
                TextColumn::make('expires_at')
                    ->label('Kedaluwarsa')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('verification_status')
                    ->label('Status Verifikasi')
                    ->badge()
                    ->formatStateUsing(fn (DocumentVerificationStatus $state) => $state->label())
                    ->color(fn (DocumentVerificationStatus $state) => match ($state) {
                        DocumentVerificationStatus::Pending => 'warning',
                        DocumentVerificationStatus::Verified => 'success',
                        DocumentVerificationStatus::Rejected => 'danger',
                    }),
                TextColumn::make('rejection_reason')
                    ->label('Alasan Penolakan')
                    ->placeholder('—')
                    ->limit(40)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('verifier.name')
                    ->label('Diverifikasi Oleh')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Diunggah')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('verification_status')
                    ->label('Status Verifikasi')
                    ->options(collect(DocumentVerificationStatus::cases())->mapWithKeys(
                        fn (DocumentVerificationStatus $s) => [$s->value => $s->label()]
                    )),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('preview')
                    ->label('Pratinjau')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (VehicleDocument $record) => route('documents.preview', $record))
                    ->openUrlInNewTab(),
                Action::make('download')
                    ->label('Unduh')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (VehicleDocument $record) => route('documents.download', $record)),
                Action::make('verify')
                    ->label('Verifikasi')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (VehicleDocument $record): bool => $record->verification_status !== DocumentVerificationStatus::Verified
                        && $record->replaced_by_id === null
                        && auth()->user()->can('verify_documents'))
                    ->action(function (VehicleDocument $record): void {
                        try {
                            app(VerifyVehicleDocument::class)->execute($record, auth()->user());
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Verifikasi gagal')
                                ->body(collect($e->errors())->flatten()->first())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Dokumen terverifikasi')
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (VehicleDocument $record): bool => $record->verification_status !== DocumentVerificationStatus::Rejected
                        && $record->replaced_by_id === null
                        && auth()->user()->can('verify_documents'))
                    ->schema([
                        Textarea::make('reason')
                            ->label('Alasan Penolakan')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->action(function (VehicleDocument $record, array $data): void {
                        try {
                            app(RejectVehicleDocument::class)->execute($record, auth()->user(), $data['reason']);
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Penolakan gagal')
                                ->body(collect($e->errors())->flatten()->first())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Dokumen ditolak')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Belum ada dokumen')
            ->emptyStateDescription('Transportir belum mengunggah dokumen untuk kendaraan ini.');
    }
}
