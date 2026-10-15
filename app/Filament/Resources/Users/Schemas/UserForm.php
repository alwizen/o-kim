<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\AccountStatus;
use App\Enums\InternalRole;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data Akun')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nama')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    TextInput::make('password')
                        ->label('Kata Sandi')
                        ->password()
                        ->revealable()
                        ->rules(fn (?string $state): array => filled($state)
                            ? [Password::min(8)->letters()->mixedCase()->numbers()]
                            : [])
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->helperText(fn (string $operation): ?string => $operation === 'edit'
                            ? 'Biarkan kosong bila tidak ingin mengubah kata sandi.'
                            : 'Minimal 8 karakter, kombinasi huruf besar, huruf kecil, dan angka.')
                        ->columnSpanFull(),
                    TextInput::make('password_confirmation')
                        ->label('Konfirmasi Kata Sandi')
                        ->password()
                        ->revealable()
                        ->same('password')
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(false)
                        ->columnSpanFull(),
                ]),

            Section::make('Peran & Status')
                ->columns(2)
                ->schema([
                    CheckboxList::make('roles')
                        ->label('Role')
                        ->options(InternalRole::options())
                        ->descriptions(collect(InternalRole::cases())->mapWithKeys(
                            fn (InternalRole $role) => [$role->value => $role->label()]
                        )->all())
                        ->required()
                        ->columns(2)
                        ->columnSpanFull()
                        ->formatStateUsing(function ($component, $state) {
                            // Isi dari relasi Spatie (nama role) saat halaman dibuka.
                            $record = $component->getRecord();

                            if (! $record) {
                                return [];
                            }

                            return $record->roles
                                ->pluck('name')
                                ->filter(fn (string $name) => in_array($name, InternalRole::values(), true))
                                ->values()
                                ->all();
                        })
                        ->dehydrated(false)
                        ->saveRelationshipsUsing(function ($component, $state) {
                            $record = $component->getRecord();

                            if (! $record) {
                                return;
                            }

                            $roles = collect((array) $state)
                                ->filter(fn ($name) => in_array($name, InternalRole::values(), true))
                                ->values()
                                ->all();

                            $record->syncRoles($roles);
                        }),
                    Select::make('account_status')
                        ->label('Status Akun')
                        ->options(collect(AccountStatus::cases())->mapWithKeys(
                            fn (AccountStatus $status) => [$status->value => $status->label()]
                        ))
                        ->default(AccountStatus::Approved->value)
                        ->required()
                        ->native(false),
                ]),
        ]);
    }
}
