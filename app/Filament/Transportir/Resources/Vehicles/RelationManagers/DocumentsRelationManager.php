<?php

namespace App\Filament\Transportir\Resources\Vehicles\RelationManagers;

use App\Enums\DocumentVerificationStatus;
use App\Models\DocumentType;
use App\Models\VehicleDocument;
use App\Services\Document\VehicleDocumentService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Dokumen Kendaraan';

    protected static ?string $modelLabel = 'Dokumen';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('document_type_id')
                ->label('Jenis Dokumen')
                ->options(DocumentType::where('is_active', true)->pluck('name', 'id'))
                ->required()
                ->live(),
            TextInput::make('document_number')
                ->label('Nomor Dokumen')
                ->maxLength(100),
            DatePicker::make('issued_at')
                ->label('Tanggal Terbit'),
            DatePicker::make('expires_at')
                ->label('Tanggal Kedaluwarsa')
                ->visible(fn ($get) => (bool) DocumentType::find($get('document_type_id'))?->has_expiry_date)
                ->required(fn ($get) => (bool) DocumentType::find($get('document_type_id'))?->has_expiry_date),
            FileUpload::make('file')
                ->label('Berkas (PDF/JPG/PNG, maks 5 MB)')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                ->maxSize(5120)
                ->disk('local')
                ->required()
                ->storeFiles(false),
        ]);
    }

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
                TextColumn::make('created_at')
                    ->label('Diunggah')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Unggah Dokumen')
                    ->createAnother(false)
                    ->using(function (array $data): VehicleDocument {
                        $file = $data['file'];
                        unset($data['file']);

                        return app(VehicleDocumentService::class)->upload(
                            $this->getOwnerRecord(),
                            (int) $data['document_type_id'],
                            $file,
                            $data
                        );
                    }),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Unduh')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (VehicleDocument $record) => route('documents.download', $record))
                    ->openUrlInNewTab(),
                DeleteAction::make()
                    ->using(fn (VehicleDocument $record) => app(VehicleDocumentService::class)->delete($record)),
            ]);
    }
}
