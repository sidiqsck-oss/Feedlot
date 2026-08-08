@extends('layouts.app')
@section('judul', 'Pratinjau Impor')

@section('isi')

<div class="kartu p-4">
    <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <dt class="label">Berkas</dt>
            <dd class="font-medium text-ink">{{ $batch->nama_berkas }}</dd>
        </div>
        <div>
            <dt class="label">Jenis</dt>
            <dd class="text-ink">{{ $definisi['nama'] }}</dd>
        </div>
        <div>
            <dt class="label">Shipment</dt>
            <dd class="font-mono text-ink">{{ $batch->shipment?->kode ?: '—' }}</dd>
        </div>
        <div>
            <dt class="label">Status</dt>
            <dd>
                <span @class([
                    'lencana',
                    'bg-masuk-soft text-masuk' => $batch->status === 'selesai',
                    'bg-tanda-soft text-tanda' => in_array($batch->status, ['pratinjau', 'dibaca', 'diproses']),
                    'bg-keluar-soft text-keluar' => in_array($batch->status, ['gagal', 'dibatalkan']),
                ])>{{ $batch->status }}</span>
            </dd>
        </div>
    </dl>

    @if ($batch->pesan)
        <p class="mt-4 rounded-md border border-keluar bg-keluar-soft px-3 py-2 text-sm text-keluar">
            {{ $batch->pesan }}
        </p>
    @endif
</div>

{{-- Ringkasan angka --}}
<div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <div class="kartu p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-mute">Total Baris</p>
        <p class="angka mt-1 text-2xl font-bold text-ink">{{ $batch->jumlah_baris }}</p>
    </div>
    <div class="kartu p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-mute">Siap Diproses</p>
        <p class="angka mt-1 text-2xl font-bold text-masuk">{{ $batch->jumlah_valid }}</p>
    </div>
    <div class="kartu p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-mute">Bermasalah</p>
        <p class="angka mt-1 text-2xl font-bold {{ $batch->jumlah_bermasalah > 0 ? 'text-keluar' : 'text-ink-mute' }}">
            {{ $batch->jumlah_bermasalah }}
        </p>
    </div>
    <div class="kartu p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-mute">
            {{ $batch->status === 'selesai' ? 'Berhasil Masuk' : 'Belum Diproses' }}
        </p>
        <p class="angka mt-1 text-2xl font-bold {{ $batch->status === 'selesai' ? 'text-masuk' : 'text-ink-mute' }}">
            {{ $batch->status === 'selesai' ? $batch->jumlah_baru : '—' }}
        </p>
    </div>
</div>

{{-- Tindakan --}}
@if ($batch->status === 'pratinjau')
    <div class="kartu mt-4 p-4">
        @if ($batch->jumlah_bermasalah > 0)
            <p class="mb-3 rounded-md border border-tanda bg-tanda-soft px-3 py-2 text-sm text-tanda">
                Ada <strong>{{ $batch->jumlah_bermasalah }}</strong> baris bermasalah. Baris itu akan
                dilewati, dan <strong>{{ $batch->jumlah_valid }}</strong> baris sisanya tetap diproses.
                Kalau mau membetulkannya dulu, batalkan unggahan ini lalu unggah ulang berkas yang sudah diperbaiki.
            </p>
        @endif

        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('impor.proses', $batch) }}"
                  onsubmit="return confirm('Proses {{ $batch->jumlah_valid }} baris ke database?')">
                @csrf
                <button type="submit" class="tombol tombol-utama" @disabled($batch->jumlah_valid === 0)>
                    Proses {{ $batch->jumlah_valid }} Baris
                </button>
            </form>

            <form method="POST" action="{{ route('impor.batal', $batch) }}"
                  onsubmit="return confirm('Batalkan unggahan ini?')">
                @csrf
                <button type="submit" class="tombol tombol-bahaya">Batalkan</button>
            </form>

            <a href="{{ route('impor.index') }}" class="tombol tombol-biasa">Kembali</a>
        </div>
    </div>
@endif

{{-- Penyaring --}}
<div class="mt-5 flex flex-wrap gap-2">
    @foreach ([
        '' => 'Semua baris',
        'bermasalah' => 'Bermasalah saja',
        'valid' => 'Yang siap saja',
    ] as $nilai => $label)
        <a href="{{ route('impor.show', ['impor' => $batch, 'saring' => $nilai ?: null]) }}"
           @class([
               'tombol',
               'tombol-utama' => $saring === $nilai,
               'tombol-biasa' => $saring !== $nilai,
           ])>{{ $label }}</a>
    @endforeach
</div>

{{-- Isi baris --}}
<div class="kartu mt-3 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="tabel">
            <thead>
                <tr>
                    <th class="w-16">Baris</th>
                    <th class="w-24">Status</th>
                    @foreach ($definisi['kolom'] as $aturan)
                        <th>{{ $aturan['judul'] }}</th>
                    @endforeach
                    <th>Masalah</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($baris as $b)
                    <tr class="{{ $b->status === 'bermasalah' ? 'bg-keluar-soft/40' : '' }}">
                        {{-- Nomor baris di berkas asli, supaya bisa langsung dicari di Excel --}}
                        <td class="angka font-mono text-xs">{{ $b->nomor_baris }}</td>

                        <td>
                            <span @class([
                                'lencana',
                                'bg-masuk-soft text-masuk' => in_array($b->status, ['valid', 'diproses']),
                                'bg-keluar-soft text-keluar' => $b->status === 'bermasalah',
                                'bg-ground text-ink-mute' => in_array($b->status, ['dilewati', 'gagal']),
                            ])>{{ $b->status }}</span>
                        </td>

                        @foreach ($definisi['kolom'] as $kunci => $aturan)
                            <td class="whitespace-nowrap text-xs">
                                {{ $b->data_mentah[$kunci] ?? '—' }}
                            </td>
                        @endforeach

                        <td class="text-xs text-keluar">
                            @if ($b->masalah)
                                <ul class="list-disc pl-4">
                                    @foreach ($b->masalah as $m)
                                        <li>{{ $m }}</li>
                                    @endforeach
                                </ul>
                            @elseif ($b->catatan)
                                <span class="text-ink-mute">{{ $b->catatan }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($definisi['kolom']) + 3 }}" class="py-8 text-center text-ink-mute">
                            Tidak ada baris yang cocok dengan penyaring ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $baris->links() }}</div>

@endsection
