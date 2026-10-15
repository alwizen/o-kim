<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #1a1a1a; }
        .card { border: 2px solid #1e3a8a; border-radius: 8px; padding: 16px; }
        .header { text-align: center; border-bottom: 2px solid #1e3a8a; padding-bottom: 8px; margin-bottom: 12px; }
        .header h1 { font-size: 16px; margin: 0; color: #1e3a8a; }
        .header p { font-size: 10px; margin: 2px 0 0; color: #555; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 6px; vertical-align: top; }
        .label { color: #555; width: 35%; }
        .value { font-weight: bold; }
        .qr { text-align: center; }
        .footer { margin-top: 12px; font-size: 8px; color: #777; text-align: center; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>KARTU IZIN MASUK (KIM)</h1>
            <p>Fuel Terminal Tegal</p>
        </div>
        <table>
            <tr>
                <td style="width: 70%;">
                    <table>
                        <tr><td class="label">Nomor KIM</td><td class="value">{{ $card->kim_number }}</td></tr>
                        <tr><td class="label">NOPOL</td><td class="value">{{ $card->application->vehicle->registration_number }}</td></tr>
                        <tr><td class="label">Perusahaan</td><td class="value">{{ $card->application->transporter->company_name }}</td></tr>
                        <tr><td class="label">Tanggal Terbit</td><td class="value">{{ $card->issued_at->format('d M Y') }}</td></tr>
                        <tr><td class="label">Berlaku Hingga</td><td class="value">{{ $card->expires_at->format('d M Y') }}</td></tr>
                    </table>
                </td>
                <td class="qr">
                    <img src="data:image/svg+xml;base64,{{ base64_encode($qrSvg) }}" width="120" alt="QR Validasi">
                    <div style="font-size: 8px; color: #777;">Pindai untuk validasi</div>
                </td>
            </tr>
        </table>
        <div class="footer">
            KIM ini sah hanya jika terverifikasi melalui halaman validasi resmi O-KIM Fuel Terminal Tegal.
        </div>
    </div>
</body>
</html>
