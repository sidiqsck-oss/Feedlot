@extends('layouts.app')
@section('judul', 'Penjualan Sapi')

@php
use App\Support\Format;
use App\Support\FormatCpl;
@endphp

@section('aksi')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('cpl.penjualan.create') }}" class="tombol tombol-utama">Catat Penjualan</a>
        <x-tombol-unduh rute="cpl.penjualan.unduh" />
    </div>
@endsection

@section('isi')

<form method="GET" class="kartu mb-4 flex flex-wrap items-end gap-3 p-3">
    <div>
        <label for="dari" class="label">Dari Tanggal</label>
        <input id="dari" name="dari" type="date" value="{{ $saring['dari'] }}" class="input">
    </div>
    <div>
        <label for="sampai" class="label">Sampai Tanggal</label>
        <input id="sampai" name="sampai" type="date" value="{{ $saring['sampai'] }}" class="input">
    </div>
    <div>
        <label for="shipment" class="label">Shipment</label>
        <select id="shipment" name="shipment" class="input">
            <option value="">Semua</option>
            @foreach ($shipment as $s)
                <option value="{{ $s->kode }}" @selected($saring['shipment'] === $s->kode)>{{ $s->kode }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="customer" class="label">Customer</label>
        <select id="customer" name="customer" class="input">
            <option value="">Semua</option>
            @foreach ($pilihan['customer'] as $c)
                <option value="{{ $c }}" @selected($saring['customer'] === $c)>{{ $c }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="invoice" class="label">Invoice</label>
        <select id="invoice" name="invoice" class="input">
            <option value="">Semua</option>
            @foreach ($pilihan['invoice'] as $i)
                <option value="{{ $i }}" @selected($saring['invoice'] === $i)>{{ $i }}</option>
            @endforeach
        </select>
    </div>

    <button type="submit" class="tombol tombol-biasa">Terapkan</button>
    <a href="{{ route('cpl.penjualan.index') }}" class="tombol tombol-biasa">Reset</a>
</form>

<div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <div class="kartu p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-mute">Ekor Terjual</p>
        <p class="angka mt-1 text-2xl font-bold text-ink">{{ number_format($ringkasan['ekor'], 0, ',', '.') }}</p>
        <p class="mt-1 text-xs text-ink-mute">{{ $ringkasan['invoice'] }} invoice</p>
    </div>
    <div class="kartu p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-mute">Total Berat</p>
        <p class="angka mt-1 text-2xl font-bold text-ink">{{ FormatCpl::kg($ringkasan['berat']) }}</p>
    </div>
    <div class="kartu p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-mute">Nilai Penjualan</p>
        <p class="angka mt-1 text-2xl font-bold text-masuk">{{ Format::rupiah($ringkasan['nilai']) }}</p>
    </div>
    <div class="kartu p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-mute">Harga Rata-rata</p>
        <p class="angka mt-1 text-2xl font-bold text-ink">
            {{ $ringkasan['harga_rata'] === null ? '—' : Format::rupiah($ringkasan['harga_rata']) }}
        </p>
        <p class="mt-1 text-xs text-ink-mute">per kg, ditimbang berat</p>
    </div>
</div>

<div class="kartu overflow-hidden">
    <div class="overflow-x-auto">
        <table class="tabel">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th>Ship</th>
                    <th>Identitas</th>
                    <th class="text-right">Berat</th>
                    <th class="text-right">Harga/kg</th>
                    <th class="text-right">Total</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftar as $p)
                    <tr>
                        <td class="whitespace-nowrap">{{ $p->tanggal->format('d/m/Y') }}</td>
                        <td class="text-xs">
                            {{ $p->no_invoice ?: '—' }}
                            @if ($p->no_surat_jalan)
                                <span class="block text-ink-mute">SJ {{ $p->no_surat_jalan }}</span>
                            @endif
                        </td>
                        <td class="text-xs">{{ $p->customer ?: '—' }}</td>
                        <td class="font-mono text-xs">{{ $p->induksi->shipment->kode }}</td>
                        <td>
                            <span class="font-mono text-xs text-ink">{{ $p->induksi->rfid ?: '—' }}</span>
                            <span class="block font-mono text-xs text-ink-mute">ear tag {{ $p->induksi->ear_tag ?: '—' }}</span>
                        </td>
                        <td class="angka">{{ FormatCpl::kg($p->berat) }}</td>
                        <td class="angka">{{ $p->harga_per_kg === null ? '—' : Format::rupiah($p->harga_per_kg) }}</td>
                        <td class="angka font-semibold">{{ $p->total === null ? '—' : Format::rupiah($p->total) }}</td>
                        <td>
                            @if ($p->status_sapi)
                                <span @class([
                                    'lencana',
                                    'bg-tanda-soft text-tanda' => strtolower($p->status_sapi) === 'salvage',
                                    'bg-ground text-ink-soft' => strtolower($p->status_sapi) !== 'salvage',
                                ])>{{ $p->status_sapi }}</span>
                            @else
                                <span class="text-ink-mute">—</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <a href="{{ route('cpl.penjualan.edit', $p) }}"
                               class="text-xs font-semibold text-accent hover:underline">Ubah</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="py-8 text-center text-ink-mute">
                            Belum ada penjualan yang cocok dengan penyaring ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $daftar->links() }}</div>

<p class="mt-3 max-w-2xl text-xs text-ink-mute">
    Jalur utama penjualan tetap impor sheet Transaksi. Form di sini untuk koreksi satu ekor
    atau nota yang belum sempat masuk berkas.
</p>

@endsection
