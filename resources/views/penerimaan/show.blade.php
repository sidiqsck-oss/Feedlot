@extends('layouts.app')
@section('judul', 'Nota ' . $penerimaan->nomor)

@php use App\Support\Format; @endphp

@section('aksi')
    <div class="flex gap-2">
        <a href="{{ route('penerimaan.cetak', $penerimaan) }}" target="_blank" class="tombol tombol-biasa">Cetak PDF</a>
        <a href="{{ route('penerimaan.create') }}" class="tombol tombol-utama">Nota Baru</a>
    </div>
@endsection

@section('isi')

<div class="kartu p-4">
    <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <dt class="label">Nomor</dt>
            <dd class="font-mono font-semibold text-ink">{{ $penerimaan->nomor }}</dd>
        </div>
        <div>
            <dt class="label">Tanggal</dt>
            <dd class="text-ink">{{ $penerimaan->tanggal->translatedFormat('d F Y') }}</dd>
        </div>
        <div>
            <dt class="label">Supplier</dt>
            <dd class="text-ink">{{ $penerimaan->supplier->nama }}</dd>
        </div>
        <div>
            <dt class="label">Purchase Order</dt>
            <dd>
                @if ($penerimaan->purchaseOrder)
                    <a href="{{ route('purchase-order.show', $penerimaan->purchaseOrder) }}"
                       class="font-mono text-accent hover:underline">{{ $penerimaan->purchaseOrder->nomor }}</a>
                @else
                    <span class="text-ink-mute">Tanpa PO</span>
                @endif
            </dd>
        </div>
        <div>
            <dt class="label">No. Faktur Supplier</dt>
            <dd class="text-ink">{{ $penerimaan->no_faktur_supplier ?: '—' }}</dd>
        </div>
        <div>
            <dt class="label">Dibuat Oleh</dt>
            <dd class="text-ink">{{ $penerimaan->pembuat->name }}</dd>
        </div>
        @if ($penerimaan->catatan)
            <div class="sm:col-span-2">
                <dt class="label">Catatan</dt>
                <dd class="text-ink-soft">{{ $penerimaan->catatan }}</dd>
            </div>
        @endif
    </dl>
</div>

<div class="kartu mt-4 overflow-hidden">
    <div class="border-b border-rule px-4 py-3">
        <h2 class="text-sm font-bold text-ink">Barang Diterima</h2>
        <p class="mt-0.5 text-xs text-ink-mute">Tiap baris membuat satu lot pembelian dengan harganya sendiri.</p>
    </div>

    <div class="overflow-x-auto">
        <table class="tabel">
            <thead>
                <tr>
                    <th>Barang</th><th class="text-right">Jumlah</th>
                    <th class="text-right">Harga Satuan</th><th class="text-right">Subtotal</th>
                    <th class="text-right">Sisa Lot</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($penerimaan->items as $item)
                    <tr>
                        <td>
                            <span class="font-medium text-ink">{{ $item->barang->nama }}</span>
                            <span class="block font-mono text-xs text-ink-mute">{{ $item->barang->kode }}</span>
                        </td>
                        <td class="angka">{{ Format::qtySatuan($item->qty, $item->barang->satuan) }}</td>
                        <td class="angka">{{ Format::rupiah($item->harga_satuan) }}</td>
                        <td class="angka font-semibold text-ink">{{ Format::rupiah($item->subtotal) }}</td>
                        <td class="angka">
                            @if ($item->lot)
                                <span class="{{ (float) $item->lot->qty_sisa > 0 ? 'text-ink' : 'text-ink-mute' }}">
                                    {{ Format::qty($item->lot->qty_sisa) }}
                                </span>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="bg-ground">
                    <td colspan="3" class="text-right font-bold text-ink">Total</td>
                    <td class="angka text-base font-bold text-masuk">{{ Format::rupiah($penerimaan->total()) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<p class="mt-3 max-w-2xl text-xs text-ink-mute">
    Nota yang sudah tersimpan tidak bisa diubah atau dihapus, karena kartu stok bersifat
    tambah-saja. Kalau ada yang salah, buat koreksi stok — riwayatnya tetap utuh.
</p>

@endsection
