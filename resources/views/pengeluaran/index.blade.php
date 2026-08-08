@extends('layouts.app')
@section('judul', 'Barang Keluar')

@php use App\Support\Format; @endphp

@section('aksi')
    <a href="{{ route('pengeluaran.create') }}" class="tombol tombol-utama">Nota Baru</a>
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
    <div class="min-w-40">
        <label for="tujuan" class="label">Tujuan</label>
        <select id="tujuan" name="tujuan" class="input">
            <option value="">Semua tujuan</option>
            @foreach (['dokter', 'induksi', 'reweight', 'lainnya'] as $t)
                <option value="{{ $t }}" @selected(request('tujuan') === $t)>{{ ucfirst($t) }}</option>
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
                    <th>Nomor</th><th>Tanggal</th><th>Tujuan</th>
                    <th>Diambil Oleh</th><th>Shipment</th><th class="text-right">Nilai</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftar as $n)
                    <tr>
                        <td>
                            <a href="{{ route('pengeluaran.show', $n) }}" class="font-mono text-xs font-semibold text-accent hover:underline">
                                {{ $n->nomor }}
                            </a>
                        </td>
                        <td class="whitespace-nowrap">{{ $n->tanggal->format('d/m/Y') }}</td>
                        <td><span class="lencana bg-ground text-ink-soft">{{ $n->tujuan }}</span></td>
                        <td class="text-ink">{{ $n->petugas?->nama ?: '—' }}</td>
                        <td class="font-mono text-xs">{{ $n->shipment?->kode ?: '—' }}</td>
                        <td class="angka font-semibold text-keluar">{{ Format::rupiah($n->total_hpp) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-ink-mute">Belum ada nota barang keluar.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $daftar->links() }}</div>
@endsection
