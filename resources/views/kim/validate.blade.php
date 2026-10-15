<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validasi KIM — Fuel Terminal Tegal</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-50 text-gray-900 antialiased">
    <div class="mx-auto max-w-md px-4 py-16">
        <div class="rounded-xl bg-white p-8 shadow-sm text-center">
            <h1 class="text-xl font-bold text-blue-900">Validasi Kartu Izin Masuk</h1>
            <p class="mt-1 text-sm text-gray-500">Fuel Terminal Tegal</p>

            @if ($card)
                @php
                    $statusColors = [
                        'active' => 'bg-green-100 text-green-800',
                        'expired' => 'bg-yellow-100 text-yellow-800',
                        'revoked' => 'bg-red-100 text-red-800',
                    ];
                @endphp
                <div class="mt-6">
                    <span class="inline-block rounded-full px-4 py-1 text-sm font-semibold {{ $statusColors[$currentStatus->value] }}">
                        {{ $currentStatus->label() }}
                    </span>
                </div>
                <dl class="mt-6 space-y-3 text-left text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Nomor KIM</dt><dd class="font-semibold">{{ $card->kim_number }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">NOPOL</dt><dd class="font-semibold">{{ $card->application->vehicle->registration_number }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Terminal</dt><dd class="font-semibold">Fuel Terminal Tegal</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Tanggal Terbit</dt><dd class="font-semibold">{{ $card->issued_at->format('d M Y') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Berlaku Hingga</dt><dd class="font-semibold">{{ $card->expires_at->format('d M Y') }}</dd></div>
                </dl>
            @else
                <div class="mt-6 rounded-lg bg-red-50 p-4 text-sm text-red-800">
                    KIM tidak ditemukan. Pastikan QR Code yang dipindai adalah resmi dari Fuel Terminal Tegal.
                </div>
            @endif
        </div>
        <p class="mt-4 text-center text-xs text-gray-400">O-KIM — Sistem Kartu Izin Masuk Digital</p>
    </div>
</body>
</html>
