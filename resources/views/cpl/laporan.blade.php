@extends('layouts.app')
@section('judul', 'Laporan CPL')

@section('aksi')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('cpl.laporan.unduh', request()->query()) }}" class="tombol tombol-biasa">CSV</a>
        <a href="{{ route('cpl.laporan.unduh', request()->query() + ['format' => 'excel']) }}" class="tombol tombol-biasa">Excel</a>
        <a href="{{ route('cpl.laporan.unduh', request()->query() + ['format' => 'pdf']) }}"
           target="_blank" class="tombol tombol-biasa">Cetak PDF</a>
        <a href="{{ route('cpl.closing', request()->query()) }}" class="tombol tombol-utama">Closing</a>
    </div>
@endsection

@section('isi')

{{-- Penyaring saling terhubung, sama seperti di dashboard --}}
<form method="GET" class="kartu mb-4 p-3">
    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label for="tanggal" class="label">Tanggal Jual</label>
            <select id="tanggal" name="tanggal" class="input">
                <option value="">— semua —</option>
                @foreach ($pilihan['tanggal'] as $t)
                    <option value="{{ $t }}" @selected($saring['tanggal'] === $t)>
                        {{ \Illuminate\Support\Carbon::parse($t)->translatedFormat('d M Y') }}
                    </option>
                @endforeach
            </select>
        </div>

        @foreach ([
            'shipment' => 'Shipment',
            'jenis' => 'Jenis',
            'customer' => 'Customer',
            'invoice' => 'Invoice',
            'status' => 'Status Jual',
        ] as $kunci => $label)
            <div class="min-w-36">
                <label for="{{ $kunci }}" class="label">{{ $label }}</label>
                <select id="{{ $kunci }}" name="{{ $kunci }}" class="input">
                    <option value="">Semua</option>
                    @foreach ($pilihan[$kunci] as $nilai)
                        <option value="{{ $nilai }}" @selected(($saring[$kunci] ?? null) === $nilai)>{{ $nilai }}</option>
                    @endforeach
                </select>
            </div>
        @endforeach

        <button type="submit" class="tombol tombol-biasa">Terapkan</button>
        <a href="{{ route('cpl.laporan') }}" class="tombol tombol-biasa">Reset</a>
    </div>
</form>

{{-- Personalisasi kolom, dibawa dari Streamlit. Semua tercentang sejak awal. --}}
<form method="GET" x-data="{ buka: false }" class="kartu mb-4 p-3">
    @foreach (request()->except(['sembunyikan', 'atur_kolom']) as $k => $v)
        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
    @endforeach
    <input type="hidden" name="atur_kolom" value="1">

    <button type="button" @click="buka = !buka" class="text-sm font-semibold text-accent">
        Personalisasi Kolom
        <span class="text-xs font-normal text-ink-mute">
            ({{ count($sembunyikan) }} dari {{ count($kolomOpsional) }} disembunyikan)
        </span>
    </button>

    <div x-show="buka" x-cloak class="mt-3 border-t border-rule pt-3">
        <p class="mb-2 text-xs text-ink-mute">
            Centang berarti kolomnya disembunyikan. Pilihanmu diingat sampai diubah lagi.
        </p>

        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($kolomOpsional as $kunci => $label)
                <label class="flex items-center gap-2 text-sm text-ink-soft">
                    <input type="checkbox" name="sembunyikan[]" value="{{ $kunci }}"
                           @checked(in_array($kunci, $sembunyikan, true)) class="rounded border-rule">
                    {{ $label }}
                </label>
            @endforeach
        </div>

        <button type="submit" class="tombol tombol-utama mt-3">Terapkan Kolom</button>
    </div>
</form>

@if (($saring['bawaan_10_invoice'] ?? false) && $baris->isNotEmpty())
    <p class="mb-4 rounded-md border border-tanda bg-tanda-soft px-3 py-2 text-sm text-tanda">
        Belum ada tanggal atau invoice yang dipilih, jadi yang ditampilkan
        <strong>10 invoice terakhir</strong>.
    </p>
@endif

@if ($baris->isEmpty())
    <div class="kartu p-10 text-center text-ink-mute">
        Tidak ada sapi terjual dengan penyaring ini.
    </div>
@else
    <div class="space-y-4">
        @foreach ($perCustomer as $namaCustomer => $barisCustomer)
            <x-cpl-tabel
                :baris="$barisCustomer"
                :sembunyikan="$sembunyikan"
                :subjudul="'Cattle Performance Log · ' . $namaCustomer . ' · ' . $barisCustomer->count() . ' ekor'"
            />
        @endforeach

        @if ($perCustomer->count() > 1)
            <x-cpl-tabel
                :baris="$baris"
                :sembunyikan="$sembunyikan"
                :subjudul="'Cattle Performance Log · GABUNGAN SEMUA CUSTOMER · ' . $baris->count() . ' ekor'"
            />
        @endif
    </div>
@endif

@endsection
