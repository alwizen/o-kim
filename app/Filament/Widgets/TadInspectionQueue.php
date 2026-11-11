<?php

namespace App\Filament\Widgets;

use App\Enums\ApplicationStatus;
use App\Filament\Resources\KimApplications\KimApplicationResource;
use App\Models\InspectionItem;
use App\Models\KimApplication;
use App\Support\Widget\DepartmentScope;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Antrean pengajuan yang siap diperiksa oleh TAD pada fungsinya sendiri.
 *
 * Muncul bila jadwal pemeriksaan sudah tiba dan checklist fungsi tersebut
 * belum lengkap.
 */
class TadInspectionQueue extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Antrean Pemeriksaan Saya';

    public static function canView(): bool
    {
        return DepartmentScope::isInspector();
    }

    public function table(Table $table): Table
    {
        $departments = DepartmentScope::inspectableValues();

        $requiredItemIds = $departments === []
            ? collect()
            : InspectionItem::query()
                ->whereHas('checklist', fn ($query) => $query
                    ->whereIn('department', $departments)
                    ->where('is_active', true))
                ->where('is_active', true)
                ->where('is_required', true)
                ->pluck('id');

        return $table
            ->query(
                KimApplication::query()
                    ->with(['vehicle', 'transporter'])
                    ->whereIn('status', [
                        ApplicationStatus::Submitted->value,
                        ApplicationStatus::Inspection->value,
                        ApplicationStatus::RevisionRequired->value,
                    ])
                    ->whereNotNull('inspection_scheduled_at')
                    ->where('inspection_scheduled_at', '<=', now())
                    ->when($requiredItemIds->isNotEmpty(), fn ($query) => $query->whereHas(
                        'inspectionResults',
                        fn ($q) => $q->whereIn('inspection_item_id', $requiredItemIds),
                        '<',
                        $requiredItemIds->count(),
                    ))
            )
            ->description('Jadwal pemeriksaan sudah tiba dan checklist fungsi Anda belum lengkap.')
            ->defaultSort('inspection_scheduled_at', 'asc')
            ->paginated([10, 25])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('application_number')
                    ->label('No. Pengajuan'),
                TextColumn::make('vehicle.registration_number')
                    ->label('NOPOL')
                    ->badge(),
                TextColumn::make('transporter.company_name')
                    ->label('Perusahaan')
                    ->limit(30)
                    ->wrap(),
                TextColumn::make('inspection_scheduled_at')
                    ->label('Jadwal')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label()),
            ])
            ->recordActions([
                Action::make('inspect')
                    ->label('Isi Checklist')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('primary')
                    ->url(fn (KimApplication $record): string => KimApplicationResource::getUrl('inspect', ['record' => $record])),
            ])
            ->emptyStateHeading('Tidak ada antrean pemeriksaan')
            ->emptyStateDescription('Semua pengajuan pada fungsi Anda sudah diperiksa.');
    }
}
