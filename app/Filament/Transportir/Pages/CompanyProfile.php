<?php

namespace App\Filament\Transportir\Pages;

use App\Models\Transporter;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CompanyProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Profil Perusahaan';

    protected static ?string $title = 'Profil Perusahaan';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.transportir.pages.company-profile';

    public ?array $data = [];

    public function mount(): void
    {
        $transporter = $this->getTransporter();

        $this->form->fill(array_merge(
            ['pic_name' => auth()->user()->name],
            $transporter?->only([
                'company_name',
                'company_address',
                'phone',
                'legal_identifier',
            ]) ?? []
        ));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Penanggung Jawab (PIC)')
                    ->description('Nama PIC ini juga digunakan sebagai nama akun Anda.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('pic_name')
                            ->label('Nama PIC')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
                Section::make('Data Perusahaan')
                    ->description('Informasi ini digunakan pada dokumen pengajuan dan penerbitan KIM.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('company_name')
                            ->label('Nama Perusahaan')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('company_address')
                            ->label('Alamat Perusahaan')
                            ->required()
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        TextInput::make('phone')
                            ->label('Nomor Telepon')
                            ->required()
                            ->maxLength(30),
                        TextInput::make('legal_identifier')
                            ->label('Nomor Identitas Perusahaan')
                            ->helperText('NIB / SIUP (opsional)')
                            ->maxLength(255),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $transporter = $this->getTransporter();

        abort_if(! $transporter, 403, 'Profil perusahaan belum terdaftar.');

        $user = auth()->user();

        $user->update(['name' => $data['pic_name']]);

        $transporter->update(collect($data)->except('pic_name')->all());

        Notification::make()
            ->title('Profil perusahaan berhasil disimpan')
            ->success()
            ->send();
    }

    private function getTransporter(): ?Transporter
    {
        return auth()->user()->transporters()->first();
    }
}
