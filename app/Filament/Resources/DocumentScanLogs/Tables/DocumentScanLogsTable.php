<?php

namespace App\Filament\Resources\DocumentScanLogs\Tables;

use App\Enums\Department;
use App\Enums\DocumentScanType;
use App\Models\DocumentScanLog;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DocumentScanLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('scanned_at', 'desc')
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('scanned_at')
                    ->label('Waktu Pindai')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('document_type')
                    ->label('Jenis Dokumen')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof DocumentScanType ? $state->label() : (string) $state)
                    ->color(fn ($state) => $state === DocumentScanType::Kim ? 'info' : 'success'),
                TextColumn::make('document_number')
                    ->label('Nomor Dokumen')
                    ->state(fn (DocumentScanLog $record) => $record->documentNumber()),
                TextColumn::make('signer_name')
                    ->label('Penanda Tangan / Penerbit')
                    ->state(fn (DocumentScanLog $record) => $record->signerName()),
                TextColumn::make('signature.department')
                    ->label('Fungsi')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state) => $state instanceof Department ? $state->label() : (string) $state)
                    ->toggleable(),
                TextColumn::make('registration_number')
                    ->label('NOPOL')
                    ->state(fn (DocumentScanLog $record) => $record->registrationNumber())
                    ->badge(),
                TextColumn::make('ip_address')
                    ->label('IP Pemindai')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('user_agent')
                    ->label('User Agent')
                    ->limit(40)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('document_type')
                    ->label('Jenis Dokumen')
                    ->options(collect(DocumentScanType::cases())
                        ->mapWithKeys(fn (DocumentScanType $type) => [$type->value => $type->label()])
                        ->all()),
                SelectFilter::make('department')
                    ->label('Fungsi')
                    ->options(collect(Department::approvalSteps())->mapWithKeys(
                        fn (Department $department) => [$department->value => $department->label()]
                    )->all())
                    ->query(fn (Builder $query, array $data) => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $q) => $q->whereHas(
                            'signature',
                            fn (Builder $sub) => $sub->where('department', $data['value']),
                        ),
                    )),
                Filter::make('scanned_at')
                    ->label('Tanggal Pindai')
                    ->schema([
                        DatePicker::make('from')->label('Dari')->native(false),
                        DatePicker::make('until')->label('Sampai')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when(
                            filled($data['from'] ?? null),
                            fn (Builder $q) => $q->whereDate('scanned_at', '>=', $data['from']),
                        )
                        ->when(
                            filled($data['until'] ?? null),
                            fn (Builder $q) => $q->whereDate('scanned_at', '<=', $data['until']),
                        )),
            ]);
    }
}
