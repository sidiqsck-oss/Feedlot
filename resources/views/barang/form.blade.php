@extends('layouts.app')
@section('judul', $barang->exists ? 'Ubah Barang' : 'Barang Baru')

@php use App\Support\Format; @endphp

@section('isi')

<div class="grid gap-5 lg:grid-cols-3">

    <form
        method="POST"
        action="{{ $barang->exists ? route('barang.update', $barang) : route('barang.store') }}"
        class="kartu p-4 lg:col-span-2"
    >
        @csrf
        @if ($barang->exists) @method('PUT') @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="kode" class="label">Kode</label>
                <input id="kode" name="kode" value="{{ old('kode', $barang->kode) }}" required class="input">
            </div>

            <div>
                <label for="kategori_barang_id" class="label">Kategori</label>
                <select id="kategori_barang_id" name="kategori_barang_id" required class="input">
                    @foreach ($kategori as $k)
                        <option value="{{ $k->id }}" @selected(old('kategori_barang_id', $barang->kategori_barang_id) == $k->id)>
                            {{ $k->nama }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="sm:col-span-2">
                <label for="nama" class="label">Nama Barang</label>
                <input id="nama" name="nama" value="{{ old('nama', $barang->nama) }}" required class="input">
            </div>

            <div>
                <label for="satuan" class="label">Satuan Stok</label>
                <input
                    id="satuan"
                    name="satuan"
                    value="{{ old('satuan', $barang->satuan) }}"
                    list="daftar-satuan"
                    required
                    class="input"
                >
                <datalist id="daftar-satuan">
                    <option value="botol"><option value="vial"><option value="tablet">
                    <option value="pcs"><option value="box"><option value="liter"><option value="sachet">
                </datalist>
                <p class="mt-1 text-xs text-ink-mute">Satuan yang dipakai saat menerima dan mengeluarkan barang.</p>
            </div>

            <div>
                <label for="stok_minimum" class="label">Stok Minimum</label>
                <input
                    id="stok_minimum"
                    name="stok_minimum"
                    type="number"
                    step="0.001"
                    min="0"
                    value="{{ old('stok_minimum', $barang->stok_minimum ?? 0) }}"
                    required
                    class="input"
                >
                <p class="mt-1 text-xs text-ink-mute">Isi 0 kalau tidak perlu diperingatkan.</p>
            </div>
        </div>

        <div class="mt-5 rounded-md border border-rule bg-ground p-3">
            <p class="text-sm font-semibold text-ink">Isi per satuan <span class="font-normal text-ink-mute">(opsional)</span></p>
            <p class="mt-1 text-xs text-ink-soft">
                Diisi hanya untuk barang yang perlu dihitung biayanya sampai satuan terkecil —
                misal 1 botol Limoxin berisi 100 ml, supaya dosis 20 ml di rekam medis dokter
                bisa dinilai rupiahnya. Sarung tangan atau pisau bedah tidak perlu diisi.
            </p>

            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="isi_nilai" class="label">Jumlah Isi</label>
                    <input
                        id="isi_nilai"
                        name="isi_nilai"
                        type="number"
                        step="0.001"
                        min="0"
                        value="{{ old('isi_nilai', $barang->isi_nilai) }}"
                        placeholder="100"
                        class="input"
                    >
                </div>
                <div>
                    <label for="isi_satuan" class="label">Satuan Isi</label>
                    <input
                        id="isi_satuan"
                        name="isi_satuan"
                        value="{{ old('isi_satuan', $barang->isi_satuan) }}"
                        placeholder="ml"
                        class="input"
                    >
                </div>
            </div>
        </div>

        <div class="mt-4">
            <label for="keterangan" class="label">Keterangan</label>
            <textarea id="keterangan" name="keterangan" rows="2" class="input">{{ old('keterangan', $barang->keterangan) }}</textarea>
        </div>

        <label class="mt-4 flex items-center gap-2 text-sm text-ink-soft">
            <input type="checkbox" name="aktif" value="1" @checked(old('aktif', $barang->aktif ?? true)) class="rounded border-rule">
            Aktif — muncul di pilihan transaksi
        </label>

        <div class="mt-5 flex flex-wrap gap-2 border-t border-rule pt-4">
            <button type="submit" class="tombol tombol-utama">Simpan</button>
            <a href="{{ route('barang.index') }}" class="tombol tombol-biasa">Batal</a>
        </div>
    </form>

    <div class="space-y-5">
        @if ($barang->exists)
            <div class="kartu p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-mute">Stok Saat Ini</p>
                <p class="angka mt-1 text-2xl font-bold text-ink">{{ Format::qtySatuan($stok, $barang->satuan) }}</p>
                <p class="mt-0.5 text-sm text-ink-mute">{{ Format::rupiah($nilai) }}</p>
                <a href="{{ route('laporan.kartu', ['barang' => $barang->id]) }}"
                   class="mt-3 block text-sm font-semibold text-accent hover:underline">Lihat kartu stok →</a>
            </div>

            {{-- Alias: nama versi dokter --}}
            <div class="kartu overflow-hidden">
                <div class="border-b border-rule px-4 py-3">
                    <h2 class="text-sm font-bold text-ink">Nama Versi Dokter</h2>
                    <p class="mt-0.5 text-xs text-ink-mute">
                        Dokter menulis nama obat dengan bebas di sheet-nya. Daftar ini yang
                        dipakai untuk mencocokkannya ke barang ini saat impor rekam medis.
                    </p>
                </div>

                <ul class="divide-y divide-rule">
                    @forelse ($barang->alias as $a)
                        <li class="flex items-center justify-between gap-2 px-4 py-2">
                            <span class="font-mono text-sm text-ink-soft">{{ $a->alias }}</span>
                            <form method="POST" action="{{ route('barang.alias.hapus', $a) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs font-semibold text-keluar hover:underline">Hapus</button>
                            </form>
                        </li>
                    @empty
                        <li class="px-4 py-4 text-center text-sm text-ink-mute">Belum ada alias.</li>
                    @endforelse
                </ul>

                <form method="POST" action="{{ route('barang.alias.tambah', $barang) }}" class="flex gap-2 border-t border-rule p-3">
                    @csrf
                    <input name="alias" placeholder="mis. limoxin 200" required class="input">
                    <button type="submit" class="tombol tombol-biasa shrink-0">Tambah</button>
                </form>
            </div>

            <form
                method="POST"
                action="{{ route('barang.destroy', $barang) }}"
                onsubmit="return confirm('Hapus atau nonaktifkan barang ini?')"
                class="kartu p-4"
            >
                @csrf @method('DELETE')
                <p class="text-sm text-ink-soft">
                    Barang yang sudah punya riwayat transaksi akan <strong>dinonaktifkan</strong>,
                    bukan dihapus — supaya nota lama tetap utuh.
                </p>
                <button type="submit" class="tombol tombol-bahaya mt-3">Hapus / Nonaktifkan</button>
            </form>
        @endif
    </div>
</div>

@endsection
