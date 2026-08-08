@extends('cetak.layout')

@php use App\Support\Format; @endphp

@section('judul', 'Nota Barang Keluar ' . $pengeluaran->nomor)
@section('nama-dokumen', 'NOTA BARANG KELUAR')
@section('nomor-dokumen', $pengeluaran->nomor)

@section('isi')

<table class="ket">
    <tr>
        <td class="label">Tanggal</td><td class="pemisah">:</td>
        <td class="nilai">{{ $pengeluaran->tanggal->translatedFormat('d F Y') }}</td>

        <td class="label">Tujuan</td><td class="pemisah">:</td>
        <td class="nilai">{{ ucfirst($pengeluaran->tujuan) }}</td>
    </tr>
    <tr>
        <td class="label">Diambil oleh</td><td class="pemisah">:</td>
        <td class="nilai">{{ $pengeluaran->petugas?->nama ?: '-' }}</td>

        <td class="label">Shipment</td><td class="pemisah">:</td>
        <td class="nilai">{{ $pengeluaran->shipment?->kode ?: '-' }}</td>
    </tr>
    @if ($pengeluaran->catatan)
        <tr>
            <td class="label">Catatan</td><td class="pemisah">:</td>
            <td colspan="4">{{ $pengeluaran->catatan }}</td>
        </tr>
    @endif
</table>

<table class="data">
    <thead>
        <tr>
            <th style="width: 24px;">No</th>
            <th>Barang &amp; Asal Lot</th>
            <th style="width: 78px;" class="kanan">Jumlah</th>
            <th style="width: 88px;" class="kanan">Harga Rata-rata</th>
            <th style="width: 98px;" class="kanan">Nilai</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($pengeluaran->items as $i => $item)
            <tr>
                <td class="tengah">{{ $i + 1 }}</td>
                <td>
                    {{ $item->barang->nama }}
                    <span class="mono"> ({{ $item->barang->kode }})</span>

                    <div class="rincian">
                        @foreach ($item->alokasi as $a)
                            <div>
                                Lot #{{ $a->stok_lot_id }} ({{ $a->lot->tanggal_masuk->format('d/m/y') }}) —
                                {{ Format::qty($a->qty) }} &times; {{ Format::rupiah($a->harga_satuan) }}
                                = {{ Format::rupiah($a->subtotal) }}
                            </div>
                        @endforeach
                    </div>
                </td>
                <td class="kanan">{{ Format::qtySatuan($item->qty, $item->barang->satuan) }}</td>
                <td class="kanan">{{ Format::rupiah($item->hargaRataRata()) }}</td>
                <td class="kanan">{{ Format::rupiah($item->nilai_hpp) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4" class="kanan">TOTAL</td>
            <td class="kanan">{{ Format::rupiah($pengeluaran->totalHpp()) }}</td>
        </tr>
    </tfoot>
</table>

<table class="ttd">
    <tr>
        <td>Diserahkan oleh</td>
        <td>Mengetahui</td>
        <td>Diterima oleh</td>
    </tr>
    <tr><td class="ruang"></td><td class="ruang"></td><td class="ruang"></td></tr>
    <tr>
        <td class="garis"><span>( {{ $pengeluaran->pembuat->name }} )</span></td>
        <td class="garis"><span>( ..................... )</span></td>
        <td class="garis"><span>( {{ $pengeluaran->petugas?->nama ?: '.....................' }} )</span></td>
    </tr>
</table>
@endsection

@section('catatan-kaki')
    Nilai dihitung dengan metode FIFO; rincian lot di atas menunjukkan asal tiap angkanya.
@endsection
