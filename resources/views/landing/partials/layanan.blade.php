    {{-- Layanan --}}
    <section id="layanan" class="scroll-mt-24 bg-white py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-landing.section-heading
                eyebrow="Layanan O-KIM"
                title="Satu sistem untuk seluruh proses perizinan"
                description="Menggantikan alur manual dengan workflow digital yang terstruktur, transparan, dan tercatat."
            />

            <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @php
                    $services = [
                        ['icon' => 'o-building-office-2', 'title' => 'Pendaftaran Transportir', 'desc' => 'Daftar via akun Google, lengkapi profil perusahaan, dan dapatkan verifikasi resmi dari admin.'],
                        ['icon' => 'o-truck', 'title' => 'Data Kendaraan & Dokumen', 'desc' => 'Kelola armada tangki beserta dokumen wajib seperti STNK, KEUR, dan TERA dengan riwayat versi.'],
                        ['icon' => 'o-clipboard-document-check', 'title' => 'Pemeriksaan Terpadu', 'desc' => 'HSSE, Distribusi, dan QQ memeriksa melalui checklist digital yang dapat dikelola tanpa ubah kode.'],
                        ['icon' => 'o-wrench-screwdriver', 'title' => 'Tindak Lanjut Temuan', 'desc' => 'Temuan ditindaklanjuti dengan bukti, lalu diverifikasi petugas sebelum dinyatakan selesai.'],
                        ['icon' => 'o-check-badge', 'title' => 'Approval Berjenjang', 'desc' => 'Persetujuan tiga fungsi, dilanjutkan approval final oleh Fuel Terminal Manager.'],
                        ['icon' => 'o-identification', 'title' => 'KIM Digital & QR Code', 'desc' => 'Kartu Izin Masuk terbit otomatis dengan nomor unik, masa berlaku, dan QR validasi publik.'],
                    ];
                @endphp

                @foreach ($services as $index => $service)
                    <article class="reveal group relative overflow-hidden rounded-2xl border border-ocean-100 bg-white p-7 transition-all duration-300 hover:-translate-y-1 hover:border-ocean-200 hover:shadow-xl hover:shadow-ocean-900/5"
                             style="--reveal-delay:{{ ($index % 3) * 80 }}ms">
                        <div class="absolute -right-8 -top-8 h-24 w-24 rounded-full bg-ocean-50 opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
                        <div class="relative flex h-12 w-12 items-center justify-center rounded-xl bg-ocean-50 text-ocean-600 transition-colors duration-300 group-hover:bg-ocean-600 group-hover:text-white">
                            <x-dynamic-component :component="'heroicon-'.$service['icon']" class="h-6 w-6" />
                        </div>
                        <h3 class="relative mt-5 text-lg font-bold text-ocean-900">{{ $service['title'] }}</h3>
                        <p class="relative mt-2 text-sm leading-relaxed text-slate-600">{{ $service['desc'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
