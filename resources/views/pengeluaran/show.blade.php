@extends('layouts.app')
@section('judul', 'Nota ' . $pengeluaran->nomor)

@php use App\Support\Format; @endphp

@section('aksi')
    <div class="flex gap-2">
        <a href="{{ route('pengeluaran.cetak', $pengeluaran) }}" target="_blank" class="tombol tombol-biasa">Cetak PDF</a>
        <a href="{{ route('pengeluaran.create') }}" class="tombol tombol-utama">Nota Baru</a>
    </div>
@endsection

@section('isi')

<div class="kartu p-4">
    <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <dt class="label">Nomor</dt>
            <dd class="font-mono font-semibold text-ink">{{ $pengeluaran->nomor }}</dd>
        </div>
        <div>
            <dt class="label">Tanggal</dt>
            <dd class="text-ink">{{ $pengeluaran->tanggal->translatedFormat('d F Y') }}</dd>
        </div>
        <div>
            <dt class="label">Tujuan</dt>
            <dd><span class="lencana bg-ground text-ink-soft">{{ $pengeluaran->tujuan }}</span></dd>
        </div>
        <div>
            <dt class="label">Diambil Oleh</dt>
            <dd class="text-ink">{{ $pengeluaran->petugas?->nama ?: '—' }}</dd>
        </div>
        <div>
            <dt class="label">Shipment</dt>
            <dd class="font-mono text-ink">{{ $pengeluaran->shipment?->kode ?: '—' }}</dd>
        </div>
        <div>
            <dt class="label">Dibuat Oleh</dt>
            <dd class="text-ink">{{ $pengeluaran->pembuat->name }}</dd>
        </div>
        @if ($pengeluaran->catatan)
            <div class="sm:col-span-2">
                <dt class="label">Catatan</dt>
                <dd class="text-ink-soft">{{ $pengeluaran->catatan }}</dd>
            </div>
        @endif
    </dl>
</div>

<div class="kartu mt-4 overflow-hidden">
    <div class="border-b border-rule px-4 py-3">
        <h2 class="text-sm font-bold text-ink">Barang Keluar &amp; Asal Lot</h2>
        <p class="mt-0.5 text-xs text-ink-mute">
            Rincian di bawah tiap barang menunjukkan lot mana yang terpakai — inilah jejak FIFO-nya.
        </p>
    </div>

    <div class="overflow-x-auto">
        <table class="tabel">
            <thead>
                <tr>
                    <th>Barang</th><th class="text-right">Jumlah</th>
                    <th class="text-right">Harga Rata-rata</th><th class="text-right">Nilai</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pengeluaran->items as $item)
                    <tr>
                        <td>
                            <span class="font-medium text-ink">{{ $item->barang->nama }}</span>
                            <span class="block font-mono text-xs text-ink-mute">{{ $item->barang->kode }}</span>

                            <ul class="mt-1.5 space-y-0.5 border-l-2 border-rule pl-2.5">
                                @foreach ($item->alokasi as $a)
                                    <li class="text-xs text-ink-mute">
                                        Lot #{{ $a->stok_lot_id }} ({{ $a->lot->tanggal_masuk->format('d/m/y') }})
                                        — {{ Format::qty($a->qty) }} &times; {{ Format::rupiah($a->harga_satuan) }}
                                        = <span class="font-semibold">{{ Format::rupiah($a->subtotal) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </td>
                        <td class="angka align-top">{{ Format::qtySatuan($item->qty, $item->barang->satuan) }}</td>
                        <td class="angka align-top">{{ Format::rupiah($item->hargaRataRata()) }}</td>
                        <td class="angka align-top font-semibold text-ink">{{ Format::rupiah($item->nilai_hpp) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="bg-ground">
                    <td colspan="3" class="text-right font-bold text-ink">Total</td>
                    <td class="angka text-base font-bold text-keluar">{{ Format::rupiah($pengeluaran->totalHpp()) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

@endsection
