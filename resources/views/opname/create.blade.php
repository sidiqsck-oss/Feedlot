@extends('layouts.app')
@section('judul', 'Opname Baru')

@php use App\Support\Format; @endphp

@section('isi')

<div class="grid gap-5 lg:grid-cols-3">
    <form method="POST" action="{{ route('opname.store') }}" class="kartu p-4 lg:col-span-2">
        @csrf

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="periode_bulan" class="label">Bulan Periode</label>
                <select id="periode_bulan" name="periode_bulan" required class="input">
                    @foreach (range(1, 12) as $b)
                        <option value="{{ $b }}" @selected(old('periode_bulan', $bulanUsulan) == $b)>
                            {{ Format::namaBulan($b) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="periode_tahun" class="label">Tahun Periode</label>
                <input id="periode_tahun" name="periode_tahun" type="number" min="2020" max="2100"
                       value="{{ old('periode_tahun', $tahunUsulan) }}" required class="input">
            </div>

            <div class="sm:col-span-2">
                <label for="tanggal" class="label">Tanggal Penghitungan Fisik</label>
                <input id="tanggal" name="tanggal" type="date"
                       value="{{ old('tanggal', now()->toDateString()) }}" required class="input">
                <p class="mt-1 text-xs text-ink-mute">
                    Stok sistem akan dibekukan pada posisi tanggal ini.
                </p>
            </div>

            <div class="sm:col-span-2">
                <label for="catatan" class="label">Catatan</label>
                <textarea id="catatan" name="catatan" rows="2" class="input">{{ old('catatan') }}</textarea>
            </div>
        </div>

        <div class="mt-5 flex flex-wrap gap-2 border-t border-rule pt-4">
            <button type="submit" class="tombol tombol-utama">Buat Opname</button>
            <a href="{{ route('opname.index') }}" class="tombol tombol-biasa">Batal</a>
        </div>
    </form>

    <div class="space-y-4">
        <div class="kartu p-4">
            <h2 class="text-sm font-bold text-ink">Cara kerjanya</h2>
            <ol class="mt-2 list-decimal space-y-1.5 pl-4 text-sm text-ink-soft">
                <li>Sistem mendaftar semua barang aktif beserta stoknya saat ini</li>
                <li>Angka itu <strong>dibekukan</strong>, jadi transaksi setelahnya tidak mengubahnya</li>
                <li>Kamu isi hasil hitungan fisik di gudang</li>
                <li>Setelah difinalkan, selisihnya masuk ke kartu stok</li>
            </ol>
        </div>

        @if ($sudahAda->isNotEmpty())
            <div class="kartu overflow-hidden">
                <div class="border-b border-rule px-4 py-3">
                    <h2 class="text-sm font-bold text-ink">Opname Terakhir</h2>
                </div>
                <ul class="divide-y divide-rule text-sm">
                    @foreach ($sudahAda as $o)
                        <li class="flex items-center justify-between gap-2 px-4 py-2">
                            <a href="{{ route('opname.show', $o) }}" class="text-accent hover:underline">
                                {{ Format::namaBulan($o->periode_bulan) }} {{ $o->periode_tahun }}
                            </a>
                            <span @class([
                                'lencana',
                                'bg-masuk-soft text-masuk' => $o->sudahFinal(),
                                'bg-tanda-soft text-tanda' => ! $o->sudahFinal(),
                            ])>{{ $o->sudahFinal() ? 'Final' : 'Draft' }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>

@endsection
