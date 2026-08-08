<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>@yield('judul')</title>
    <style>
        /*
         * Dompdf hanya mendukung sebagian kecil CSS — tidak ada flexbox, grid,
         * maupun properti custom. Semua tata letak di sini memakai tabel dan
         * satuan mutlak, karena itu yang benar-benar dihormati mesin cetaknya.
         */
        @page { margin: 18mm 15mm 20mm 15mm; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9.5pt;
            color: #15201b;
            margin: 0;
        }

        .kop { border-bottom: 2px solid #15201b; padding-bottom: 8px; margin-bottom: 14px; }
        .kop-nama { font-size: 13pt; font-weight: bold; margin: 0; }
        .kop-sub { font-size: 8pt; color: #6c7f75; margin: 2px 0 0 0; }
        .kop-dok { font-size: 11pt; font-weight: bold; margin: 8px 0 0 0; }
        .kop-nomor { font-size: 9pt; color: #0e6b5a; margin: 1px 0 0 0; }

        table { width: 100%; border-collapse: collapse; }

        /* Blok keterangan dokumen: dua kolom, tanpa garis. */
        table.ket td { padding: 2px 0; vertical-align: top; font-size: 9pt; }
        table.ket td.label { color: #6c7f75; width: 90px; }
        table.ket td.pemisah { width: 8px; }
        table.ket td.nilai { font-weight: bold; }

        /* Tabel data utama. */
        table.data { margin-top: 12px; }
        table.data th {
            background: #edf1ec;
            border: 1px solid #c9d4cd;
            padding: 5px 6px;
            font-size: 8pt;
            text-align: left;
            text-transform: uppercase;
        }
        table.data td {
            border: 1px solid #dde4df;
            padding: 5px 6px;
            font-size: 9pt;
            vertical-align: top;
        }
        table.data tfoot td {
            background: #edf1ec;
            font-weight: bold;
            border-top: 1.5px solid #15201b;
        }

        .kanan { text-align: right; }
        .tengah { text-align: center; }
        .kecil { font-size: 8pt; color: #6c7f75; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 8pt; }
        .masuk { color: #2c7a33; }
        .keluar { color: #a63528; }

        /* Rincian lot FIFO di bawah tiap barang. */
        .rincian { margin: 3px 0 0 8px; padding-left: 6px; border-left: 2px solid #dde4df; }
        .rincian div { font-size: 7.5pt; color: #6c7f75; line-height: 1.5; }

        /*
         * Blok tanda tangan.
         *
         * Garisnya dipasang di <span> di dalam sel, bukan sebagai border-top
         * sel itu sendiri. Border sel bersebelahan akan menyambung jadi satu
         * garis panjang melintasi ketiga kolom, bukan tiga garis tanda tangan
         * yang terpisah.
         */
        table.ttd { margin-top: 28px; }
        table.ttd td { width: 33%; text-align: center; font-size: 9pt; padding: 0 10px; }
        table.ttd .ruang { height: 52px; }
        table.ttd .garis span {
            display: inline-block;
            border-top: 1px solid #15201b;
            padding-top: 4px;
            min-width: 130px;
        }

        .catatan-kaki {
            margin-top: 16px;
            padding-top: 6px;
            border-top: 1px solid #dde4df;
            font-size: 7.5pt;
            color: #6c7f75;
        }
    </style>
</head>
<body>

<div class="kop">
    <p class="kop-nama">SUMBER CIPTA KENCANA</p>
    <p class="kop-sub">Sistem Manajemen OVK &amp; Perbekalan Kesehatan</p>
    <p class="kop-dok">@yield('nama-dokumen')</p>
    <p class="kop-nomor">@yield('nomor-dokumen')</p>
</div>

@yield('isi')

<div class="catatan-kaki">
    Dicetak {{ now()->translatedFormat('d F Y, H:i') }} oleh {{ auth()->user()?->name ?? 'sistem' }}.
    @yield('catatan-kaki')
</div>

</body>
</html>
