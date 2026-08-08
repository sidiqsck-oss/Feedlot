@extends('layouts.app')
@section('judul', 'Dashboard OVK')

@php use App\Support\Format; @endphp

@section('isi')

<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <div class="kartu p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-mute">Nilai Persediaan</p>
        <p class="angka mt-1 text-2xl font-bold text-ink">{{ Format::rupiah($nilaiPersediaan) }}</p>
        <p class="mt-0.5 text-xs text-ink-mute">{{ $jumlahBarang }} barang aktif</p>
    </div>

    <div class="kartu p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-mute">Masuk Bulan Ini</p>
        <p class="angka mt-1 text-2xl font-bold text-masuk">{{ Format::rupiah($masukBulanIni) }}</p>
    </div>

    <div class="kartu p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-mute">Keluar Bulan Ini</p>
        <p class="angka mt-1 text-2xl font-bold text-keluar">{{ Format::rupiah($keluarBulanIni) }}</p>
    </div>

    <div class="kartu p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-mute">Opname {{ Format::namaBulan(now()->month) }}</p>
        @if ($opnameBulanIni)
            <p class="mt-1 text-2xl font-bold {{ $opnameBulanIni->sudahFinal() ? 'text-masuk' : 'text-tanda' }}">
                {{ $opnameBulanIni->sudahFinal() ? 'Selesai' : 'Draft' }}
            </p>
            <a href="{{ route('opname.show', $opnameBulanIni) }}" class="mt-0.5 block text-xs text-accent hover:underline">
                {{ $opnameBulanIni->nomor }}
            </a>
        @else
            <p class="mt-1 text-2xl font-bold text-ink-mute">Belum</p>
            <a href="{{ route('opname.create') }}" class="mt-0.5 block text-xs text-accent hover:underline">Buat opname</a>
        @endif
    </div>
</div>

@if ($poBerjalan > 0)
    <div class="mt-3 rounded-md border border-tanda bg-tanda-soft px-4 py-3 text-sm text-tanda">
        Ada <strong>{{ $poBerjalan }}</strong> purchase order yang masih berjalan.
        <a href="{{ route('purchase-order.index') }}" class="font-semibold underline">Lihat</a>
    </div>
@endif

<div class="mt-5 grid gap-5 lg:grid-cols-2">

    {{-- Stok menipis --}}
    <section class="kartu overflow-hidden">
        <div class="border-b border-rule px-4 py-3">
            <h2 class="text-sm font-bold text-ink">Stok Menipis</h2>
            <p class="text-xs text-ink-mute">Sudah di bawah atau sama dengan batas minimum</p>
        </div>

        @if ($menipis->isEmpty())
            <p class="px-4 py-6 text-center text-sm text-ink-mute">
                Tidak ada barang yang menipis.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <thead>
                        <tr>
                            <th>Barang</th>
                            <th class="text-right">Sisa</th>
                            <th class="text-right">Minimum</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($menipis as $b)
                            <tr>
                                <td>
                                    <span class="font-medium text-ink">{{ $b->nama }}</span>
                                    <span class="block text-xs text-ink-mute">{{ $b->kategori->nama }}</span>
                                </td>
                                <td class="angka font-semibold text-keluar">
                                    {{ Format::qtySatuan($saldo[$b->id] ?? 0, $b->satuan) }}
                                </td>
                                <td class="angka text-ink-mute">{{ Format::qty($b->stok_minimum) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Kartu stok terakhir --}}
    <section class="kartu overflow-hidden">
        <div class="border-b border-rule px-4 py-3">
            <h2 class="text-sm font-bold text-ink">Pergerakan Terakhir</h2>
            <p class="text-xs text-ink-mute">Dari kartu stok, urut dari yang terbaru</p>
        </div>

        @if ($pergerakanTerakhir->isEmpty())
            <p class="px-4 py-6 text-center text-sm text-ink-mute">
                Belum ada transaksi.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Barang</th>
                            <th>Jenis</th>
                            <th class="text-right">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pergerakanTerakhir as $p)
                            <tr>
                                <td class="whitespace-nowrap text-xs">{{ $p->tanggal->format('d/m/y') }}</td>
                                <td class="text-ink">{{ $p->barang->nama }}</td>
                                <td>
                                    <span @class([
                                        'lencana',
                                        'bg-masuk-soft text-masuk' => $p->tipe === 'masuk',
                                        'bg-keluar-soft text-keluar' => $p->tipe === 'keluar',
                                        'bg-tanda-soft text-tanda' => in_array($p->tipe, ['opname', 'koreksi']),
                                    ])>{{ $p->tipe }}</span>
                                </td>
                                <td class="angka font-semibold {{ (float) $p->qty < 0 ? 'text-keluar' : 'text-masuk' }}">
                                    {{ Format::bertanda($p->qty) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>

@endsection
