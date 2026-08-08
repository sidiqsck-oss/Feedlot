@extends('layouts.app')
@section('judul', $shipment->exists ? 'Ubah Shipment' : 'Shipment Baru')

@section('isi')
<form
    method="POST"
    action="{{ $shipment->exists ? route('shipment.update', $shipment) : route('shipment.store') }}"
    class="kartu max-w-lg p-4"
>
    @csrf
    @if ($shipment->exists) @method('PUT') @endif

    <div class="space-y-4">
        <div>
            <label for="kode" class="label">Kode</label>
            <input id="kode" name="kode" value="{{ old('kode', $shipment->kode) }}" placeholder="90" required class="input">
            <p class="mt-1 text-xs text-ink-mute">
                Cukup ketik angkanya — <span class="font-mono">90</span> otomatis jadi
                <span class="font-mono">SCK90</span>, sama seperti di form kertas.
            </p>
        </div>

        <div>
            <label for="tanggal_masuk" class="label">Tanggal Masuk</label>
            <input id="tanggal_masuk" name="tanggal_masuk" type="date"
                   value="{{ old('tanggal_masuk', $shipment->tanggal_masuk?->format('Y-m-d')) }}" class="input">
        </div>

        <div>
            <label for="keterangan" class="label">Keterangan</label>
            <input id="keterangan" name="keterangan" value="{{ old('keterangan', $shipment->keterangan) }}" class="input">
        </div>

        <label class="flex items-center gap-2 text-sm text-ink-soft">
            <input type="checkbox" name="aktif" value="1" @checked(old('aktif', $shipment->aktif ?? true)) class="rounded border-rule">
            Aktif
        </label>
    </div>

    <div class="mt-5 flex flex-wrap gap-2 border-t border-rule pt-4">
        <button type="submit" class="tombol tombol-utama">Simpan</button>
        <a href="{{ route('shipment.index') }}" class="tombol tombol-biasa">Batal</a>
    </div>
</form>
@endsection
