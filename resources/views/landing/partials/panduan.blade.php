    {{-- Panduan --}}
    <section id="panduan" class="scroll-mt-24 bg-white py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-14 lg:grid-cols-2 lg:items-center lg:gap-20">
                <div>
                    <x-landing.section-heading align="left" eyebrow="Panduan" title="Empat langkah menuju KIM terbit" />

                    <ol class="mt-10 space-y-8">
                        @php
                            $steps = [
                                ['title' => 'Daftar akun', 'desc' => 'Gunakan email & kata sandi atau akun Google perusahaan. Akun baru berstatus menunggu verifikasi.'],
                                ['title' => 'Lengkapi profil perusahaan', 'desc' => 'Isi data perusahaan transportir Anda. Admin akan memeriksa dan menyetujui pendaftaran.'],
                                ['title' => 'Ajukan kendaraan & dokumen', 'desc' => 'Tambahkan kendaraan tangki, unggah dokumen wajib, lalu kirim pengajuan untuk diperiksa.'],
                                ['title' => 'Terima KIM digital', 'desc' => 'Setelah seluruh pemeriksaan dan approval selesai, KIM terbit dan dapat diunduh beserta QR validasi.'],
                            ];
                        @endphp

                        @foreach ($steps as $index => $step)
                            <li class="reveal relative flex gap-5" style="--reveal-delay:{{ $index * 90 }}ms">
                                <div class="flex flex-col items-center">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border-2 border-lavender-200 bg-white text-sm font-bold text-lavender-700">{{ $index + 1 }}</span>
                                    @if (! $loop->last)
                                        <span class="mt-1 h-full w-px flex-1 bg-gradient-to-b from-lavender-200 to-transparent"></span>
                                    @endif
                                </div>
                                <div class="pb-2">
                                    <h3 class="text-base font-bold text-lavender-900">{{ $step['title'] }}</h3>
                                    <p class="mt-1 text-sm leading-relaxed text-slate-600">{{ $step['desc'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>

                <div class="reveal relative" style="--reveal-delay:120ms">
                    <div class="rounded-3xl border border-lavender-200 bg-pastel p-8 text-lavender-900 shadow-xl shadow-lavender-900/10 sm:p-10">
                        <x-heroicon-o-sparkles class="h-8 w-8 text-lavender-600" />
                        <h3 class="mt-5 text-2xl font-bold">Pantau status, kapan saja</h3>
                        <p class="mt-3 text-sm leading-relaxed text-lavender-700">
                            Setiap perubahan status tercatat dan dapat ditelusuri. Anda akan tahu persis
                            di tahap mana pengajuan berada — pemeriksaan, tindak lanjut, atau approval.
                        </p>

                        <div class="mt-8 space-y-4">
                            @foreach ([
                                ['label' => 'Dikirim', 'state' => 'done'],
                                ['label' => 'Pemeriksaan HSSE · Distribusi · QQ', 'state' => 'done'],
                                ['label' => 'Approval Final FTM', 'state' => 'current'],
                                ['label' => 'KIM Terbit', 'state' => 'pending'],
                            ] as $track)
                                <div class="flex items-center gap-3">
                                    @if ($track['state'] === 'done')
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-lavender-600 text-white"><x-heroicon-o-check class="h-4 w-4" /></span>
                                    @elseif ($track['state'] === 'current')
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full border-2 border-lavender-400 bg-transparent">
                                            <span class="h-2 w-2 animate-pulse rounded-full bg-lavender-500"></span>
                                        </span>
                                    @else
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full border-2 border-lavender-300"></span>
                                    @endif
                                    <span class="text-sm {{ $track['state'] === 'pending' ? 'text-lavender-400' : 'text-lavender-900' }}">{{ $track['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
