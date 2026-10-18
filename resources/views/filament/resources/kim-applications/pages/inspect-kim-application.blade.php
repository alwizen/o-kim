<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex gap-3"><br>
            <x-filament::button type="submit">
                Simpan Hasil Pemeriksaan
            </x-filament::button>
            <x-filament::button color="gray" :href="$this->getResource()::getUrl('view', ['record' => $this->record])" tag="a">
                Kembali
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
