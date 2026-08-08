@extends('cetak.layout')

@php use App\Support\Format; @endphp

@section('judul', 'Kartu Stok ' . $barang->nama)
@section('nama-dokumen', 'KARTU STOK')
@section('nomor-dokumen', $barang->nama . ' (' . $barang->kode . ')')

@section('isi')

<table class="ket">
    <tr>
        <td class="label">Satuan</td><td class="pemisah">:</td>
        <td class="nilai">{{ $barang->satuan }}</td>

        <td class="label">Periode</td><td class="pemisah">:</td>
        <td class="nilai">{{ $periode }}</td>
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            <th style="width: 58px;">Tanggal</th>
            <th style="width: 50px;">Jenis</th>
            <th>Keterangan</th>
            <th style="width: 56px;" class="kanan">Masuk</th>
            <th style="width: 56px;" class="kanan">Keluar</th>
            <th style="width: 56px;" class="kanan">Saldo</th>
            <th style="width: 74px;" class="kanan">Harga</th>
        </tr>
    </thead>
    <tbody>
        @php $saldo = $saldoAwal; @endphp

        <tr>
            <td colspan="5">Saldo awal</td>
            <td class="kanan">{{ Format::qty($saldoAwal) }}</td>
            <td></td>
        </tr>

        @foreach ($baris as $p)
            @php $saldo += (float) $p->qty; @endphp
            <tr>
                <td>{{ $p->tanggal->format('d/m/Y') }}</td>
                <td class="kecil">{{ $p->tipe }}</td>
                <td class="kecil">{{ $p->keterangan }}</td>
                <td class="kanan masuk">{{ (float) $p->qty > 0 ? Format::qty($p->qty) : '' }}</td>
                <td class="kanan keluar">{{ (float) $p->qty < 0 ? Format::qty(abs((float) $p->qty)) : '' }}</td>
                <td class="kanan">{{ Format::qty($saldo) }}</td>
                <td class="kanan kecil">{{ Format::rupiah($p->harga_satuan) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5" class="kanan">SALDO AKHIR</td>
            <td class="kanan">{{ Format::qty($saldo) }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>
@endsection

@section('catatan-kaki')
    Baris kartu stok bersifat tambah-saja; koreksi muncul sebagai baris tersendiri, bukan mengubah baris lama.
@endsection
