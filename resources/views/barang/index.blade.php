@extends('layouts.app')
@section('judul', 'Master Barang')

@php use App\Support\Format; @endphp

@section('aksi')
    <a href="{{ route('barang.create') }}" class="tombol tombol-utama">Tambah Barang</a>
@endsection

@section('isi')

<form method="GET" class="kartu mb-4 flex flex-wrap items-end gap-3 p-3">
    <div class="min-w-48 flex-1">
        <label for="cari" class="label">Cari</label>
        <input id="cari" name="cari" value="{{ request('cari') }}" placeholder="Nama atau kode barang" class="input">
    </div>

    <div class="min-w-40">
        <label for="kategori" class="label">Kategori</label>
        <select id="kategori" name="kategori" class="input">
            <option value="">Semua kategori</option>
            @foreach ($kategori as $k)
                <option value="{{ $k->id }}" @selected(request('kategori') == $k->id)>{{ $k->nama }}</option>
            @endforeach
        </select>
    </div>

    <label class="flex items-center gap-2 pb-2 text-sm text-ink-soft">
        <input type="checkbox" name="semua" value="1" @checked(request('semua')) class="rounded border-rule">
        Tampilkan yang nonaktif
    </label>

    <button type="submit" class="tombol tombol-biasa">Terapkan</button>
</form>

<div class="kartu overflow-hidden">
    <div class="overflow-x-auto">
        <table class="tabel">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama</th>
                    <th>Kategori</th>
                    <th>Satuan</th>
                    <th class="text-right">Stok</th>
                    <th class="text-right">Nilai</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftar as $b)
                    @php $stok = (float) ($saldo[$b->id] ?? 0); @endphp
                    <tr>
                        <td class="font-mono text-xs">{{ $b->kode }}</td>
                        <td>
                            <a href="{{ route('barang.edit', $b) }}" class="font-medium text-ink hover:text-accent hover:underline">
                                {{ $b->nama }}
                            </a>
                            @unless ($b->aktif)
                                <span class="lencana ml-1 bg-ground text-ink-mute">nonaktif</span>
                            @endunless
                            @if ($b->isi_nilai)
                                <span class="block text-xs text-ink-mute">
                                    1 {{ $b->satuan }} = {{ Format::qty($b->isi_nilai) }} {{ $b->isi_satuan }}
                                </span>
                            @endif
                        </td>
                        <td class="text-xs">{{ $b->kategori->nama }}</td>
                        <td class="text-xs">{{ $b->satuan }}</td>
                        <td @class([
                            'angka font-semibold',
                            'text-keluar' => (float) $b->stok_minimum > 0 && $stok <= (float) $b->stok_minimum,
                            'text-ink' => ! ((float) $b->stok_minimum > 0 && $stok <= (float) $b->stok_minimum),
                        ])>{{ Format::qty($stok) }}</td>
                        <td class="angka">{{ Format::rupiah($b->nilaiPersediaan()) }}</td>
                        <td class="text-right">
                            <a href="{{ route('barang.edit', $b) }}" class="text-xs font-semibold text-accent hover:underline">Ubah</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-ink-mute">
                            Belum ada barang yang cocok.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $daftar->links() }}</div>

@endsection
