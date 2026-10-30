<?php

namespace App\Filament\Widgets;

use App\Enums\ApplicationStatus;
use App\Enums\ApprovalDecision;
use App\Filament\Resources\KimApplications\KimApplicationResource;
use App\Models\KimApplication;
use App\Support\Widget\DepartmentScope;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Antrean pengajuan yang menunggu approval fungsi Pengawas.
 */
class PengawasApprovalQueue extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Siap Di-approve Fungsi Saya';

    public static function canView(): bool
    {
        return DepartmentScope::isApprover();
    }

    public function table(Table $table): Table
    {
        $departments = DepartmentScope::approvableValues();

        return $table
            ->query(
                KimApplication::query()
                    ->with(['vehicle', 'transporter'])
                    ->whereIn('status', [
                        ApplicationStatus::PendingApproval->value,
                        ApplicationStatus::Inspection->value,
                        ApplicationStatus::RevisionRequired->value,
                    ])
                    ->when($departments === [], fn ($query) => $query->whereRaw('1 = 0'))
                    ->when($departments !== [], fn ($query) => $query->whereDoesntHave(
                        'approvals',
                        fn ($q) => $q
                            ->whereIn('department', $departments)
                            ->where('decision', ApprovalDecision::Approved->value),
                    ))
            )
            ->description('Pengajuan pada fungsi Anda yang belum Anda setujui.')
            ->defaultSort('submitted_at', 'asc')
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('application_number')
                    ->label('No. Pengajuan')
                    ->searchable(),
                TextColumn::make('vehicle.registration_number')
                    ->label('NOPOL')
                    ->badge(),
                TextColumn::make('transporter.company_name')
                    ->label('Perusahaan')
                    ->limit(30)
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('submitted_at')
                    ->label('Dikirim')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Buka Pengajuan')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('primary')
                    ->url(fn (KimApplication $record): string => KimApplicationResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading('Tidak ada antrean approval')
            ->emptyStateDescription('Semua pengajuan pada fungsi Anda sudah diputuskan.');
    }
}
