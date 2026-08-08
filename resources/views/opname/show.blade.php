@extends('layouts.app')
@section('judul', 'Opname ' . $opname->nomor)

@php use App\Support\Format; @endphp

@section('isi')

<div class="kartu p-4">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <dl class="grid flex-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="label">Nomor</dt>
                <dd class="font-mono font-semibold text-ink">{{ $opname->nomor }}</dd>
            </div>
            <div>
                <dt class="label">Periode</dt>
                <dd class="text-ink">{{ Format::namaBulan($opname->periode_bulan) }} {{ $opname->periode_tahun }}</dd>
            </div>
            <div>
                <dt class="label">Tanggal Hitung</dt>
                <dd class="text-ink">{{ $opname->tanggal->translatedFormat('d F Y') }}</dd>
            </div>
            <div>
                <dt class="label">Status</dt>
                <dd>
                    <span @class([
                        'lencana',
                        'bg-masuk-soft text-masuk' => $opname->sudahFinal(),
                        'bg-tanda-soft text-tanda' => ! $opname->sudahFinal(),
                    ])>{{ $opname->sudahFinal() ? 'Final' : 'Draft' }}</span>
                </dd>
            </div>
        </dl>
    </div>

    @if ($opname->sudahFinal())
        <p class="mt-4 rounded-md border border-masuk bg-masuk-soft px-3 py-2 text-sm text-masuk">
            Difinalkan {{ $opname->difinalkan_pada->format('d/m/Y H:i') }}. Selisihnya sudah masuk ke kartu stok
            dan tidak bisa diubah lagi — kalau ada yang keliru, buat koreksi stok.
        </p>
    @else
        <p class="mt-4 rounded-md border border-tanda bg-tanda-soft px-3 py-2 text-sm text-tanda">
            Stok sistem sudah dibekukan pada angka saat opname dibuat, jadi transaksi yang masuk
            setelah ini tidak mengubah kolom Sistem. Isi hasil hitungan fisik lalu finalkan.
        </p>
    @endif
</div>

<form method="POST" action="{{ route('opname.update', $opname) }}" class="mt-4">
    @csrf @method('PUT')

    <div class="kartu overflow-hidden">
        <div class="overflow-x-auto">
            <table class="tabel">
                <thead>
                    <tr>
                        <th>Barang</th>
                        <th class="text-right">Sistem</th>
                        <th class="text-right">Fisik</th>
                        <th class="text-right">Selisih</th>
                        @if ($opname->sudahFinal())
                            <th class="text-right">Nilai Selisih</th>
                        @endif
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($opname->items as $item)
                        @php
                            $selisih = $item->stok_fisik === null
                                ? null
                                : (float) $item->stok_fisik - (float) $item->stok_sistem;
                        @endphp
                        <tr>
                            <td>
                                <span class="font-medium text-ink">{{ $item->barang->nama }}</span>
                                <span class="block text-xs text-ink-mute">
                                    {{ $item->barang->kode }} · {{ $item->barang->satuan }}
                                </span>
                            </td>

                            <td class="angka">{{ Format::qty($item->stok_sistem) }}</td>

                            <td class="w-32">
                                @if ($opname->sudahFinal())
                                    <span class="angka block">{{ Format::qty($item->stok_fisik) }}</span>
                                @else
                                    <input
                                        type="number" step="0.001" min="0"
                                        name="fisik[{{ $item->id }}]"
                                        value="{{ $item->stok_fisik !== null ? (float) $item->stok_fisik : '' }}"
                                        class="input angka"
                                    >
                                @endif
                            </td>

                            <td @class([
                                'angka font-semibold',
                                'text-ink-mute' => $selisih === null || abs($selisih) < 0.0005,
                                'text-keluar' => $selisih !== null && $selisih < -0.0005,
                                'text-masuk' => $selisih !== null && $selisih > 0.0005,
                            ])>
                                {{ $selisih === null ? '—' : Format::bertanda($selisih) }}
                            </td>

                            @if ($opname->sudahFinal())
                                <td class="angka {{ (float) $item->nilai_selisih < 0 ? 'text-keluar' : 'text-ink-soft' }}">
                                    {{ Format::rupiah($item->nilai_selisih) }}
                                </td>
                            @endif

                            <td class="w-56">
                                @if ($opname->sudahFinal())
                                    <span class="text-xs">{{ $item->keterangan ?: '—' }}</span>
                                @else
                                    <input
                                        name="keterangan[{{ $item->id }}]"
                                        value="{{ $item->keterangan }}"
                                        placeholder="mis. rusak, tumpah"
                                        class="input"
                                    >
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @unless ($opname->sudahFinal())
        <div class="mt-4 flex flex-wrap gap-2">
            <button type="submit" class="tombol tombol-utama">Simpan Hitungan</button>
            <a href="{{ route('opname.index') }}" class="tombol tombol-biasa">Kembali</a>

            <span class="flex-1"></span>

            <button
                form="finalkan-opname"
                type="submit"
                class="tombol tombol-bahaya"
                onclick="return confirm('Finalkan opname ini? Selisihnya akan masuk ke kartu stok dan tidak bisa dibatalkan.')"
            >Finalkan Opname</button>
        </div>

        <p class="mt-3 max-w-2xl text-xs text-ink-mute">
            Semua barang harus sudah terisi hasil fisiknya sebelum bisa difinalkan.
            Barang yang tidak ada selisihnya cukup diisi angka yang sama dengan kolom Sistem.
        </p>
    @endunless
</form>

@unless ($opname->sudahFinal())
    <form id="finalkan-opname" method="POST" action="{{ route('opname.finalkan', $opname) }}" class="hidden">
        @csrf
    </form>
@endunless

@endsection
