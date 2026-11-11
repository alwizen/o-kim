<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Tanda Tangan — Fuel Terminal Tegal</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-50 text-gray-900 antialiased">
    <div class="mx-auto max-w-md px-4 py-16">
        <div class="rounded-xl bg-white p-8 shadow-sm">
            <div class="text-center">
                <h1 class="text-xl font-bold text-blue-900">Verifikasi Tanda Tangan</h1>
                <p class="mt-1 text-sm text-gray-500">{{ $documentType ?? 'Dokumen' }} — Fuel Terminal Tegal</p>
            </div>

            @if ($signature)
                <div class="mt-6 rounded-lg bg-green-50 p-3 text-center text-sm font-semibold text-green-800">
                    Dokumen sah dan terverifikasi
                </div>

                <dl class="mt-6 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Nama</dt>
                        <dd class="text-right font-semibold">{{ $signature->name }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Jabatan</dt>
                        <dd class="text-right font-semibold">{{ $signature->jabatan ?: $signature->department->label() }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Fungsi</dt>
                        <dd class="text-right font-semibold">{{ $signature->department->label() }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Waktu Approval</dt>
                        <dd class="text-right font-semibold">{{ $signature->decided_at?->translatedFormat('d F Y H:i') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Jenis Dokumen</dt>
                        <dd class="text-right font-semibold">{{ $documentType }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Nomor Dokumen</dt>
                        <dd class="text-right font-semibold">{{ $documentNumber ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">No. Polisi</dt>
                        <dd class="text-right font-semibold">{{ $application?->vehicle?->registration_number ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Perusahaan</dt>
                        <dd class="text-right font-semibold">{{ $application?->transporter?->company_name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">IP Approval</dt>
                        <dd class="text-right font-mono text-xs">{{ $signature->ip_address ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Hash PDF (SHA-256)</dt>
                        <dd class="break-all text-right font-mono text-[10px]">{{ $documentHash ?? '—' }}</dd>
                    </div>
                </dl>

                @if ($legalReference)
                    <p class="mt-6 border-t border-gray-200 pt-4 text-xs text-gray-500">
                        Tanda tangan elektronik ini sah berdasarkan {{ $legalReference }}
                    </p>
                @endif
            @else
                <div class="mt-6 rounded-lg bg-red-50 p-4 text-sm text-red-800">
                    Tanda tangan tidak ditemukan. Pastikan QR Code yang dipindai adalah resmi dari Fuel Terminal Tegal.
                </div>
            @endif
        </div>
        <p class="mt-4 text-center text-xs text-gray-400">O-KIM — Sistem Kartu Izin Masuk Digital</p>
    </div>
</body>
</html>
