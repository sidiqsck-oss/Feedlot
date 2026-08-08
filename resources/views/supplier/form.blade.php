@extends('layouts.app')
@section('judul', $supplier->exists ? 'Ubah Supplier' : 'Supplier Baru')

@section('isi')
<form
    method="POST"
    action="{{ $supplier->exists ? route('supplier.update', $supplier) : route('supplier.store') }}"
    class="kartu max-w-2xl p-4"
>
    @csrf
    @if ($supplier->exists) @method('PUT') @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="kode" class="label">Kode</label>
            <input id="kode" name="kode" value="{{ old('kode', $supplier->kode) }}" required class="input">
        </div>
        <div>
            <label for="nama" class="label">Nama Supplier</label>
            <input id="nama" name="nama" value="{{ old('nama', $supplier->nama) }}" required class="input">
        </div>
        <div>
            <label for="kontak" class="label">Nama Kontak</label>
            <input id="kontak" name="kontak" value="{{ old('kontak', $supplier->kontak) }}" class="input">
        </div>
        <div>
            <label for="telepon" class="label">Telepon</label>
            <input id="telepon" name="telepon" value="{{ old('telepon', $supplier->telepon) }}" class="input">
        </div>
        <div class="sm:col-span-2">
            <label for="alamat" class="label">Alamat</label>
            <textarea id="alamat" name="alamat" rows="2" class="input">{{ old('alamat', $supplier->alamat) }}</textarea>
        </div>
    </div>

    <label class="mt-4 flex items-center gap-2 text-sm text-ink-soft">
        <input type="checkbox" name="aktif" value="1" @checked(old('aktif', $supplier->aktif ?? true)) class="rounded border-rule">
        Aktif
    </label>

    <div class="mt-5 flex flex-wrap gap-2 border-t border-rule pt-4">
        <button type="submit" class="tombol tombol-utama">Simpan</button>
        <a href="{{ route('supplier.index') }}" class="tombol tombol-biasa">Batal</a>

        @if ($supplier->exists)
            <span class="flex-1"></span>
            <button
                form="hapus-supplier"
                type="submit"
                class="tombol tombol-bahaya"
                onclick="return confirm('Hapus atau nonaktifkan supplier ini?')"
            >Hapus</button>
        @endif
    </div>
</form>

@if ($supplier->exists)
    <form id="hapus-supplier" method="POST" action="{{ route('supplier.destroy', $supplier) }}" class="hidden">
        @csrf @method('DELETE')
    </form>
@endif
@endsection
