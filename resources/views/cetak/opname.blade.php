@extends('cetak.layout')

@php use App\Support\Format; @endphp

@section('judul', 'Berita Acara Opname ' . $opname->nomor)
@section('nama-dokumen', 'BERITA ACARA STOK OPNAME')
@section('nomor-dokumen', $opname->nomor)

@section('isi')

<table class="ket">
    <tr>
        <td class="label">Periode</td><td class="pemisah">:</td>
        <td class="nilai">{{ Format::namaBulan($opname->periode_bulan) }} {{ $opname->periode_tahun }}</td>

        <td class="label">Status</td><td class="pemisah">:</td>
        <td class="nilai">{{ $opname->sudahFinal() ? 'Final' : 'Draft' }}</td>
    </tr>
    <tr>
        <td class="label">Tanggal hitung</td><td class="pemisah">:</td>
        <td class="nilai">{{ $opname->tanggal->translatedFormat('d F Y') }}</td>

        <td class="label">Petugas</td><td class="pemisah">:</td>
        <td class="nilai">{{ $opname->pembuat->name }}</td>
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            <th style="width: 24px;">No</th>
            <th>Barang</th>
            <th style="width: 62px;" class="kanan">Sistem</th>
            <th style="width: 62px;" class="kanan">Fisik</th>
            <th style="width: 62px;" class="kanan">Selisih</th>
            <th style="width: 88px;" class="kanan">Nilai Selisih</th>
            <th style="width: 96px;">Keterangan</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($opname->items as $i => $item)
            @php
                $selisih = $item->stok_fisik === null
                    ? null
                    : (float) $item->stok_fisik - (float) $item->stok_sistem;
            @endphp
            <tr>
                <td class="tengah">{{ $i + 1 }}</td>
                <td>
                    {{ $item->barang->nama }}
                    <span class="mono"> ({{ $item->barang->satuan }})</span>
                </td>
                <td class="kanan">{{ Format::qty($item->stok_sistem) }}</td>
                <td class="kanan">{{ $item->stok_fisik === null ? '-' : Format::qty($item->stok_fisik) }}</td>
                <td class="kanan @if ($selisih < 0) keluar @elseif ($selisih > 0) masuk @endif">
                    {{ $selisih === null ? '-' : ($selisih == 0 ? '0' : Format::bertanda($selisih)) }}
                </td>
                <td class="kanan @if ((float) $item->nilai_selisih < 0) keluar @endif">
                    {{ (float) $item->nilai_selisih == 0.0 ? '-' : Format::rupiah($item->nilai_selisih) }}
                </td>
                <td class="kecil">{{ $item->keterangan ?: '' }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5" class="kanan">TOTAL NILAI SELISIH</td>
            <td class="kanan">{{ Format::rupiah($opname->items->sum('nilai_selisih')) }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>

<table class="ttd">
    <tr>
        <td>Dihitung oleh</td>
        <td>Diperiksa oleh</td>
        <td>Disetujui oleh</td>
    </tr>
    <tr><td class="ruang"></td><td class="ruang"></td><td class="ruang"></td></tr>
    <tr>
        <td class="garis"><span>( {{ $opname->pembuat->name }} )</span></td>
        <td class="garis"><span>( ..................... )</span></td>
        <td class="garis"><span>( ..................... )</span></td>
    </tr>
</table>
@endsection

@section('catatan-kaki')
    Kolom Sistem dibekukan pada posisi tanggal hitung, sehingga transaksi setelahnya tidak mengubah selisih di atas.
@endsection
