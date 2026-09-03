<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
 <style>

        @page {
            margin: 10mm 18mm 10mm 18mm;
            footer: pageFooter;
        }        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Calibri', sans-serif;
            font-size: 10.5pt;
            line-height: 1.5;
            color: #000000;
        }

        table {
            border-collapse: collapse;
        }

        /* =========================================================
        KOP SURAT
        ========================================================= */

        .kop-table !important{
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-family: 'dejavusans', sans-serif ;
        }

        .kop-table td  !important{
            border: 0;
            padding: 0;
            margin: 0;
            vertical-align: middle;
            font-family: 'dejavusans', sans-serif ;
        }

        /* Logo kiri */
        .kop-logo-left {
            width: 14%;
            text-align: center;
            vertical-align: middle;
        }

        /* Bagian tengah */
        .kop-content {
            width: 72%;
            text-align: center;
            vertical-align: middle;
        }

        /* Logo kanan */
        .kop-logo-right {
            width: 14%;
            text-align: center;
            vertical-align: middle;
        }

        .kop-logo-left img,
        .kop-logo-right img {
            width: 72px;
            height: 82px;
        }

        .kop-provinsi {
            font-size: 13pt;
            line-height: 1.05;
            font-weight: normal;
            margin: 0;
        }

        .kop-dinas {
            font-size: 13pt;
            line-height: 1.05;
            font-weight: bold;
            margin-top: 1px;
        }

        .kop-sekolah {
            font-size: 14pt;
            line-height: 1.05;
            font-weight: bold;
            margin-top: 1px;
        }

        .kop-alamat {
            font-size: 7pt;
            line-height: 1.15;
            margin-top: 5px;
            white-space: nowrap;
        }

        .kop-kontak {
            font-size: 7pt;
            line-height: 1.15;
            margin-top: 1px;
            white-space: nowrap;
        }

        .kop-website {
            font-size: 7pt;
            line-height: 1.15;
            margin-top: 1px;
            font-style: italic;
        }

        /*
        * Garis kop seperti pada screenshot:
        * satu garis hitam tebal dan panjang.
        */
        .kop-line {
            width: 100%;
            border-bottom: 3px solid #000000;
            margin-top: 7px;
            margin-bottom: 18px;
            height: 3px;
        }
        /* =========================================================
           JUDUL
        ========================================================= */

        .document-title {
            text-align: center;
            margin-bottom: 16px;
        }

        .document-title h1 {
            margin: 0;
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
        }

        .document-number {
            margin-top: 4px;
            font-size: 9pt;
        }

        /* =========================================================
           PEMBUKA
        ========================================================= */

        .intro {
            text-align: justify;
            margin-bottom: 12px;
            line-height: 1.65;
        }

        /* =========================================================
           IDENTITAS
        ========================================================= */

        .identity {
            width: 100%;
            margin-bottom: 14px;
        }

        .identity td {
            padding: 2px 0;
            vertical-align: top;
        }

        .identity-label {
            width: 145px;
        }

        .identity-separator {
            width: 15px;
            text-align: center;
        }

        /* =========================================================
           SECTION
        ========================================================= */

        .section-title {
            margin-top: 13px;
            margin-bottom: 7px;
            font-weight: bold;
            font-size: 10.5pt;
        }

        /* =========================================================
           TABEL RINCIAN
        ========================================================= */

        .detail-table {
            width: 100%;
            border: 0.7pt solid #000000;
        }

        .detail-table th,
        .detail-table td {
            border: 0.5pt solid #000000;
            padding: 5px 6px;
            vertical-align: top;
        }

        .detail-table th {
            text-align: center;
            font-weight: bold;
            background-color: #ffffff;
        }

        .detail-table .center {
            text-align: center;
            vertical-align: middle;
        }

        .detail-table small {
            font-size: 8.5pt;
        }

        /* =========================================================
           CATATAN
        ========================================================= */

        .notes {
            margin-top: 12px;
        }

        .note {
            margin-bottom: 7px;
            line-height: 1.5;
        }

        .note-title {
            font-weight: bold;
        }

        /* =========================================================
           STATUS
        ========================================================= */

        .status {
            font-weight: bold;
        }

        /* Tidak menggunakan warna status.
           Semua tetap hitam agar formal. */

        /* =========================================================
        TANDA TANGAN
        ========================================================= */

        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
        }

        .signature-table td {
            border: 0;
            padding: 0;
        }

        .signature-empty {
            width: 58%;
        }

        .signature-cell {
            width: 42%;
            text-align: center;
            vertical-align: top;
        }

        .signature-location {
            font-size: 10pt;
            margin-bottom: 6px;
        }

        .signature-position {
            font-size: 10pt;
            margin-bottom: 3px;
        }

        .signature-image-wrapper {
            height: 75px;
            width: 100%;
            text-align: center;
            vertical-align: middle;
        }

        .signature-image {
            width: 170px;
            height: 70px;
            object-fit: contain;
        }

        .signature-placeholder {
            width: 170px;
            height: 70px;
        }

        .signature-name {
            font-size: 10pt;
            font-weight: bold;
            text-decoration: underline;
            margin-top: 2px;
        }

        /* =========================================================
           FOOTER
        ========================================================= */
        .footer {
            text-align: center;
            font-family: DejaVu Sans, sans-serif;
            font-size: 7.5pt;
            color: #000000;
        }
    </style>
