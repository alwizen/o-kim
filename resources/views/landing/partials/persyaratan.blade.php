    {{-- Persyaratan --}}
    <section id="persyaratan" class="scroll-mt-24 bg-navy-50/60 py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-landing.section-heading
                eyebrow="Persyaratan"
                title="Siapkan berkas sebelum mengajukan"
                description="Pastikan data perusahaan dan dokumen kendaraan lengkap agar pengajuan dapat diproses tanpa hambatan."
            />

            <div class="mt-14 grid gap-6 lg:grid-cols-3">
                <div class="reveal rounded-2xl border border-navy-100 bg-white p-7">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-navy-700 text-sm font-bold text-white">1</span>
                        <h3 class="font-bold text-navy-900">Akun & Perusahaan</h3>
                    </div>
                    <ul class="mt-5 space-y-3 text-sm text-slate-600">
                        @foreach (['Akun Google aktif milik PIC', 'Nama dan alamat perusahaan', 'Nomor telepon perusahaan', 'Nomor identitas perusahaan (bila ada)'] as $item)
                            <li class="flex gap-3"><x-heroicon-o-check class="mt-0.5 h-5 w-5 shrink-0 text-navy-500" /> {{ $item }}</li>
                        @endforeach
                    </ul>
                </div>

                <div class="reveal rounded-2xl border border-navy-100 bg-white p-7" style="--reveal-delay:80ms">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-navy-700 text-sm font-bold text-white">2</span>
                        <h3 class="font-bold text-navy-900">Data Kendaraan</h3>
                    </div>
                    <ul class="mt-5 space-y-3 text-sm text-slate-600">
                        @foreach (['Nomor polisi (NOPOL) kendaraan tangki', 'Merek, model, dan tahun pembuatan', 'Kapasitas tangki', 'Data kendaraan tercatat rapi'] as $item)
                            <li class="flex gap-3"><x-heroicon-o-check class="mt-0.5 h-5 w-5 shrink-0 text-navy-500" /> {{ $item }}</li>
                        @endforeach
                    </ul>
                </div>

                <div class="reveal rounded-2xl border border-navy-100 bg-white p-7" style="--reveal-delay:160ms">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-navy-700 text-sm font-bold text-white">3</span>
                        <h3 class="font-bold text-navy-900">Dokumen Wajib</h3>
                    </div>
                    <ul class="mt-5 space-y-3 text-sm text-slate-600">
                        @foreach (['STNK — Surat Tanda Nomor Kendaraan', 'KEUR — Keterangan Uji Kendaraan', 'TERA — Surat Tera Tangki', 'PTO — bila dipersyaratkan'] as $item)
                            <li class="flex gap-3"><x-heroicon-o-check class="mt-0.5 h-5 w-5 shrink-0 text-navy-500" /> {{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <p class="reveal mx-auto mt-8 max-w-3xl text-center text-xs leading-relaxed text-slate-500">
                Format berkas yang diterima: PDF, JPG, atau PNG dengan ukuran maksimum 5 MB per dokumen.
                Daftar dokumen wajib dapat disesuaikan oleh admin melalui master data.
            </p>
        </div>
    </section>
