@extends('layouts.app')
@section('judul', $petugas->exists ? 'Ubah Petugas' : 'Petugas Baru')

@section('isi')
<form
    method="POST"
    action="{{ $petugas->exists ? route('petugas.update', $petugas) : route('petugas.store') }}"
    class="kartu max-w-lg p-4"
>
    @csrf
    @if ($petugas->exists) @method('PUT') @endif

    <div class="space-y-4">
        <div>
            <label for="nama" class="label">Nama</label>
            <input id="nama" name="nama" value="{{ old('nama', $petugas->nama) }}" required class="input">
        </div>

        <div>
            <label for="peran" class="label">Peran</label>
            <select id="peran" name="peran" required class="input">
                @foreach (['dokter' => 'Dokter', 'operator' => 'Operator', 'lainnya' => 'Lainnya'] as $nilai => $label)
                    <option value="{{ $nilai }}" @selected(old('peran', $petugas->peran) === $nilai)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <label class="flex items-center gap-2 text-sm text-ink-soft">
            <input type="checkbox" name="aktif" value="1" @checked(old('aktif', $petugas->aktif ?? true)) class="rounded border-rule">
            Aktif — muncul di pilihan nota keluar
        </label>
    </div>

    <div class="mt-5 flex flex-wrap gap-2 border-t border-rule pt-4">
        <button type="submit" class="tombol tombol-utama">Simpan</button>
        <a href="{{ route('petugas.index') }}" class="tombol tombol-biasa">Batal</a>

        @if ($petugas->exists)
            <span class="flex-1"></span>
            <button
                form="hapus-petugas"
                type="submit"
                class="tombol tombol-bahaya"
                onclick="return confirm('Hapus atau nonaktifkan petugas ini?')"
            >Hapus</button>
        @endif
    </div>
</form>

@if ($petugas->exists)
    <form id="hapus-petugas" method="POST" action="{{ route('petugas.destroy', $petugas) }}" class="hidden">
        @csrf @method('DELETE')
    </form>
    <p class="mt-3 max-w-lg text-xs text-ink-mute">
        Petugas yang sudah pernah mengambil barang akan dinonaktifkan, bukan dihapus —
        supaya nota lama tetap mencantumkan namanya.
    </p>
@endif
@endsection
