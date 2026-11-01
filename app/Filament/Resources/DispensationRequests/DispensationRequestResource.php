<?php

namespace App\Filament\Resources\DispensationRequests;

use App\Enums\InternalRole;
use App\Filament\Resources\DispensationRequests\Pages\ListDispensationRequests;
use App\Filament\Resources\DispensationRequests\Tables\DispensationRequestsTable;
use App\Models\FindingDispensationRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Antrean permohonan KIM Sementara (dispensasi temuan) yang diajukan transportir.
 *
 * Hanya Fuel Terminal Manager yang dapat memutuskan permohonan di sini.
 */
class DispensationRequestResource extends Resource
{
    protected static ?string $model = FindingDispensationRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $navigationLabel = 'Pengajuan KIM Sementara';

    protected static ?string $modelLabel = 'Pengajuan KIM Sementara';

    protected static ?string $pluralModelLabel = 'Pengajuan KIM Sementara';

    protected static string|UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'reason';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(InternalRole::Ftm->value)
            && auth()->user()->can('dispensate_findings');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['reason'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'No. Pengajuan' => $record->finding?->application?->application_number ?? '—',
            'Status' => $record->status->label(),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('finding.application');
    }

    /**
     * Seluruh permohonan ditampilkan agar riwayat keputusan FTM tetap terekam
     * dan dapat dipakai sebagai bahan laporan. Antrean yang belum diputuskan
     * disaring lewat filter status di tabel.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'finding.application.vehicle',
                'finding.application.transporter',
                'finding.inspectionResult.item',
                'requester',
            ]);
    }

    public static function table(Table $table): Table
    {
        return DispensationRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDispensationRequests::route('/'),
        ];
    }

    // public static function getNavigationBadge(): ?string
    // {
    //     $count = static::getEloquentQuery()->count();

    //     return $count > 0 ? (string) $count : null;
    // }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
