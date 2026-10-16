    {{-- Stats strip --}}
    <section class="border-b border-navy-100 bg-white">
        <div class="mx-auto grid max-w-7xl grid-cols-2 divide-navy-100 px-4 sm:px-6 lg:grid-cols-4 lg:divide-x lg:px-8">
            @foreach ([
                ['icon' => 'o-user-group', 'value' => 'Transportir', 'label' => 'Kelola data & dokumen'],
                ['icon' => 'o-clipboard-document-check', 'value' => 'HSSE · Distribusi · QQ', 'label' => 'Pemeriksaan terpadu'],
                ['icon' => 'o-qr-code', 'value' => 'QR Code', 'label' => 'Validasi keaslian'],
                ['icon' => 'o-document-text', 'value' => 'Digital', 'label' => 'KIM siap unduh'],
            ] as $stat)
                <div class="reveal flex flex-col gap-2 px-2 py-8 text-center lg:px-8">
                    <x-dynamic-component :component="'heroicon-'.$stat['icon']" class="mx-auto h-6 w-6 text-navy-500" />
                    <p class="mt-1 text-base font-bold text-navy-800">{{ $stat['value'] }}</p>
                    <p class="text-sm text-slate-500">{{ $stat['label'] }}</p>
                </div>
            @endforeach
        </div>
    </section>
