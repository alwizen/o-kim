<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kartu Izin Masuk — {{ $card->kim_number }}</title>
    <style>
        @page {
            margin: 14px 16px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #000000;
            margin: 0;
        }

        .card {
            border: 2px solid #000000;
            padding: 12px 14px;
        }

        .header {
            border-bottom: 2px solid #000000;
            padding-bottom: 6px;
            margin-bottom: 10px;
        }

        .header table {
            width: 100%;
            border-collapse: collapse;
        }

        .header td.logo-left {
            width: 20%;
            text-align: left;
            vertical-align: middle;
        }

        .header td.center {
            width: 60%;
            text-align: center;
            vertical-align: middle;
            padding: 0 6px;
        }

        .header td.logo-right {
            width: 20%;
            text-align: right;
            vertical-align: middle;
        }

        .header .logo-k3 {
            height: 44px;
            width: auto;
        }

        .header .logo-ppn {
            height: 26px;
            width: auto;
        }

        .header .brand {
            font-size: 9px;
            color: #000000;
        }

        .header h1 {
            font-size: 14px;
            margin: 2px 0 0;
            color: #000000;
            font-weight: bold;
        }

        .header .sub {
            font-size: 9px;
            color: #000000;
            margin-top: 1px;
        }

        /* Tabel data: garis hitam penuh seperti dokumen resmi. */
        table.data {
            width: 100%;
            border-collapse: collapse;
        }

        table.data td {
            border: 1px solid #000000;
            padding: 4px 6px;
            vertical-align: middle;
        }

        table.data td.label {
            width: 30%;
            color: #000000;
            font-weight: bold;
        }

        table.data td.value {
            font-weight: bold;
            color: #000000;
        }

        .bottom table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .bottom td.qr {
            width: 30%;
            text-align: center;
            vertical-align: middle;
        }

        .bottom td.note {
            width: 70%;
            vertical-align: middle;
            padding-right: 10px;
        }

        .qr-caption {
            font-size: 7px;
            color: #000000;
            margin-top: 2px;
        }

        .dispensation-note {
            font-size: 8px;
            color: #000000;
            border: 1px solid #000000;
            padding: 5px 6px;
        }

        .footer {
            margin-top: 8px;
            font-size: 7.5px;
            color: #000000;
            border-top: 1px solid #000000;
            padding-top: 4px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <table>
                <tr>
                    <td class="logo-left">
                        <img src="{{ public_path('img/K3.png') }}" alt="Logo K3" class="logo-k3">
                    </td>
                    <td class="center">
                        <div class="brand">O-KIM — Fuel Terminal Tegal</div>
                        <h1>KARTU IZIN MASUK (KIM){{ $card->is_dispensation ? ' — SEMENTARA' : '' }}</h1>
                        <div class="sub">Sistem Kartu Izin Masuk Digital</div>
                    </td>
                    <td class="logo-right">
                        <img src="{{ public_path('img/ppn.png') }}" alt="Logo PPN" class="logo-ppn">
                    </td>
                </tr>
            </table>
        </div>

        <table class="data">
            <tr>
                <td class="label">Nomor KIM</td>
                <td class="value">{{ $card->kim_number }}</td>
            </tr>
            <tr>
                <td class="label">NOPOL</td>
                <td class="value">{{ $card->application->vehicle->registration_number }}</td>
            </tr>
            <tr>
                <td class="label">Perusahaan</td>
                <td class="value">{{ $card->application->transporter->company_name }}</td>
            </tr>
            <tr>
                <td class="label">Tanggal Terbit</td>
                <td class="value">{{ $card->issued_at->format('d M Y') }}</td>
            </tr>
            <tr>
                <td class="label">Berlaku Hingga</td>
                <td class="value">{{ $card->expires_at->format('d M Y') }}</td>
            </tr>
            @if ($card->is_dispensation)
                <tr>
                    <td class="label">Batas Dispensasi</td>
                    <td class="value">{{ $card->dispensation_expires_at?->format('d M Y') ?? '—' }}</td>
                </tr>
            @endif
        </table>

        <div class="bottom">
            <table>
                <tr>
                    <td class="note">
                        @if ($card->is_dispensation)
                            <div class="dispensation-note">
                                KIM SEMENTARA — diterbitkan karena terdapat temuan dalam dispensasi FTM.
                                KIM tidak berlaku setelah batas dispensasi terlampaui.
                            </div>
                        @endif
                    </td>
                    <td class="qr">
                        <img src="data:image/svg+xml;base64,{{ base64_encode($qrSvg) }}" width="105" alt="QR Validasi">
                        <div class="qr-caption">Pindai untuk validasi</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="footer">
            @if ($card->is_dispensation)
                KIM SEMENTARA — tidak berlaku setelah batas dispensasi terlampaui.
            @else
                KIM ini sah hanya jika terverifikasi melalui halaman validasi resmi O-KIM Fuel Terminal Tegal.
            @endif
        </div>
    </div>
</body>
</html>
