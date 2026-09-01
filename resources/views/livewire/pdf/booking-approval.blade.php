<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1f2937;
        }

        .page {
            padding: 35px 45px;
        }

        .header {
            display: table;
            width: 100%;
            border-bottom: 3px solid #1e3a8a;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .logo {
            display: table-cell;
            width: 70px;
            vertical-align: middle;
            text-align: center;
        }

        .logo-box {
            width: 55px;
            height: 55px;
            border: 2px solid #1e3a8a;
            border-radius: 8px;
            margin: auto;
            line-height: 55px;
            font-weight: bold;
            color: #1e3a8a;
        }

        .school {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
        }

        .school-name {
            font-size: 16px;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
        }

        .school-title {
            margin-top: 3px;
            font-size: 12px;
            font-weight: bold;
        }

        .school-address {
            margin-top: 3px;
            font-size: 9px;
            color: #4b5563;
        }

        .title {
            text-align: center;
            margin: 20px 0;
        }

        .title h1 {
            margin: 0;
            font-size: 14px;
            text-transform: uppercase;
        }

        .title p {
            margin-top: 5px;
            font-size: 10px;
        }

        .intro {
            text-align: justify;
            line-height: 1.7;
            margin-bottom: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .identity td {
            padding: 5px 4px;
            vertical-align: top;
        }

        .identity .label {
            width: 130px;
            font-weight: bold;
        }

        .section-title {
            margin-top: 18px;
            margin-bottom: 7px;
            font-weight: bold;
            color: #1e3a8a;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 4px;
        }

        .detail-table {
            border: 1px solid #cbd5e1;
        }

        .detail-table th,
        .detail-table td {
            border: 1px solid #cbd5e1;
            padding: 6px;
        }

        .detail-table th {
            background: #eff6ff;
            color: #1e3a8a;
            font-weight: bold;
            text-align: center;
        }

        .status {
            font-weight: bold;
        }

        .approved {
            color: #047857;
        }

        .rejected {
            color: #b91c1c;
        }

        .pending {
            color: #b45309;
        }

        .note {
            margin-top: 12px;
            padding: 9px;
            background: #f8fafc;
            border-left: 3px solid #1e3a8a;
            line-height: 1.6;
        }

        .signature-wrapper {
            margin-top: 35px;
            width: 100%;
        }

        .signature {
            width: 240px;
            margin-left: auto;
            text-align: center;
        }

        .signature-date {
            margin-bottom: 45px;
        }

        .signature-image {
            max-width: 170px;
            max-height: 75px;
            object-fit: contain;
            margin-bottom: 5px;
        }

        .signature-name {
            font-weight: bold;
            text-decoration: underline;
        }

        .footer {
            margin-top: 30px;
            padding-top: 8px;
            border-top: 1px solid #d1d5db;
            font-size: 8px;
            color: #6b7280;
            text-align: center;
        }

    </style>
</head>

<body>
    <div class="page">

        <div class="header">
            <div class="logo">
                <div class="logo-box">
                    SMANSA
                </div>
            </div>

            <div class="school">
                <div class="school-name">
                    SMA NEGERI 1 PEKALONGAN
                </div>

                <div class="school-title">
                    SARANA DAN PRASARANA
                </div>

                <div class="school-address">
                    Sistem Peminjaman Sarana Prasarana Sekolah
                </div>
            </div>
        </div>

        <div class="title">
            <h1>Surat Persetujuan Permohonan Peminjaman</h1>
            <p>
                Nomor Transaksi:
                <strong>{{ $borrowing->kode_transaksi }}</strong>
            </p>
        </div>

        <div class="intro">
            Dengan ini menerangkan bahwa permohonan peminjaman fasilitas sarana prasarana
            telah diproses melalui sistem dan tercatat dengan rincian sebagai berikut:
        </div>

        <table class="identity">
            <tr>
                <td class="label">Nama Pemohon</td>
                <td>: {{ $borrowing->user?->name ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">No. HP</td>
                <td>: {{ $borrowing->user?->no_hp ?? $borrowing->user?->no_wa ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Tanggal Pengajuan</td>
                <td>: {{ optional($borrowing->created_at)->format('d F Y H:i') }}</td>
            </tr>

            <tr>
                <td class="label">Waktu Peminjaman</td>
                <td>
                    :
                    {{ optional($borrowing->tanggal_mulai)->format('d F Y H:i') }}
                    s/d
                    {{ optional($borrowing->tanggal_selesai)->format('d F Y H:i') }}
                </td>
            </tr>

            <tr>
                <td class="label">Tujuan Kegiatan</td>
                <td>: {{ $borrowing->tujuan ?: '-' }}</td>
            </tr>

            <tr>
                <td class="label">Status</td>
                <td
                    class="status
                {{ $borrowing->status === 'Disetujui' ? 'approved' : ($borrowing->status === 'Ditolak' ? 'rejected' : 'pending') }}">
                    : {{ $borrowing->status }}
                </td>
            </tr>
        </table>

        <div class="section-title">
            Rincian Fasilitas Peminjaman
        </div>

        <table class="detail-table">
            <thead>
                <tr>
                    <th width="9%">No</th>
                    <th width="12%">Tipe</th>
                    <th>Nama Fasilitas</th>
                    <th width="12%">Jumlah</th>
                    <th width="18%">Status</th>
                </tr>
            </thead>

            <tbody>
                @forelse($borrowing->details as $index => $detail)
                <tr>
                    <td style="text-align:center;">
                        {{ $index + 1 }}
                    </td>

                    <td>
                        {{ $detail->room ? 'Ruangan' : 'Barang' }}
                    </td>

                    <td>
                        <strong>
                            {{ $detail->room?->nama_ruangan ?? $detail->item?->nama_barang ?? '-' }}
                        </strong>

                        @if($detail->room && $detail->fasilitas)
                        <br>
                        <small>
                            Fasilitas:
                            {{ is_array($detail->fasilitas)
                                    ? implode(', ', $detail->fasilitas)
                                    : $detail->fasilitas }}
                        </small>
                        @endif
                    </td>

                    <td style="text-align:center;">
                        {{ $detail->jumlah }}
                    </td>

                    <td style="text-align:center;">
                        {{ $detail->status }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align:center;">
                        Tidak ada rincian fasilitas.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        @if($borrowing->catatan)
        <div class="note">
            <strong>Catatan Penggunaan:</strong><br>
            {{ $borrowing->catatan }}
        </div>
        @endif

        @if($borrowing->catatan_admin)
        <div class="note">
            <strong>Catatan Admin:</strong><br>
            {{ $borrowing->catatan_admin }}
        </div>
        @endif

        <div class="signature-wrapper">
            <div class="signature">
                <div class="signature-date">
                    Pekalongan, {{ optional($borrowing->created_at)->format('d F Y') }}
                </div>

                <div>
                    @if($borrowing->tanda_tangan)
                    <img src="{{ storage_path('app/public/' . $borrowing->tanda_tangan) }}" class="signature-image"
                        alt="Tanda Tangan">
                    @else
                    <div style="height:75px;"></div>
                    @endif
                </div>

                <div>
                    <div class="signature-name">
                        {{ $borrowing->user?->name ?? '-' }}
                    </div>

                    <div>
                        Pemohon
                    </div>
                </div>
            </div>
        </div>

        <div class="footer">
            Dokumen ini dibuat secara elektronik melalui Sistem Sarpras SMANSA.
            Validitas dokumen dapat diverifikasi berdasarkan kode transaksi.
        </div>

    </div>
</body>

</html>
