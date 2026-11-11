<?php

namespace App\Filament\Resources\DocumentScanLogs;

use App\Filament\Resources\DocumentScanLogs\Pages\ListDocumentScanLogs;
use App\Filament\Resources\DocumentScanLogs\Tables\DocumentScanLogsTable;
use App\Models\DocumentScanLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Riwayat pemindaian QR verifikasi dokumen (Berita Acara & Kartu KIM).
 * Read-only.
 */
class DocumentScanLogResource extends Resource
{
    protected static ?string $model = DocumentScanLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?string $navigationLabel = 'Log Verifikasi QR';

    protected static ?string $modelLabel = 'Log Verifikasi QR';

    protected static ?string $pluralModelLabel = 'Log Verifikasi QR';

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 15;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_audit_logs') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return DocumentScanLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentScanLogs::route('/'),
        ];
    }
}
