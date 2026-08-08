@extends('layouts.app')
@section('judul', 'Biaya Obat per Ekor')

@php use App\Support\Format; @endphp

@section('aksi')
    <x-tombol-unduh rute="laporan.biaya-obat.unduh" />
@endsection

@section('isi')

<form method="GET" class="kartu mb-4 flex flex-wrap items-end gap-3 p-3">
    <div>
        <label for="dari" class="label">Dari Tanggal</label>
        <input id="dari" name="dari" type="date" value="{{ $saring['dari'] }}" class="input">
    </div>
    <div>
        <label for="sampai" class="label">Sampai Tanggal</label>
        <input id="sampai" name="sampai" type="date" value="{{ $saring['sampai'] }}" class="input">
    </div>
    <div>
        <label for="shipment" class="label">Shipment</label>
        <select id="shipment" name="shipment" class="input">
            <option value="">Semua</option>
            @foreach ($shipment as $s)
                <option value="{{ $s->kode }}" @selected($saring['shipment'] === $s->kode)>{{ $s->kode }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="ear_tag" class="label">Ear Tag</label>
        <input id="ear_tag" name="ear_tag" value="{{ $saring['ear_tag'] }}" placeholder="4250" class="input">
    </div>

    <button type="submit" class="tombol tombol-biasa">Terapkan</button>
    <a href="{{ route('laporan.biaya-obat') }}" class="tombol tombol-biasa">Reset</a>
</form>

<div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <div class="kartu p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-mute">Ekor Ditangani</p>
        <p class="angka mt-1 text-2xl font-bold text-ink">{{ number_format($ringkasan['ekor'], 0, ',', '.') }}</p>
        <p class="mt-1 text-xs text-ink-mute">{{ $ringkasan['perawatan'] }} kali perawatan</p>
    </div>
    <div class="kartu p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-mute">Total Biaya Obat</p>
        <p class="angka mt-1 text-2xl font-bold text-keluar">{{ Format::rupiah($ringkasan['total']) }}</p>
    </div>
    <div class="kartu p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-mute">Rata-rata per Ekor</p>
        <p class="angka mt-1 text-2xl font-bold text-ink">
            {{ $ringkasan['rata'] === null ? '—' : Format::rupiah($ringkasan['rata']) }}
        </p>
        <p class="mt-1 text-xs text-ink-mute">dari {{ $ringkasan['n_rata'] }} ekor yang bisa dihitung</p>
    </div>
    <div class="kartu p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-mute">Belum Bisa Dihitung</p>
        <p class="angka mt-1 text-2xl font-bold {{ $ringkasan['bermasalah'] > 0 ? 'text-tanda' : 'text-ink-mute' }}">
            {{ $ringkasan['bermasalah'] }}
        </p>
        <p class="mt-1 text-xs text-ink-mute">ekor punya baris yang belum bernilai</p>
    </div>
</div>

@if ($belumDipetakan->isNotEmpty())
    <div class="kartu mb-4 border-l-4 border-tanda p-4">
        <h2 class="text-sm font-bold text-ink">Nama Obat yang Belum Dipetakan</h2>
        <p class="mt-1 text-sm text-ink-soft">
            Dokter menulis nama ini tapi belum ada di master barang, jadi dosisnya belum
            bisa dinilai. Tambahkan aliasnya di
            <a href="{{ route('barang.index') }}" class="font-semibold text-accent hover:underline">Master Barang</a>,
            lalu angkanya langsung ikut terhitung tanpa perlu impor ulang.
        </p>
        <ul class="mt-3 flex flex-wrap gap-2">
            @foreach ($belumDipetakan as $b)
                <li class="lencana bg-tanda-soft text-tanda">
                    {{ $b->nama_obat_asli }} <span class="ml-1 font-normal">×{{ $b->jumlah }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif

<div class="kartu overflow-hidden">
    <div class="overflow-x-auto">
        <table class="tabel">
            <thead>
                <tr>
                    <th>Shipment</th>
                    <th>Ear Tag</th>
                    <th class="text-right">Perawatan</th>
                    <th>Periode</th>
                    <th>Diagnosa</th>
                    <th class="text-right">Obat</th>
                    <th class="text-right">Biaya Obat</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($baris as $b)
                    <tr>
                        <td class="font-mono text-xs">{{ $b['shipment'] }}</td>
                        <td class="font-mono text-xs text-ink">{{ $b['ear_tag'] ?: '—' }}</td>
                        <td class="angka">{{ $b['jumlah_treatment'] }}</td>
                        <td class="whitespace-nowrap text-xs">
                            {{ $b['tanggal_awal']?->format('d/m/y') }}
                            @if ($b['tanggal_akhir'] && ! $b['tanggal_akhir']->isSameDay($b['tanggal_awal']))
                                – {{ $b['tanggal_akhir']->format('d/m/y') }}
                            @endif
                        </td>
                        <td class="max-w-xs text-xs">
                            {{ $b['diagnosa'] ?: '—' }}
                            @foreach ($b['masalah'] as $m)
                                <span class="block text-tanda">{{ $m }}</span>
                            @endforeach
                        </td>
                        <td class="angka">{{ $b['jumlah_item'] }}</td>
                        <td class="angka font-semibold {{ $b['biaya'] === null ? 'text-ink-mute' : 'text-ink' }}">
                            {{ $b['biaya'] === null ? '—' : Format::rupiah($b['biaya']) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-ink-mute">
                            Belum ada rekam medis yang cocok dengan penyaring ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<p class="mt-3 max-w-3xl text-xs text-ink-mute">
    Dosis dokter dinilai dengan harga rata-rata <strong>pengambilan</strong> barang dari gudang
    sampai tanggal perawatan, bukan harga sisa stok hari ini — botol yang sudah disuntikkan
    bulan lalu harganya harga waktu itu. Halaman ini tidak memotong stok: stoknya sudah
    berkurang saat dokter mengambil barangnya.
</p>

@endsection
