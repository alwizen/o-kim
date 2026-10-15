<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Perusahaan — O-KIM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-navy-50/60 font-sans text-slate-800 antialiased">

    <header class="border-b border-navy-100 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ route('landing') }}" class="flex items-center gap-3">
                <img src="{{ asset('logo.png') }}" alt="Logo O-KIM" class="h-10 w-auto">
                <div class="flex flex-col leading-none">
                    <span class="text-base font-bold text-navy-800">O-KIM</span>
                    <span class="text-[11px] text-slate-500">Fuel Terminal Tegal</span>
                </div>
            </a>
            <form method="POST" action="/logout" class="hidden sm:block">
                @csrf
                <button type="submit" class="rounded-full px-4 py-2 text-sm font-medium text-slate-500 transition hover:bg-navy-50 hover:text-navy-700">
                    Keluar
                </button>
            </form>
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
        <div class="reveal">
            <span class="inline-flex items-center gap-2 rounded-full border border-navy-100 bg-white px-3.5 py-1.5 text-xs font-semibold uppercase tracking-widest text-navy-600">
                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500"></span>
                Menunggu Verifikasi
            </span>
            <h1 class="mt-5 text-3xl font-bold tracking-tight text-navy-900">Profil Perusahaan Transportir</h1>
            <p class="mt-3 max-w-xl text-sm leading-relaxed text-slate-600">
                Lengkapi data perusahaan Anda. Data ini akan diverifikasi oleh admin Fuel Terminal Tegal
                sebelum akun diaktifkan.
            </p>
        </div>

        @if (session('success'))
            <div class="reveal mt-6 flex items-start gap-3 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                <x-heroicon-o-check-circle class="mt-0.5 h-5 w-5 shrink-0" />
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if (session('error'))
            <div class="reveal mt-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <x-heroicon-o-exclamation-triangle class="mt-0.5 h-5 w-5 shrink-0" />
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('registration.profile.update') }}"
              class="reveal mt-8 rounded-2xl border border-navy-100 bg-white p-6 shadow-sm sm:p-8">
            @csrf

            <div class="flex items-center gap-3 border-b border-navy-100 pb-5">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-navy-50 text-navy-600">
                    <x-heroicon-o-building-office-2 class="h-5 w-5" />
                </span>
                <div>
                    <h2 class="font-bold text-navy-900">Data Perusahaan</h2>
                    <p class="text-xs text-slate-500">Kolom bertanda <span class="text-red-500">*</span> wajib diisi.</p>
                </div>
            </div>

            <div class="mt-6 space-y-6">
                <div>
                    <label for="company_name" class="block text-sm font-semibold text-navy-800">
                        Nama Perusahaan <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="company_name" name="company_name" required
                           value="{{ old('company_name', $transporter->company_name ?? '') }}"
                           placeholder="Contoh: PT Angkutan Tegal Sejahtera"
                           class="mt-2 block w-full rounded-xl border border-navy-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm transition placeholder:text-slate-400 focus:border-navy-500 focus:outline-none focus:ring-2 focus:ring-navy-200">
                    @error('company_name') <p class="mt-2 flex items-center gap-1.5 text-sm text-red-600"><x-heroicon-o-exclamation-circle class="h-4 w-4" />{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="company_address" class="block text-sm font-semibold text-navy-800">
                        Alamat Perusahaan <span class="text-red-500">*</span>
                    </label>
                    <textarea id="company_address" name="company_address" rows="3" required
                              placeholder="Alamat lengkap perusahaan"
                              class="mt-2 block w-full rounded-xl border border-navy-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm transition placeholder:text-slate-400 focus:border-navy-500 focus:outline-none focus:ring-2 focus:ring-navy-200">{{ old('company_address', $transporter->company_address ?? '') }}</textarea>
                    @error('company_address') <p class="mt-2 flex items-center gap-1.5 text-sm text-red-600"><x-heroicon-o-exclamation-circle class="h-4 w-4" />{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <label for="phone" class="block text-sm font-semibold text-navy-800">
                            Nomor Telepon <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="phone" name="phone" required
                               value="{{ old('phone', $transporter->phone ?? '') }}"
                               placeholder="0283-xxxxxx"
                               class="mt-2 block w-full rounded-xl border border-navy-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm transition placeholder:text-slate-400 focus:border-navy-500 focus:outline-none focus:ring-2 focus:ring-navy-200">
                        @error('phone') <p class="mt-2 flex items-center gap-1.5 text-sm text-red-600"><x-heroicon-o-exclamation-circle class="h-4 w-4" />{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="legal_identifier" class="block text-sm font-semibold text-navy-800">
                            Nomor Identitas <span class="font-normal text-slate-400">(opsional)</span>
                        </label>
                        <input type="text" id="legal_identifier" name="legal_identifier"
                               value="{{ old('legal_identifier', $transporter->legal_identifier ?? '') }}"
                               placeholder="NIB / SIUP"
                               class="mt-2 block w-full rounded-xl border border-navy-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm transition placeholder:text-slate-400 focus:border-navy-500 focus:outline-none focus:ring-2 focus:ring-navy-200">
                        @error('legal_identifier') <p class="mt-2 flex items-center gap-1.5 text-sm text-red-600"><x-heroicon-o-exclamation-circle class="h-4 w-4" />{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="mt-8 flex flex-col gap-3 border-t border-navy-100 pt-6 sm:flex-row sm:items-center sm:justify-between">
                <p class="flex items-center gap-2 text-xs text-slate-500">
                    <x-heroicon-o-information-circle class="h-4 w-4 text-navy-400" />
                    Anda dapat memperbarui data ini sebelum akun disetujui.
                </p>
                <button type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-navy-700 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-navy-900/15 transition hover:-translate-y-0.5 hover:bg-navy-800 hover:shadow-xl">
                    <x-heroicon-o-paper-airplane class="h-4 w-4" />
                    Simpan Data Perusahaan
                </button>
            </div>
        </form>

        <div class="reveal mt-8 rounded-2xl border border-navy-100 bg-white p-6">
            <h2 class="flex items-center gap-2 text-sm font-bold text-navy-900">
                <x-heroicon-o-clipboard-document-list class="h-5 w-5 text-navy-500" />
                Langkah selanjutnya
            </h2>
            <ol class="mt-4 space-y-3 text-sm text-slate-600">
                @foreach ([
                    'Simpan profil perusahaan Anda.',
                    'Admin akan memeriksa data perusahaan.',
                    'Setelah disetujui, Anda dapat mengakses panel transportir.',
                    'Tambahkan kendaraan, unggah dokumen, lalu ajukan KIM.',
                ] as $index => $step)
                    <li class="flex gap-3">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-navy-50 text-xs font-bold text-navy-600">{{ $index + 1 }}</span>
                        {{ $step }}
                    </li>
                @endforeach
            </ol>
        </div>
    </main>

    <footer class="border-t border-navy-100 bg-white py-6">
        <p class="text-center text-xs text-slate-400">&copy; {{ date('Y') }} O-KIM Fuel Terminal Tegal</p>
    </footer>

</body>
</html>
