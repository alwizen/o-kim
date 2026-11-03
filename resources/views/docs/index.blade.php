<x-landing-auth-layout title="Panduan Penggunaan">
    <div class="mx-auto max-w-6xl">

        {{-- Heading --}}
        <div class="text-center">
            <span class="inline-flex items-center gap-2 rounded-full border border-auth-100 bg-auth-50 px-3.5 py-1.5 text-xs font-semibold uppercase tracking-widest text-auth-600">
                <span class="h-1.5 w-1.5 rounded-full bg-auth-500"></span>
                Dokumentasi
            </span>
            <h1 class="mt-5 text-3xl font-bold tracking-tight text-auth-900 sm:text-4xl">
                Panduan Penggunaan O-KIM
            </h1>
            <p class="mx-auto mt-4 max-w-2xl text-base leading-relaxed text-slate-600">
                Pilih panduan sesuai peran Anda. Setiap dokumen menjelaskan alur kerja, syarat,
                dan langkah-langkah di sistem O-KIM.
            </p>
        </div>

        {{-- Kartu panduan --}}
        <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($panduan as $slug => $item)
                <a href="{{ route('panduan.show', $slug) }}"
                   class="group relative flex flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-auth-300 hover:shadow-xl hover:shadow-auth-900/5">
                    <div class="flex items-center justify-between">
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-auth-50 text-auth-600 transition group-hover:bg-auth-600 group-hover:text-white">
                            <x-heroicon-o-book-open class="h-5 w-5" />
                        </span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                            {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                        </span>
                    </div>

                    <h2 class="mt-4 text-base font-bold text-auth-900 group-hover:text-auth-700">
                        {{ $item['judul'] }}
                    </h2>
                    <p class="mt-2 flex-1 text-sm leading-relaxed text-slate-600">
                        {{ $item['ringkas'] }}
                    </p>

                    <span class="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-auth-600">
                        Baca panduan
                        <x-heroicon-m-arrow-right class="h-4 w-4 transition group-hover:translate-x-0.5" />
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</x-landing-auth-layout>
