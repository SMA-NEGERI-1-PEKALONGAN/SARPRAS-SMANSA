<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: sans-serif;
            font-size: 9px;
            color: #111827;
        }

        .kop-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }

        .kop-table td {
            vertical-align: middle;
        }

        .kop-logo-left {
            width: 105px;
            text-align: center;
        }

        .kop-logo-left img {
            width: 92px;
            height: 102px;
            object-fit: contain;
        }

        .kop-content {
            text-align: center;
            padding: 0 8px;
        }

        .kop-provinsi {
            font-size: 13px;
            font-weight: bold;
            line-height: 1.25;
        }

        .kop-dinas {
            font-size: 12px;
            font-weight: bold;
            line-height: 1.25;
        }

        .kop-sekolah {
            font-size: 14px;
            font-weight: bold;
            line-height: 1.25;
        }

        .kop-alamat {
            margin-top: 3px;
            font-size: 8.5px;
            line-height: 1.35;
        }

        .kop-kontak,
        .kop-website {
            font-size: 8px;
            line-height: 1.35;
        }

        .kop-logo-right {
            width: 105px;
            text-align: center;
        }

        .kop-logo-right img {
            width: 92px;
            height: 92px;
            object-fit: contain;
        }

        .kop-line {
            width: 100%;
            margin-top: 5px;
            border-top: 3px solid #111827;
            border-bottom: 1px solid #111827;
            height: 2px;
        }
        
        .report-title {
            text-align: center;
            margin: 8px 0 4px;
        }

        .report-title h3 {
            margin: 0;
            font-size: 13px;
            text-transform: uppercase;
        }

        .report-title p {
            margin: 3px 0 0;
            font-size: 9px;
        }

        .summary {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
        }

        .summary td {
            padding: 4px 6px;
        }

        .data {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .data th,
        .data td {
            border: 1px solid #9ca3af;
            padding: 5px;
            vertical-align: top;
        }

        .data th {
            background: #f3f4f6;
            text-align: center;
            font-weight: bold;
        }

        .center {
            text-align: center;
        }

        .resource {
            margin-bottom: 2px;
        }

        .signature {
            width: 100%;
            margin-top: 25px;
            border-collapse: collapse;
        }

        .signature td {
            width: 50%;
            vertical-align: top;
        }

        .signature-right {
            text-align: center;
        }

        .signature-space {
            height: 70px;
        }

        .signature-image {
            max-width: 140px;
            max-height: 60px;
        }

        .footer-note {
            margin-top: 12px;
            font-size: 8px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    @php
        $logoJateng = public_path('img/logo-jateng.png');
        $logoSmansa = public_path('img/logosmansa.png');
    @endphp

    <table class="kop-table">
        <tr>
            <td class="kop-logo-left">
                @if(file_exists($logoJateng))
                    <img src="{{ $logoJateng }}" alt="Logo Jawa Tengah" style="width: 92px; height: 102px;">
                @endif
            </td>
            <td class="kop-content">
                <div class="kop-provinsi">PEMERINTAH PROVINSI JAWA TENGAH</div>
                <div class="kop-dinas">DINAS PENDIDIKAN</div>
                <div class="kop-sekolah">SEKOLAH MENENGAH ATAS NEGERI 1</div>
                <div class="kop-sekolah">PEKALONGAN</div>
                <div class="kop-alamat">Jalan RA. Kartini Nomor 39 Kota Pekalongan Kode Pos. 51128</div>
                <div class="kop-kontak">Telepon. (0285) 421190&nbsp;&nbsp; Faksimile. (0285) 432712&nbsp;&nbsp; email. sma1pkl@yahoo.com</div>
                <div class="kop-website">website. www.sman1pekalongan.sch.id</div>
            </td>
            <td class="kop-logo-right">
                @if(file_exists($logoSmansa))
                    <img src="{{ $logoSmansa }}" alt="Logo SMAN 1 Pekalongan" style="width: 92px; height: 92px;">
                @endif
            </td>
        </tr>
    </table>
    <div class="kop-line"></div>

    <div class="report-title">
        <h3>Laporan Peminjaman Sarana dan Prasarana</h3>
        <p>Periode: {{ $periodText }} | Status: {{ $statusText }}</p>
    </div>

    <table class="summary">
        <tr style="border: 0px">
            <td> <strong>Total Transaksi</strong></td>
            <td>{{ $rows->count() }}</td>
            <td><strong>Dicetak</strong></td>
            <td>{{ $generatedAt }}</td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th width="4%">No</th>
                <th width="9%">Kode</th>
                <th width="12%">Peminjam</th>
                <th width="13%">Tanggal Pengajuan</th>
                <th width="15%">Periode Peminjaman</th>
                <th width="14%">Fasilitas</th>
                <th width="14%">Tujuan</th>
                <th width="7%">Status</th>
                <th width="12%">Catatan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $borrowing)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $borrowing->kode_transaksi }}</td>
                    <td>
                        <strong>{{ $borrowing->user?->name ?? '-' }}</strong><br>
                        {{ $borrowing->user?->username ?? '-' }}
                    </td>
                    <td>{{ optional($borrowing->created_at)->format('d-m-Y H:i') }}</td>
                    <td>
                        {{ optional($borrowing->tanggal_mulai)->format('d-m-Y H:i') }}<br>
                        s/d<br>
                        {{ optional($borrowing->tanggal_selesai)->format('d-m-Y H:i') }}
                    </td>
                    <td>
                        @foreach($borrowing->details as $detail)
                            <div class="resource">
                                {{ $detail->room ? 'Ruangan' : 'Barang' }}:
                                <strong>{{ $detail->room?->nama_ruangan ?? $detail->item?->nama_barang ?? '-' }}</strong>
                                ×{{ $detail->jumlah }}
                            </div>
                        @endforeach
                    </td>
                    <td>{{ $borrowing->tujuan ?? '-' }}</td>
                    <td class="center">{{ $borrowing->status }}</td>
                    <td>
                        @if($borrowing->catatan_admin)
                            <strong>Admin:</strong> {{ $borrowing->catatan_admin }}
                        @elseif($borrowing->catatan)
                            {{ $borrowing->catatan }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="center">Tidak ada data peminjaman.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="signature">
        <tr>
            <td></td>
            <td class="signature-right">
                Pekalongan, {{ now()->translatedFormat('d F Y') }}<br>
                Mengetahui,<br>
                {{ $signerRole }}
                <div class="signature-space">
                   <br>
                   <br>
                </div>
                <strong>{{ $signerName }}</strong>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        Dokumen ini dicetak secara elektronik melalui Sistem SARPRAS SMANSA.
    </div>
</body>
</html>
