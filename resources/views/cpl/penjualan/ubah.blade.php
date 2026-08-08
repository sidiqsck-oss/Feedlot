@extends('layouts.app')
@section('judul', 'Ubah Penjualan')

@section('isi')

<form method="POST" action="{{ route('cpl.penjualan.update', $penjualan) }}" class="kartu max-w-2xl p-4">
    @csrf @method('PUT')

    {{-- Sapinya ditampilkan, bukan diubah. Kalau ekornya salah orang, barisnya
         dihapus lalu dicatat ulang — supaya tidak ada penjualan yang diam-diam
         berpindah sapi. --}}
    <div class="mb-4 rounded border border-rule bg-ground p-3 text-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-mute">Sapi</p>
        <p class="mt-1 font-mono text-ink">
            {{ $penjualan->induksi->shipment->kode }} ·
            {{ $penjualan->induksi->rfid ?: '—' }} ·
            ear tag {{ $penjualan->induksi->ear_tag ?: '—' }}
        </p>
        <p class="mt-1 text-xs text-ink-mute">
            Identitas sapi tidak bisa diubah di sini. Kalau salah ekor, hapus baris ini
            lalu catat ulang.
        </p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="tanggal" class="label">Tanggal</label>
            <input id="tanggal" name="tanggal" type="date" required class="input"
                   value="{{ old('tanggal', $penjualan->tanggal->format('Y-m-d')) }}">
        </div>

        <div>
            <label for="customer" class="label">Customer</label>
            <input id="customer" name="customer" required class="input"
                   value="{{ old('customer', $penjualan->customer) }}">
        </div>

        <div>
            <label for="no_invoice" class="label">No Invoice</label>
            <input id="no_invoice" name="no_invoice" class="input"
                   value="{{ old('no_invoice', $penjualan->no_invoice) }}">
        </div>

        <div>
            <label for="no_surat_jalan" class="label">No Surat Jalan</label>
            <input id="no_surat_jalan" name="no_surat_jalan" class="input"
                   value="{{ old('no_surat_jalan', $penjualan->no_surat_jalan) }}">
        </div>

        <div>
            <label for="kode_customer" class="label">Kode Customer</label>
            <input id="kode_customer" name="kode_customer" class="input"
                   value="{{ old('kode_customer', $penjualan->kode_customer) }}">
        </div>

        <div>
            <label for="nama_barang" class="label">Nama Barang</label>
            <input id="nama_barang" name="nama_barang" class="input"
                   value="{{ old('nama_barang', $penjualan->nama_barang) }}">
        </div>

        <div>
            <label for="berat" class="label">Berat (kg)</label>
            <input id="berat" name="berat" type="number" step="0.1" min="1" required class="input angka"
                   value="{{ old('berat', $penjualan->berat) }}">
        </div>

        <div>
            <label for="harga_per_kg" class="label">Harga per Kg</label>
            <input id="harga_per_kg" name="harga_per_kg" type="number" step="1" min="0" required class="input angka"
                   value="{{ old('harga_per_kg', $penjualan->harga_per_kg) }}">
            <p class="mt-1 text-xs text-ink-mute">Total dihitung ulang dari berat × harga.</p>
        </div>

        <div>
            <label for="realisasi" class="label">Realisasi</label>
            <input id="realisasi" name="realisasi" type="number" step="1" min="0" class="input angka"
                   value="{{ old('realisasi', $penjualan->realisasi) }}">
        </div>

        <div>
            <label for="potongan" class="label">Potongan</label>
            <input id="potongan" name="potongan" type="number" step="1" min="0" class="input angka"
                   value="{{ old('potongan', $penjualan->potongan) }}">
        </div>

        <div>
            <label for="satuan" class="label">Satuan</label>
            <input id="satuan" name="satuan" class="input" value="{{ old('satuan', $penjualan->satuan) }}">
        </div>

        <div>
            <label for="status_sapi" class="label">Status Sapi</label>
            <select id="status_sapi" name="status_sapi" class="input">
                <option value="">—</option>
                @foreach (['Sehat', 'Salvage'] as $s)
                    <option value="{{ $s }}" @selected(old('status_sapi', $penjualan->status_sapi) === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="mt-5 flex flex-wrap gap-2 border-t border-rule pt-4">
        <button type="submit" class="tombol tombol-utama">Simpan</button>
        <a href="{{ route('cpl.penjualan.index') }}" class="tombol tombol-biasa">Batal</a>
        <button type="submit" form="hapus-penjualan" class="tombol tombol-biasa ml-auto text-keluar"
                onclick="return confirm('Hapus baris penjualan ini?')">Hapus</button>
    </div>
</form>

<form id="hapus-penjualan" method="POST" action="{{ route('cpl.penjualan.destroy', $penjualan) }}" class="hidden">
    @csrf @method('DELETE')
</form>

@endsection
