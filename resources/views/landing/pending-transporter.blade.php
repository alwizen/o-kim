<x-landing.auth-layout
    title="Menunggu Verifikasi"
    subtitle="Profil perusahaan Anda sudah kami terima dan sedang menunggu verifikasi admin."
>
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-5">
        <div class="flex items-start gap-3">
            <x-heroicon-o-clock class="mt-0.5 h-6 w-6 shrink-0 text-amber-600" />
            <div class="text-sm leading-relaxed text-amber-800">
                <p class="font-semibold">Pendaftaran sedang ditinjau</p>
                <p class="mt-1">
                    Admin Fuel Terminal Tegal akan memeriksa data perusahaan Anda. Anda akan
                    menerima notifikasi melalui email setelah akun disetujui atau ditolak.
                </p>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="mt-4 flex items-start gap-3 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">
            <x-heroicon-o-check-circle class="mt-0.5 h-5 w-5 shrink-0" />
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if ($transporter)
        <div class="mt-6 overflow-hidden rounded-xl border border-auth-100 bg-white">
            <div class="border-b border-auth-100 bg-auth-50/60 px-5 py-3">
                <h2 class="text-sm font-bold text-auth-900">Data perusahaan yang dikirim</h2>
            </div>
            <dl class="divide-y divide-auth-50 text-sm">
                @foreach ([
                    'Nama PIC' => auth()->user()->name,
                    'Perusahaan' => $transporter->company_name,
                    'Alamat' => $transporter->company_address,
                    'Telepon' => $transporter->phone,
                ] as $label => $value)
                    <div class="flex gap-4 px-5 py-3">
                        <dt class="w-40 shrink-0 text-slate-500">{{ $label }}</dt>
                        <dd class="font-medium text-slate-800">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <p class="mt-4 text-sm text-slate-500">
            Data terkunci selama proses verifikasi. Hubungi admin bila ada koreksi.
        </p>
    @endif

    <div class="mt-6 rounded-xl border border-auth-100 bg-white p-5">
        <h2 class="flex items-center gap-2 text-sm font-bold text-auth-900">
            <x-heroicon-o-clipboard-document-list class="h-5 w-5 text-auth-500" />
            Langkah selanjutnya
        </h2>
        <ol class="mt-4 space-y-3 text-sm text-slate-600">
            @foreach ([
                'Admin memeriksa data perusahaan Anda.',
                'Setelah disetujui, Anda dapat mengakses panel transportir.',
                'Tambahkan kendaraan, unggah dokumen, lalu ajukan KIM.',
            ] as $index => $step)
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-auth-50 text-xs font-bold text-auth-600">{{ $index + 1 }}</span>
                    {{ $step }}
                </li>
            @endforeach
        </ol>
    </div>

    <x-slot:footer>
        <div class="mt-8 flex items-center justify-between text-sm">
            <a href="{{ route('landing') }}" class="text-slate-400 transition hover:text-auth-600">&larr; Beranda</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="font-medium text-slate-600 transition hover:text-auth-600">Keluar</button>
            </form>
        </div>
    </x-slot:footer>
</x-landing.auth-layout>
