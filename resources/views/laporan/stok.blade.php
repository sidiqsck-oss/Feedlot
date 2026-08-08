@extends('layouts.app')
@section('judul', 'Laporan Stok & Nilai')

@php use App\Support\Format; @endphp

@section('aksi')
    <x-tombol-unduh rute="laporan.stok.unduh" />
@endsection

@section('isi')

<form method="GET" class="kartu mb-4 flex flex-wrap items-end gap-3 p-3">
    <div>
        <label for="per_tanggal" class="label">Posisi Per Tanggal</label>
        <input id="per_tanggal" name="per_tanggal" type="date" value="{{ request('per_tanggal') }}" class="input">
        <p class="mt-1 text-xs text-ink-mute">Kosongkan untuk posisi terkini.</p>
    </div>
    <div class="min-w-44">
        <label for="kategori" class="label">Kategori</label>
        <select id="kategori" name="kategori" class="input">
            <option value="">Semua kategori</option>
            @foreach ($kategori as $k)
                <option value="{{ $k->id }}" @selected(request('kategori') == $k->id)>{{ $k->nama }}</option>
            @endforeach
        </select>
    </div>
    <label class="flex items-center gap-2 pb-2 text-sm text-ink-soft">
        <input type="checkbox" name="hanya_bersisa" value="1" @checked(request('hanya_bersisa')) class="rounded border-rule">
        Hanya yang bersisa
    </label>
    <button type="submit" class="tombol tombol-biasa">Terapkan</button>
</form>

<div class="kartu mb-4 p-4">
    <p class="text-xs font-semibold uppercase tracking-wide text-ink-mute">
        Total Nilai Persediaan{{ $perTanggal ? ' per ' . \Illuminate\Support\Carbon::parse($perTanggal)->format('d/m/Y') : '' }}
    </p>
    <p class="angka mt-1 text-2xl font-bold text-ink">{{ Format::rupiah($totalNilai) }}</p>
</div>

<div class="kartu overflow-hidden">
    <div class="overflow-x-auto">
        <table class="tabel">
            <thead>
                <tr>
                    <th>Kode</th><th>Barang</th><th>Kategori</th>
                    <th class="text-right">Stok</th><th class="text-right">Minimum</th><th class="text-right">Nilai</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftar as $b)
                    <tr>
                        <td class="font-mono text-xs">{{ $b->kode }}</td>
                        <td class="font-medium text-ink">{{ $b->nama }}</td>
                        <td class="text-xs">{{ $b->kategori->nama }}</td>
                        <td @class([
                            'angka font-semibold',
                            'text-keluar' => (float) $b->stok_minimum > 0 && $b->stok_kini <= (float) $b->stok_minimum,
                            'text-ink' => ! ((float) $b->stok_minimum > 0 && $b->stok_kini <= (float) $b->stok_minimum),
                        ])>{{ Format::qtySatuan($b->stok_kini, $b->satuan) }}</td>
                        <td class="angka text-ink-mute">{{ Format::qty($b->stok_minimum) }}</td>
                        <td class="angka">{{ Format::rupiah($b->nilai_kini) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-ink-mute">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="bg-ground">
                    <td colspan="5" class="text-right font-bold text-ink">Total</td>
                    <td class="angka text-base font-bold text-ink">{{ Format::rupiah($totalNilai) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<p class="mt-3 text-xs text-ink-mute">
    Nilai dihitung dari sisa tiap lot dikali harga belinya masing-masing, sesuai metode FIFO.
</p>
@endsection
