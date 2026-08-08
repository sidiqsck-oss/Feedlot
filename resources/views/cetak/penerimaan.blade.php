@extends('cetak.layout')

@php use App\Support\Format; @endphp

@section('judul', 'Nota Barang Masuk ' . $penerimaan->nomor)
@section('nama-dokumen', 'NOTA BARANG MASUK')
@section('nomor-dokumen', $penerimaan->nomor)

@section('isi')

<table class="ket">
    <tr>
        <td class="label">Tanggal</td><td class="pemisah">:</td>
        <td class="nilai">{{ $penerimaan->tanggal->translatedFormat('d F Y') }}</td>

        <td class="label">Purchase Order</td><td class="pemisah">:</td>
        <td class="nilai">{{ $penerimaan->purchaseOrder?->nomor ?: 'Tanpa PO' }}</td>
    </tr>
    <tr>
        <td class="label">Supplier</td><td class="pemisah">:</td>
        <td class="nilai">{{ $penerimaan->supplier->nama }}</td>

        <td class="label">No. Faktur</td><td class="pemisah">:</td>
        <td class="nilai">{{ $penerimaan->no_faktur_supplier ?: '-' }}</td>
    </tr>
    @if ($penerimaan->catatan)
        <tr>
            <td class="label">Catatan</td><td class="pemisah">:</td>
            <td colspan="4">{{ $penerimaan->catatan }}</td>
        </tr>
    @endif
</table>

<table class="data">
    <thead>
        <tr>
            <th style="width: 24px;">No</th>
            <th>Barang</th>
            <th style="width: 78px;" class="kanan">Jumlah</th>
            <th style="width: 88px;" class="kanan">Harga Satuan</th>
            <th style="width: 98px;" class="kanan">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($penerimaan->items as $i => $item)
            <tr>
                <td class="tengah">{{ $i + 1 }}</td>
                <td>
                    {{ $item->barang->nama }}
                    <span class="mono"> ({{ $item->barang->kode }})</span>
                </td>
                <td class="kanan">{{ Format::qtySatuan($item->qty, $item->barang->satuan) }}</td>
                <td class="kanan">{{ Format::rupiah($item->harga_satuan) }}</td>
                <td class="kanan">{{ Format::rupiah($item->subtotal) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4" class="kanan">TOTAL</td>
            <td class="kanan">{{ Format::rupiah($penerimaan->total()) }}</td>
        </tr>
    </tfoot>
</table>

<table class="ttd">
    <tr>
        <td>Diserahkan oleh</td>
        <td>Diperiksa oleh</td>
        <td>Diterima oleh</td>
    </tr>
    <tr><td class="ruang"></td><td class="ruang"></td><td class="ruang"></td></tr>
    <tr>
        <td class="garis"><span>( {{ $penerimaan->supplier->kontak ?: '.....................' }} )</span></td>
        <td class="garis"><span>( ..................... )</span></td>
        <td class="garis"><span>( {{ $penerimaan->pembuat->name }} )</span></td>
    </tr>
</table>
@endsection

@section('catatan-kaki')
    Nota ini tidak dapat diubah setelah tersimpan; koreksi dilakukan lewat jurnal koreksi stok.
@endsection
