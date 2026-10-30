<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Berita Acara — {{ $application->application_number }}</title>
    <style>
        @page {
            margin: 24px 52px 42px 52px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #000000;
            line-height: 1.45;
        }

        h1, h2, h3 {
            margin: 0;
        }

        .header {
            /* border-bottom: 2px solid #000000; */
            padding-bottom: 8px;
            margin-bottom: 16px;
        }

        .header table {
            width: 100%;
            border-collapse: collapse;
        }

        .header td.center {
            width: 76%;
            text-align: center;
            vertical-align: middle;
            padding-right: 8px;
        }

        .header td.logo-right {
            width: 24%;
            text-align: right;
            vertical-align: middle;
        }

        .header .logo-ppn {
            height: 30px;
            width: auto;
        }

        .header .brand {
            font-size: 11px;
            font-weight: bold;
            color: #000000;
        }

        .header .doc-title {
            font-size: 12px;
            font-weight: bold;
            color: #000000;
            margin-top: 1px;
        }

        .header .doc-meta {
            font-size: 9px;
            color: #000000;
            margin-top: 1px;
        }

        .doc-heading {
            text-align: center;
            margin-bottom: 14px;
        }

        .doc-heading .title {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .doc-heading .subtitle {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .doc-heading .number {
            font-size: 11px;
            font-weight: bold;
            margin-top: 6px;
        }

        .paragraph {
            text-align: justify;
            margin: 0 0 10px;
        }

        table.grid {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0 12px;
        }

        table.grid thead th {
            background: #ffffff;
            border: 1px solid #000000;
            padding: 5px 6px;
            font-size: 9px;
            text-align: left;
            color: #000000;
        }

        table.grid tbody td {
            border: 1px solid #000000;
            padding: 5px 6px;
            vertical-align: top;
            color: #000000;
        }

        .col-no { width: 7%; text-align: center; }
        .col-nopol { width: 20%; }
        .col-ket { width: 28%; }

        .page-break {
            page-break-before: always;
        }

        .attachment-title {
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        table.attachments {
            width: 100%;
            border-collapse: collapse;
        }

        table.attachments thead th {
            background: #ffffff;
            border: 1px solid #000000;
            padding: 5px 6px;
            font-size: 9px;
            text-align: center;
            color: #000000;
        }

        table.attachments tbody td {
            border: 1px solid #000000;
            padding: 6px;
            vertical-align: top;
            color: #000000;
        }

        table.attachments .att-no {
            width: 6%;
            text-align: center;
        }

        table.attachments .att-desc {
            width: 26%;
        }

        table.attachments .att-photo {
            width: 34%;
            text-align: center;
        }

        .att-photo .thumb {
            max-width: 100%;
            max-height: 150px;
            width: auto;
            height: auto;
        }

        .att-photo .empty {
            font-size: 9px;
            color: #000000;
        }

        .att-photo .date {
            margin-top: 4px;
            font-size: 8px;
            color: #000000;
        }

        .footer {
            position: fixed;
            bottom: -26px;
            left: 0;
            right: 0;
            font-size: 7px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 3px;
        }
    </style>
</head>
<body>

<div class="footer">
    O-KIM — Fuel Terminal Tegal · Berita Acara {{ $application->application_number }} · Dicetak {{ $printedAt->translatedFormat('d M Y H:i') }}
</div>

<div class="header">
    <table>
        <tr>
            {{-- <td class="center">
                <div class="brand">O-KIM — Fuel Terminal Tegal</div>
                <div class="doc-title">BERITA ACARA</div>
                <div class="doc-meta">No. BA: {{ $application->application_number }}</div>
            </td> --}}
            <td class="logo-right">
                <img src="{{ public_path('img/ppn.png') }}" alt="Logo PPN" class="logo-ppn">
            </td>
        </tr>
    </table>
</div>

<div class="doc-heading">
    <div class="title">Berita Acara</div>
    <div class="subtitle">Hasil Pemeriksaan Tindak Lanjut Temuan Mobil Tangki</div>
    <div class="subtitle">di Fuel Terminal Tegal</div>
    <div class="number">No. BA – {{ $application->application_number }}</div>
</div>

<p class="paragraph">
    Pada hari ini <strong>{{ $printedAt->translatedFormat('l') }}</strong>,
    tanggal <strong>{{ \App\Support\TanggalTerbilang::angka($printedAt->day) }}</strong>
    bulan <strong>{{ \App\Support\TanggalTerbilang::bulan($printedAt->month) }}</strong>
    tahun <strong>{{ \App\Support\TanggalTerbilang::angka($printedAt->year) }}</strong>
    telah dilakukan pemeriksaan verifikasi tindak lanjut temuan terhadap mobil tangki (KIM) milik
    <strong>{{ $application->transporter?->company_name ?? '—' }}</strong> dengan nomor polisi
    <strong>{{ $application->vehicle?->registration_number ?? '—' }}</strong> dengan hasil verifikasi
    tindak lanjut sebagai berikut:
</p>

<table class="grid">
    <thead>
        <tr>
            <th class="col-no">No</th>
            <th class="col-nopol">Nopol</th>
            <th>Temuan</th>
            <th class="col-ket">Keterangan</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td class="col-no">{{ $row['no'] }}</td>
                <td class="col-nopol">{{ $application->vehicle?->registration_number ?? '—' }}</td>
                <td>{{ $row['description'] }}</td>
                <td>{{ $row['status'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4" style="text-align: center;">Belum ada temuan yang ditindaklanjuti.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<p class="paragraph">
    Demikian Berita Acara ini dibuat sesuai dengan keadaan sebenarnya dan untuk dipergunakan
    sebagaimana mestinya.
</p>

<div class="page-break"></div>

<div class="attachment-title">Lampiran Dokumentasi Verifikasi Tindak Lanjut</div>

<table class="attachments">
    <thead>
        <tr>
            <th class="att-no">No</th>
            <th class="att-desc">Temuan</th>
            <th class="att-photo">Dokumentasi Temuan Awal</th>
            <th class="att-photo">Dokumentasi Verifikasi</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($attachments as $attachment)
            <tr>
                <td class="att-no">{{ $attachment['no'] }}</td>
                <td class="att-desc">{{ $attachment['description'] }}</td>
                <td class="att-photo">
                    @if ($attachment['initial_image'])
                        <img src="{{ $attachment['initial_image'] }}" alt="Dokumentasi Temuan Awal" class="thumb">
                    @else
                        <div class="empty">-</div>
                    @endif
                    @if ($attachment['initial_date'])
                        <div class="date">Tanggal : {{ $attachment['initial_date']->translatedFormat('d F Y') }}</div>
                    @endif
                </td>
                <td class="att-photo">
                    @if ($attachment['action_image'])
                        <img src="{{ $attachment['action_image'] }}" alt="Dokumentasi Verifikasi" class="thumb">
                    @else
                        <div class="empty">-</div>
                    @endif
                    @if ($attachment['action_date'])
                        <div class="date">Tanggal : {{ $attachment['action_date']->translatedFormat('d F Y') }}</div>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" style="text-align: center;">Belum ada lampiran dokumentasi.</td>
            </tr>
        @endforelse
    </tbody>
</table>

</body>
</html>
