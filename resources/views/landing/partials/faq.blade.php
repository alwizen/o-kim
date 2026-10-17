    {{-- FAQ --}}
    <section id="faq" class="scroll-mt-24 bg-lavender-50/60 py-20 sm:py-28">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <x-landing.section-heading eyebrow="FAQ" title="Pertanyaan yang sering diajukan" />

            <div class="mt-12 space-y-3">
                @php
                    $faqs = [
                        ['q' => 'Siapa yang dapat mendaftar O-KIM?', 'a' => 'Perusahaan transportir yang akan mengoperasikan kendaraan tangki di Fuel Terminal Tegal. Pendaftaran dilakukan oleh PIC menggunakan email & kata sandi atau akun Google perusahaan.'],
                        ['q' => 'Berapa lama proses verifikasi akun?', 'a' => 'Setelah profil perusahaan dilengkapi, admin akan memeriksa data Anda. Anda akan menerima notifikasi setelah pendaftaran disetujui atau ditolak.'],
                        ['q' => 'Apakah KIM masih berbentuk kartu fisik?', 'a' => 'KIM diterbitkan secara digital dalam bentuk PDF dengan nomor unik dan QR Code. Kartu dapat diunduh dan dicetak bila diperlukan.'],
                        ['q' => 'Bagaimana cara memverifikasi keaslian KIM?', 'a' => 'Pindai QR Code pada KIM. Anda akan diarahkan ke halaman validasi resmi yang menampilkan status terkini: aktif, kedaluwarsa, atau dicabut.'],
                        ['q' => 'Apa yang terjadi bila ditemukan temuan pemeriksaan?', 'a' => 'Pengajuan tidak dapat lanjut hingga temuan diselesaikan. Anda mengirim tindak lanjut beserta bukti, lalu petugas yang berwenang memverifikasinya.'],
                    ];
                @endphp

                @foreach ($faqs as $index => $faq)
                    <details class="reveal group rounded-xl border border-lavender-100 bg-white p-5 open:shadow-md" style="--reveal-delay:{{ $index * 60 }}ms">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-lavender-900">
                            {{ $faq['q'] }}
                            <x-heroicon-o-chevron-down class="h-5 w-5 shrink-0 text-lavender-400 transition-transform duration-300 group-open:rotate-180" />
                        </summary>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ $faq['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>
