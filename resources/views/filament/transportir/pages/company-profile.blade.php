<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-6 dark:border-white/10">
            <br>
            <x-filament::button type="submit" size="lg">
                Simpan Profil
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