</head>

@php
    /*
     * Sesuaikan dengan lokasi logo pada project Anda.
     *
     * Contoh:
     * public/assets/images/logo-jateng.png
     * public/assets/images/logo-smansa.png
     */
    $logoJateng = public_path('assets/images/logo-jateng.png');
    $logoSekolah = public_path('assets/images/logo-smansa.png');

    /*
     * Format tanggal Bahasa Indonesia.
     * Pastikan Carbon locale sudah diset id.
     */
    $tanggalPengajuan = optional($borrowing->created_at)->translatedFormat('d F Y H:i');
    $tanggalMulai = optional($borrowing->tanggal_mulai)->translatedFormat('d F Y H:i');
    $tanggalSelesai = optional($borrowing->tanggal_selesai)->translatedFormat('d F Y H:i');
    $tanggalSurat = optional($borrowing->created_at)->translatedFormat('d F Y');
@endphp

<body>

    <!-- =========================================================
         KOP SURAT
    ========================================================== -->

    @php
        $logoJateng = public_path('img/logo-jateng.png');
        $logoSmansa = public_path('img/logosmansa.png');
    @endphp

    <table class="kop-table">
        <tr>

            {{-- LOGO JAWA TENGAH --}}
            <td class="kop-logo-left">
                @if(file_exists($logoJateng))
                    <img
                        src="{{ $logoJateng }}"
                        alt="Logo Jawa Tengah" style="width: 92px; height: 102px;"
                    >
                @endif
            </td>

            {{-- IDENTITAS SEKOLAH --}}
            <td class="kop-content">

                <div class="kop-provinsi">
                    PEMERINTAH PROVINSI JAWA TENGAH
                </div>

                <div class="kop-dinas">
                    DINAS PENDIDIKAN
                </div>

                <div class="kop-sekolah">
                    SEKOLAH MENENGAH ATAS NEGERI 1
                </div>

                <div class="kop-sekolah">
                    PEKALONGAN
                </div>

                <div class="kop-alamat">
                    Jalan RA. Kartini Nomor 39 Kota Pekalongan Kode Pos. 51128
                </div>

                <div class="kop-kontak">
                    Telepon. (0285) 421190 &nbsp;&nbsp;
                    Faksimile. (0285) 432712 &nbsp;&nbsp;
                    email. sma1pkl@yahoo.com
                </div>

                <div class="kop-website">
                    website. www.sman1pekalongan.sch.id
                </div>

            </td>

            {{-- LOGO SEKOLAH --}}
            <td class="kop-logo-right">
                @if(file_exists($logoSmansa))
                    <img
                        src="{{ $logoSmansa }}"
                        alt="Logo SMAN 1 Pekalongan" style="width: 92px; height: 92px;"
                    >
                @endif
            </td>

        </tr>
    </table>

    <div class="kop-line"></div>

    <!-- =========================================================
         JUDUL SURAT
    ========================================================== -->

    <div class="document-title">
        <h1>
            Surat Persetujuan Permohonan Peminjaman
        </h1>

        <div class="document-number">
            Nomor Transaksi :
            <strong>{{ $borrowing->kode_transaksi }}</strong>
        </div>
    </div>

    <!-- =========================================================
         PEMBUKA
    ========================================================== -->

    <div class="intro">
        Dengan ini menerangkan bahwa permohonan peminjaman sarana dan prasarana
        pada SMA Negeri 1 Pekalongan telah diproses dan tercatat dalam sistem
        dengan rincian sebagai berikut:
    </div>

    <!-- =========================================================
         IDENTITAS PEMOHON
    ========================================================== -->

    <table class="identity">
        <tr>
            <td class="identity-label">
                Nama Pemohon
            </td>
            <td class="identity-separator">:</td>
            <td>
                {{ $borrowing->user?->name ?? '-' }}
            </td>
        </tr>

        <tr>
            <td class="identity-label">
                Nomor HP
            </td>
            <td class="identity-separator">:</td>
            <td>
                {{ $borrowing->user?->no_hp ?? $borrowing->user?->no_wa ?? '-' }}
            </td>
        </tr>

        <tr>
            <td class="identity-label">
                Tanggal Pengajuan
            </td>
            <td class="identity-separator">:</td>
            <td>
                {{ $tanggalPengajuan }}
            </td>
        </tr>

        <tr>
            <td class="identity-label">
                Waktu Peminjaman
            </td>
            <td class="identity-separator">:</td>
            <td>
                {{ $tanggalMulai }}
                s.d.
                {{ $tanggalSelesai }}
            </td>
        </tr>

        <tr>
            <td class="identity-label">
                Tujuan Kegiatan
            </td>
            <td class="identity-separator">:</td>
            <td>
                {{ $borrowing->tujuan ?: '-' }}
            </td>
        </tr>

        <tr>
            <td class="identity-label">
                Status Permohonan
            </td>
            <td class="identity-separator">:</td>
            <td class="status">
                {{ $borrowing->status ?: '-' }}
            </td>
        </tr>
    </table>

    <!-- =========================================================
         RINCIAN
    ========================================================== -->

    <div class="section-title">
        Rincian Sarana dan Prasarana yang Dipinjam
    </div>

    <table class="detail-table">
        <thead>
            <tr>
                <th width="8%">No.</th>
                <th width="14%">Jenis</th>
                <th>Nama Sarana / Prasarana</th>
                <th width="12%">Jumlah</th>
                <th width="17%">Status</th>
            </tr>
        </thead>

        <tbody>
            @forelse($borrowing->details as $index => $detail)
                <tr>
                    <td class="center">
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

                    <td class="center">
                        {{ $detail->jumlah }}
                    </td>

                    <td class="center">
                        {{ $detail->status ?: '-' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="center">
                        Tidak ada rincian fasilitas.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- =========================================================
         CATATAN
    ========================================================== -->

    @if($borrowing->catatan || $borrowing->catatan_admin)
        <div class="notes">

            @if($borrowing->catatan)
                <div class="note">
                    <span class="note-title">
                        Catatan Penggunaan:
                    </span>
                    {{ $borrowing->catatan }}
                </div>
            @endif

            @if($borrowing->catatan_admin)
                <div class="note">
                    <span class="note-title">
                        Catatan Admin:
                    </span>
                    {{ $borrowing->catatan_admin }}
                </div>
            @endif

        </div>
    @endif
   <!-- =========================================================
        TANDA TANGAN PEMOHON
    ========================================================= -->

    @php
    $signatureData = $borrowing->tanda_tangan;
    @endphp

    <table class="signature-table">
        <tr>
            <td class="signature-empty"></td>

            <td class="signature-cell">

                <div class="signature-location">
                    Pekalongan, {{ $tanggalSurat }}
                </div>

                <div class="signature-position">
                    Pemohon,
                </div>

                <div class="signature-image-wrapper">

                    @if(!empty($signatureData))

                        <img
                            src="{{ $signatureData }}"
                            class="signature-image"
                            alt="Tanda Tangan Pemohon">

                    @else

                        <div class="signature-placeholder"> 
                            <br>
                            <br>
                        </div>

                    @endif

                </div>

                <div class="signature-name">
                    {{ $borrowing->user?->name ?? '-' }}
                </div>

            </td>
        </tr>
    </table>


</body>

</html>