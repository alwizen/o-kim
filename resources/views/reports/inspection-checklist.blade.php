<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Pemeriksaan — {{ $application->application_number }}</title>
    <style>
        @page {
            margin: 24px 28px 42px 28px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #1f2937;
            line-height: 1.35;
        }

        h1, h2, h3 {
            margin: 0;
        }

        .header {
            border-bottom: 2px solid #000000;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .header table {
            width: 100%;
            border-collapse: collapse;
        }

        .header td.logo-left {
            width: 22%;
            text-align: left;
            vertical-align: middle;
        }

        .header td.center {
            width: 56%;
            text-align: center;
            vertical-align: middle;
            padding: 0 8px;
        }

        .header td.logo-right {
            width: 22%;
            text-align: right;
            vertical-align: middle;
        }

        .header .logo-k3 {
            height: 52px;
            width: auto;
        }

        .header .logo-ppn {
            height: 30px;
            width: auto;
        }

        .header .brand {
            font-size: 13px;
            font-weight: bold;
            color: #000000;
        }

        .header .sub {
            font-size: 8.5px;
            color: #6b7280;
            margin-top: 1px;
        }

        .header .doc-title {
            font-size: 11px;
            font-weight: bold;
            color: #000000;
            margin-top: 2px;
        }

        .header .doc-meta {
            font-size: 8px;
            color: #6b7280;
        }

        .section-title {
            font-size: 10px;
            font-weight: bold;
            color: #000000;
            border-left: 3px solid #1e3a8a;
            padding-left: 6px;
            margin: 14px 0 6px;
        }

        table.meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        table.meta td {
            padding: 2px 0;
            vertical-align: top;
        }

        table.meta td.label {
            width: 24%;
            color: #6b7280;
        }

        table.meta td.value {
            width: 26%;
            font-weight: bold;
            color: #000000;
        }

        table.summary {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        table.summary td {
            border: 1px solid #000000;
            text-align: center;
            padding: 5px 3px;
            width: 16.66%;
        }

        table.summary .num {
            font-size: 13px;
            font-weight: bold;
            color: #1e3a8a;
        }

        table.summary .caption {
            font-size: 7.5px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        table.grid {
            width: 100%;
            border-collapse: collapse;
        }

        table.grid thead th {
            background: #ffffff;
            border: 1px solid #000000;
            padding: 4px 5px;
            font-size: 8px;
            text-align: left;
            color: #000000;
        }

        table.grid tbody td {
            border: 1px solid #000000;
            padding: 4px 5px;
            vertical-align: top;
        }

        table.grid tbody tr:nth-child(even) td {
            background: #f9fafb;
        }

        .col-no { width: 5%; text-align: center; }
        .col-status { width: 12%; }
        .col-note { width: 20%; }

        .item-label {
            font-weight: bold;
            color: #000000;
        }

        .item-desc {
            color: #6b7280;
            font-size: 8px;
        }

        .points {
            margin: 3px 0 0;
            padding: 0;
            list-style: none;
        }

        .points li {
            font-size: 8px;
            color: #4b5563;
        }

        .points li.checked {
            color: #166534;
        }

        .badge {
            display: inline-block;
            padding: 1px 5px;
            border-radius: 8px;
            font-size: 7.5px;
            font-weight: bold;
        }

        .badge-compliant { background: #dcfce7; color: #166534; }
        .badge-finding { background: #fee2e2; color: #991b1b; }
        .badge-na { background: #e5e7eb; color: #4b5563; }
        .badge-empty { background: #fef3c7; color: #92400e; }

        .finding-box {
            margin-top: 3px;
            border-left: 2px solid #dc2626;
            background: #fef2f2;
            padding: 3px 5px;
            font-size: 8px;
            color: #7f1d1d;
        }

        .finding-box .meta {
            color: #b91c1c;
            font-size: 7.5px;
        }

        .department-head {
            font-size: 10px;
            font-weight: bold;
            color: #000000;
            margin: 14px 0 4px;
        }

        .department-meta {
            font-size: 8px;
            color: #6b7280;
            margin-bottom: 4px;
        }

        .empty-note {
            font-size: 8px;
            color: #92400e;
            background: #fef3c7;
            border: 1px solid #fde68a;
            padding: 5px 6px;
        }

        .disclaimer {
            margin-top: 18px;
            font-size: 7.5px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 5px;
        }

        .page-break {
            page-break-before: always;
        }

        .footer {
            position: fixed;
            bottom: -26px;
            left: 0;
            right: 0;
            font-size: 7px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 3px;
        }
    </style>
</head>
<body>

<div class="footer">
    O-KIM — Fuel Terminal Tegal · Laporan Pemeriksaan {{ $application->application_number }} · Dicetak {{ $printedAt->translatedFormat('d M Y H:i') }}
</div>

<div class="header">
    <table>
        <tr>
            <td class="logo-left">
                <img src="{{ public_path('img/K3.png') }}" alt="Logo K3" class="logo-k3">
            </td>
            <td class="center">
                <div class="brand">O-KIM — Fuel Terminal Tegal</div>
                <div class="doc-title">LAPORAN HASIL PEMERIKSAAN</div>
                <div class="doc-meta">No. Pengajuan: {{ $application->application_number }}</div>
            </td>
            <td class="logo-right">
                <img src="{{ public_path('img/ppn.png') }}" alt="Logo PPN" class="logo-ppn">
            </td>
        </tr>
    </table>
</div>

<div class="section-title">Data Pengajuan</div>
<table class="meta">
    <tr>
        <td class="label">NOPOL</td>
        <td class="value">{{ $application->vehicle?->registration_number ?? '—' }}</td>
        <td class="label">Perusahaan</td>
        <td class="value">{{ $application->transporter?->company_name ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Jenis Pengajuan</td>
        <td class="value">{{ $application->application_type->label() }}</td>
        <td class="label">Status Pengajuan</td>
        <td class="value">{{ $application->status->label() }}</td>
    </tr>
    <tr>
        <td class="label">Diajukan Pada</td>
        <td class="value">{{ $application->submitted_at?->translatedFormat('d M Y H:i') ?? '—' }}</td>
        <td class="label">Jadwal Pemeriksaan</td>
        <td class="value">{{ $application->inspection_scheduled_at?->translatedFormat('d M Y H:i') ?? '—' }}</td>
    </tr>
</table>

<div class="section-title">Daftar Pemeriksaan</div>
{{-- <table class="summary">
    <tr>
        <td>
            <div class="num">{{ $summary['assessed_items'] }}/{{ $summary['total_items'] }}</div>
            <div class="caption">Butir dinilai</div>
        </td>
        <td>
            <div class="num">{{ $summary['percentage'] }}%</div>
            <div class="caption">Progres</div>
        </td>
        <td>
            <div class="num">{{ $summary['compliant'] }}</div>
            <div class="caption">Sesuai</div>
        </td>
        <td>
            <div class="num">{{ $summary['finding'] }}</div>
            <div class="caption">Temuan</div>
        </td>
        <td>
            <div class="num">{{ $summary['not_applicable'] }}</div>
            <div class="caption">N/A</div>
        </td>
        <td>
            <div class="num">{{ $summary['open_findings'] }}</div>
            <div class="caption">Temuan terbuka</div>
        </td>
    </tr>
</table> --}}

@forelse ($departments as $index => $department)
    <div class="{{ $index > 0 ? 'page-break' : '' }}">
        <div class="department-head">{{ $department['department']->label() }} — {{ $department['checklist']->title }}</div>
        <div class="department-meta">
            {{ $department['assessed'] }} dari {{ $department['total'] }} butir dinilai
            @if (! empty($department['inspectors']))
                · Petugas: {{ implode(', ', $department['inspectors']) }}
            @endif
        </div>

        @if ($department['total'] === 0)
            <div class="empty-note">Belum ada butir checklist aktif untuk fungsi ini.</div>
        @else
            <table class="grid">
                <thead>
                    <tr>
                        <th class="col-no">No</th>
                        <th>Butir Pemeriksaan</th>
                        <th class="col-status">Hasil</th>
                        <th class="col-note">Catatan</th>
                        <th class="col-note">Waktu Periksa</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($department['rows'] as $rowIndex => $row)
                        @php($result = $row['result'])
                        <tr>
                            <td class="col-no">{{ $rowIndex + 1 }}</td>
                            <td>
                                <span class="item-label">{{ $row['item']->label }}</span>
                                {{-- @if ($row['item']->description)
                                    <div class="item-desc">{{ $row['item']->description }}</div>
                                @endif --}}

                                @if ($row['item']->points->isNotEmpty())
                                    <ul class="points">
                                        @foreach ($row['item']->points as $point)
                                            <li class="{{ ($row['checked_points'][$point->id] ?? false) ? 'checked' : '' }}">
                                                {{ ($row['checked_points'][$point->id] ?? false) ? '[v]' : '[ ]' }} {{ $point->label }}
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif

                                @if ($result?->finding)
                                    <div class="finding-box">
                                        <strong>Temuan:</strong> {{ $result->finding->description }}
                                        <div class="meta">
                                            Status: {{ $result->finding->status->label() }}
                                            @if ($result->finding->creator)
                                                · Dilaporkan oleh {{ $result->finding->creator->name }}
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </td>
                            <td class="col-status">
                                @if ($result === null)
                                    <span class="badge badge-empty">Belum dinilai</span>
                                @else
                                    <span class="badge {{ match ($result->result) {
                                        \App\Enums\InspectionResultValue::Compliant => 'badge-compliant',
                                        \App\Enums\InspectionResultValue::Finding => 'badge-finding',
                                        \App\Enums\InspectionResultValue::NotApplicable => 'badge-na',
                                    } }}">
                                        {{ $result->result->label() }}
                                    </span>
                                @endif
                            </td>
                            <td class="col-note">{{ $result?->notes ?: '—' }}</td>
                            <td class="col-note">{{ $result?->inspected_at?->translatedFormat('d M Y H:i') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@empty
    <div class="empty-note">Belum ada checklist aktif yang dapat dilaporkan.</div>
@endforelse

<div class="disclaimer">
    Dokumen ini dihasilkan otomatis oleh O-KIM berdasarkan data hasil pemeriksaan yang tersimpan pada sistem.
    Laporan bersifat informatif dan tidak menggantikan dokumen resmi KIM. Perubahan data setelah tanggal cetak
    tidak tercermin pada dokumen ini.
</div>

</body>
</html>
