@extends('layouts.app')
@section('judul', 'Barang Masuk')

@php use App\Support\Format; @endphp

@section('aksi')
    <a href="{{ route('penerimaan.create') }}" class="tombol tombol-utama">Nota Baru</a>
@endsection

@section('isi')

<form method="GET" class="kartu mb-4 flex flex-wrap items-end gap-3 p-3">
    <div>
        <label for="dari" class="label">Dari Tanggal</label>
        <input id="dari" name="dari" type="date" value="{{ request('dari') }}" class="input">
    </div>
    <div>
        <label for="sampai" class="label">Sampai</label>
        <input id="sampai" name="sampai" type="date" value="{{ request('sampai') }}" class="input">
    </div>
    <div class="min-w-44">
        <label for="supplier" class="label">Supplier</label>
        <select id="supplier" name="supplier" class="input">
            <option value="">Semua supplier</option>
            @foreach ($supplier as $s)
                <option value="{{ $s->id }}" @selected(request('supplier') == $s->id)>{{ $s->nama }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="tombol tombol-biasa">Terapkan</button>
</form>

<div class="kartu overflow-hidden">
    <div class="overflow-x-auto">
        <table class="tabel">
            <thead>
                <tr>
                    <th>Nomor</th><th>Tanggal</th><th>Supplier</th>
                    <th>PO</th><th>Faktur</th><th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftar as $n)
                    <tr>
                        <td>
                            <a href="{{ route('penerimaan.show', $n) }}" class="font-mono text-xs font-semibold text-accent hover:underline">
                                {{ $n->nomor }}
                            </a>
                        </td>
                        <td class="whitespace-nowrap">{{ $n->tanggal->format('d/m/Y') }}</td>
                        <td class="text-ink">{{ $n->supplier->nama }}</td>
                        <td class="font-mono text-xs">{{ $n->purchaseOrder?->nomor ?: '—' }}</td>
                        <td class="text-xs">{{ $n->no_faktur_supplier ?: '—' }}</td>
                        <td class="angka font-semibold text-masuk">{{ Format::rupiah($n->total) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-ink-mute">Belum ada nota barang masuk.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $daftar->links() }}</div>
@endsection
