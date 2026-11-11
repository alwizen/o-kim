<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kartu Izin Masuk — {{ $card->kim_number }}</title>
    <style>
        @page {
            margin: 10px 12px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #000000;
            margin: 0;
        }

        .card {
            border: 2px solid #000000;
            padding: 8px 10px;
        }

        .header {
            border-bottom: 2px solid #000000;
            padding-bottom: 4px;
            margin-bottom: 6px;
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
            height: 32px;
            width: auto;
        }

        .header .logo-ppn {
            height: 20px;
            width: auto;
        }

        .header .brand {
            font-size: 8px;
            color: #000000;
        }

        .header h1 {
            font-size: 12px;
            margin: 1px 0 0;
            color: #000000;
            font-weight: bold;
        }

        .header .sub {
            font-size: 8px;
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
            padding: 2px 5px;
            vertical-align: middle;
        }

        table.data td.label {
            width: 26%;
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
            margin-top: 5px;
        }

        .bottom td.qr {
            width: 20%;
            text-align: center;
            vertical-align: middle;
        }

        .bottom td.note {
            width: 80%;
            vertical-align: middle;
            padding-right: 8px;
        }

        .qr-caption {
            font-size: 6px;
            color: #000000;
            margin-top: 1px;
        }

        .dispensation-note {
            font-size: 7px;
            color: #000000;
            border: 1px solid #000000;
            padding: 4px 5px;
        }

        .footer {
            margin-top: 5px;
            font-size: 6.5px;
            color: #000000;
            border-top: 1px solid #000000;
            padding-top: 3px;
            text-align: center;
        }

        /* Strip tanda tangan elektronik lima fungsi. */
        .signatures {
            margin-top: 6px;
            border-top: 1px solid #000000;
            padding-top: 4px;
        }

        .signatures-title {
            font-size: 7px;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        table.signatures-table {
            width: 100%;
            border-collapse: collapse;
        }

        table.signatures-table td.signature-cell {
            text-align: center;
            vertical-align: top;
            padding: 2px 2px;
        }

        .signature-qr {
            width: 46px;
            height: 46px;
        }

        .signature-name {
            font-size: 6.5px;
            font-weight: bold;
            margin-top: 1px;
        }

        .signature-role {
            font-size: 5.5px;
            font-weight: bold;
            margin-top: 2px;
            border-top: 1px solid #000000;
            padding-top: 1px;
        }

        .signature-placeholder {
            font-size: 5.5px;
            color: #6b7280;
            height: 46px;
            padding-top: 16px;
        }

        .signature-hash {
            margin-top: 3px;
            font-size: 5px;
            color: #6b7280;
            text-align: center;
            word-wrap: break-word;
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
                <td class="value">{{ $card->expires_at->format('d M Y') }}{{ $validityLabel ?? '' }}</td>
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
                        @if (! empty($qrSvg))
                            <img src="data:image/svg+xml;base64,{{ base64_encode($qrSvg) }}" width="78" alt="QR Validasi">
                            <div class="qr-caption">Pindai untuk validasi</div>
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        @if (! empty($signatureBlocks))
            <div class="signatures">
                <div class="signatures-title">Di Setujui Oleh</div>

                <table class="signatures-table">
                    <tr>
                        @foreach ($signatureBlocks as $block)
                            <td class="signature-cell">
                                @if ($block['placeholder'])
                                    <div class="signature-placeholder">Tidak tersedia<br>(data lama)</div>
                                @else
                                    @if ($block['qr'])
                                        <img src="{{ $block['qr'] }}" alt="QR Tanda Tangan {{ $block['department']->label() }}" class="signature-qr">
                                    @endif
                                    <div class="signature-name">{{ $block['signature']->name }}</div>
                                @endif
                                <div class="signature-role">{{ $block['signature']?->jabatan ?: $block['department']->label() }}</div>
                            </td>
                        @endforeach
                    </tr>
                </table>

                @if ($card->pdf_hash)
                    <div class="signature-hash">
                        No. KIM : {{ $card->kim_number }} · SHA-256: {{ $card->pdf_hash }}
                    </div>
                @endif
            </div>
        @endif

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
