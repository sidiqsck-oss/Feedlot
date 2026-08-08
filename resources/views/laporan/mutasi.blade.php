@extends('layouts.app')
@section('judul', 'Laporan Masuk & Keluar')

@php use App\Support\Format; @endphp

@section('isi')

<form method="GET" class="kartu mb-4 flex flex-wrap items-end gap-3 p-3">
    <div>
        <label for="dari" class="label">Dari Tanggal</label>
        <input id="dari" name="dari" type="date" value="{{ $dari }}" class="input">
    </div>
    <div>
        <label for="sampai" class="label">Sampai Tanggal</label>
        <input id="sampai" name="sampai" type="date" value="{{ $sampai }}" class="input">
    </div>
    <button type="submit" class="tombol tombol-biasa">Terapkan</button>
</form>

<div class="mb-4 grid gap-3 sm:grid-cols-2">
    <div class="kartu p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-mute">Nilai Barang Masuk</p>
        <p class="angka mt-1 text-2xl font-bold text-masuk">{{ Format::rupiah($totalMasuk) }}</p>
    </div>
    <div class="kartu p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-mute">Nilai Barang Keluar</p>
        <p class="angka mt-1 text-2xl font-bold text-keluar">{{ Format::rupiah($totalKeluar) }}</p>
    </div>
</div>

<div class="kartu overflow-hidden">
    <div class="overflow-x-auto">
        <table class="tabel">
            <thead>
                <tr>
                    <th rowspan="2" class="align-bottom">Barang</th>
                    <th rowspan="2" class="align-bottom text-right">Saldo Awal</th>
                    <th colspan="2" class="text-center">Masuk</th>
                    <th colspan="2" class="text-center">Keluar</th>
                    <th rowspan="2" class="align-bottom text-right">Saldo Akhir</th>
                </tr>
                <tr>
                    <th class="text-right">Qty</th><th class="text-right">Nilai</th>
                    <th class="text-right">Qty</th><th class="text-right">Nilai</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftar as $b)
                    <tr>
                        <td>
                            <span class="font-medium text-ink">{{ $b->nama }}</span>
                            <span class="block font-mono text-xs text-ink-mute">{{ $b->kode }} · {{ $b->satuan }}</span>
                        </td>
                        <td class="angka">{{ Format::qty($b->saldo_awal) }}</td>
                        <td class="angka text-masuk">{{ $b->qty_masuk ? Format::qty($b->qty_masuk) : '—' }}</td>
                        <td class="angka text-masuk">{{ $b->nilai_masuk ? Format::rupiah($b->nilai_masuk) : '—' }}</td>
                        <td class="angka text-keluar">{{ $b->qty_keluar ? Format::qty($b->qty_keluar) : '—' }}</td>
                        <td class="angka text-keluar">{{ $b->nilai_keluar ? Format::rupiah($b->nilai_keluar) : '—' }}</td>
                        <td class="angka font-semibold text-ink">{{ Format::qty($b->saldo_akhir) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-ink-mute">Tidak ada pergerakan di rentang ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<p class="mt-3 max-w-2xl text-xs text-ink-mute">
    Saldo awal dihitung dari seluruh pergerakan sebelum tanggal mulai, jadi rentang mana pun
    tetap berimbang: saldo awal + masuk − keluar = saldo akhir.
</p>
@endsection
