<?php

namespace App\Filament\Pages;

use App\Services\Setting\SettingsService;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class ManageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Pengaturan Sistem';

    protected static ?string $title = 'Pengaturan Sistem';

    protected static ?int $navigationSort = 14;

    protected string $view = 'filament.pages.manage-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()->can('manage_settings');
    }

    public function mount(): void
    {
        $settings = app(SettingsService::class);

        $this->form->fill([
            'kim_validity_months' => (string) $settings->kimValidityMonths(),
            'kim_number_prefix' => $settings->kimNumberPrefix(),
            'kim_number_period_format' => $settings->kimNumberPeriodFormat(),
            'kim_number_sequence_digits' => (string) $settings->kimNumberSequenceDigits(),
            'application_number_prefix' => $settings->applicationNumberPrefix(),
            'application_number_period_format' => $settings->applicationNumberPeriodFormat(),
            'application_number_sequence_digits' => (string) $settings->applicationNumberSequenceDigits(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Masa Berlaku KIM')
                    ->description('Batas maksimum masa berlaku KIM. Tanggal akhir tetap diambil yang paling awal antara nilai ini dan kedaluwarsa dokumen wajib.')
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                        Select::make('kim_validity_months')
                            ->label('Masa Berlaku (bulan)')
                            ->options([
                                '3' => '3 bulan',
                                '6' => '6 bulan',
                                '12' => '12 bulan',
                                '24' => '24 bulan',
                            ])
                            ->default('6')
                            ->required()
                            ->native(false),
                    ]),

                Section::make('Penomoran KIM')
                    ->description('Format nomor KIM yang akan dihasilkan saat penerbitan.')
                    ->icon('heroicon-o-hashtag')
                    ->columns(3)
                    ->schema([
                        TextInput::make('kim_number_prefix')
                            ->label('Prefix')
                            ->required()
                            ->live(onBlur: true)
                            ->maxLength(50)
                            ->helperText('Contoh: KIM/FT-TGL'),

                        Select::make('kim_number_period_format')
                            ->label('Format Periode')
                            ->options([
                                'Y/m' => 'Tahun/Bulan (2026/10)',
                                'Y' => 'Tahun (2026)',
                                'Ym' => 'TahunBulan (202610)',
                                'none' => 'Tanpa periode',
                            ])
                            ->default('Y/m')
                            ->required()
                            ->live()
                            ->native(false),

                        Select::make('kim_number_sequence_digits')
                            ->label('Digit Urutan')
                            ->options([
                                '3' => '3 digit (001)',
                                '4' => '4 digit (0001)',
                                '5' => '5 digit (00001)',
                            ])
                            ->default('4')
                            ->required()
                            ->live()
                            ->native(false),
                    ]),

                Section::make('Pratinjau Nomor KIM')
                    ->description('Nomor akan mengikuti urutan otomatis per periode.')
                    ->schema([
                        TextEntry::make('preview')
                            ->hiddenLabel()
                            ->state(fn ($get) => $this->buildPreview(
                                $get('kim_number_prefix'),
                                $get('kim_number_period_format'),
                                (int) $get('kim_number_sequence_digits'),
                            )),
                    ]),

                Section::make('Penomoran Pengajuan KIM')
                    ->description('Format nomor pengajuan yang dibuat saat transportir membuat pengajuan baru.')
                    ->icon('heroicon-o-document-plus')
                    ->columns(3)
                    ->schema([
                        TextInput::make('application_number_prefix')
                            ->label('Prefix')
                            ->required()
                            ->live(onBlur: true)
                            ->maxLength(50)
                            ->helperText('Contoh: APP'),

                        Select::make('application_number_period_format')
                            ->label('Format Periode')
                            ->options([
                                'Ymd' => 'Tanggal (20261014)',
                                'Y/m' => 'Tahun/Bulan (2026/10)',
                                'Y' => 'Tahun (2026)',
                                'none' => 'Tanpa periode',
                            ])
                            ->default('Ymd')
                            ->required()
                            ->live()
                            ->native(false),

                        Select::make('application_number_sequence_digits')
                            ->label('Digit Urutan')
                            ->options([
                                '3' => '3 digit (001)',
                                '4' => '4 digit (0001)',
                                '5' => '5 digit (00001)',
                            ])
                            ->default('4')
                            ->required()
                            ->live()
                            ->native(false),
                    ]),

                Section::make('Pratinjau Nomor Pengajuan')
                    ->description('Nomor akan mengikuti urutan otomatis per periode.')
                    ->schema([
                        TextEntry::make('application_preview')
                            ->hiddenLabel()
                            ->state(fn ($get) => $this->buildPreview(
                                $get('application_number_prefix'),
                                $get('application_number_period_format'),
                                (int) $get('application_number_sequence_digits'),
                            )),
                    ]),
            ]);
    }

    public function save(SettingsService $settings): void
    {
        $data = $this->form->getState();

        $settings->setMany([
            'kim.validity_months' => (string) $data['kim_validity_months'],
            'kim.number_prefix' => (string) $data['kim_number_prefix'],
            'kim.number_period_format' => (string) $data['kim_number_period_format'],
            'kim.number_sequence_digits' => (string) $data['kim_number_sequence_digits'],
            'application.number_prefix' => (string) $data['application_number_prefix'],
            'application.number_period_format' => (string) $data['application_number_period_format'],
            'application.number_sequence_digits' => (string) $data['application_number_sequence_digits'],
        ], SettingsService::GROUP_KIM);

        Notification::make()
            ->title('Pengaturan berhasil disimpan')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [];
    }

    private function buildPreview(?string $prefix, ?string $periodFormat, int $digits): HtmlString
    {
        $number = app(SettingsService::class)->buildNumberPreview(
            (string) $prefix,
            (string) $periodFormat,
            $digits,
        );

        return new HtmlString(
            '<span class="font-mono text-base font-semibold text-primary-600 dark:text-primary-400">'.
            e($number).
            '</span>'
        );
    }
}
