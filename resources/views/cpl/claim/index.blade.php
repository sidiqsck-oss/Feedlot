@extends('layouts.app')
@section('judul', 'Claim ke Importir')

@php
use App\Http\Controllers\Cpl\ClaimController;
use App\Support\Format;
use App\Support\FormatCpl;
@endphp

@section('aksi')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('cpl.claim.create', request()->only('shipment')) }}" class="tombol tombol-utama">Catat Claim</a>
        <x-tombol-unduh rute="cpl.claim.unduh" />
    </div>
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
        <label for="jenis_claim" class="label">Jenis</label>
        <select id="jenis_claim" name="jenis_claim" class="input">
            <option value="">Semua</option>
            @foreach (ClaimController::JENIS as $nilai => $nama)
                <option value="{{ $nilai }}" @selected($saring['jenis_claim'] === $nilai)>{{ $nama }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="fase" class="label">Fase</label>
        <select id="fase" name="fase" class="input">
            <option value="">Semua</option>
            @foreach (ClaimController::FASE as $nilai => $nama)
                <option value="{{ $nilai }}" @selected($saring['fase'] === $nilai)>{{ $nama }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="status_klaim" class="label">Status</label>
        <select id="status_klaim" name="status_klaim" class="input">
            <option value="">Semua</option>
            @foreach (ClaimController::STATUS as $nilai => $nama)
                <option value="{{ $nilai }}" @selected($saring['status_klaim'] === $nilai)>{{ $nama }}</option>
            @endforeach
        </select>
    </div>

    <button type="submit" class="tombol tombol-biasa">Terapkan</button>
    <a href="{{ route('cpl.claim.index') }}" class="tombol tombol-biasa">Reset</a>
</form>

{{-- Rekap. Mati dipisah sebelum dan sesudah induksi karena artinya berbeda:
     yang sebelum induksi hampir selalu bawaan dari kapal, yang sesudah lebih
     mengarah ke penanganan di pen. --}}
<div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
    <div class="kartu p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-mute">Total Claim</p>
        <p class="angka mt-1 text-2xl font-bold text-ink">{{ $ringkasan['total'] }}</p>
        <p class="mt-1 text-xs text-ink-mute">ekor</p>
    </div>
    <div class="kartu p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-mute">Mati Sebelum Induksi</p>
        <p class="angka mt-1 text-2xl font-bold text-keluar">{{ $ringkasan['mati_sebelum'] }}</p>
    </div>
    <div class="kartu p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-mute">Mati Sesudah Induksi</p>
        <p class="angka mt-1 text-2xl font-bold text-keluar">{{ $ringkasan['mati_sesudah'] }}</p>
    </div>
    <div class="kartu p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-mute">Salvage</p>
        <p class="angka mt-1 text-2xl font-bold text-tanda">{{ $ringkasan['salvage'] }}</p>
        <p class="mt-1 text-xs text-ink-mute">sakit bawaan {{ $ringkasan['sakit_bawaan'] }}</p>
    </div>
    <div class="kartu p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-mute">Nilai Klaim</p>
        <p class="angka mt-1 text-2xl font-bold text-masuk">{{ Format::rupiah($ringkasan['nilai']) }}</p>
        <p class="mt-1 text-xs text-ink-mute">
            {{ $ringkasan['disetujui'] }} disetujui · {{ $ringkasan['ditolak'] }} ditolak
        </p>
    </div>
</div>

<div class="kartu overflow-hidden">
    <div class="overflow-x-auto">
        <table class="tabel">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Shipment</th>
                    <th>Identitas</th>
                    <th>Jenis</th>
                    <th>Fase</th>
                    <th class="text-right">Umur</th>
                    <th>Diagnosa</th>
                    <th class="text-right">Berat</th>
                    <th class="text-right">Nilai Klaim</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftar as $c)
                    @php
                        $tiba = $tibaFeedlot->get($c->shipment_id);
                        $umur = $tiba
                            ? (int) \Illuminate\Support\Carbon::parse($tiba)->diffInDays($c->tanggal_kejadian, absolute: false)
                            : null;
                    @endphp
                    <tr>
                        <td class="whitespace-nowrap">{{ $c->tanggal_kejadian->format('d/m/Y') }}</td>
                        <td class="font-mono text-xs">{{ $c->shipment->kode }}</td>
                        <td>
                            <span class="font-mono text-xs text-ink">{{ $c->rfid ?: '—' }}</span>
                            <span class="block font-mono text-xs text-ink-mute">
                                ear tag {{ $c->ear_tag ?: '—' }}
                                @if ($c->induksi_id)
                                    <span class="lencana ml-1 bg-masuk-soft text-masuk">terinduksi</span>
                                @endif
                            </span>
                        </td>
                        <td>
                            <span @class([
                                'lencana',
                                'bg-keluar-soft text-keluar' => $c->jenis_claim === 'mati',
                                'bg-tanda-soft text-tanda' => $c->jenis_claim === 'salvage',
                                'bg-ground text-ink-soft' => $c->jenis_claim === 'sakit_bawaan',
                            ])>{{ ClaimController::JENIS[$c->jenis_claim] }}</span>
                        </td>
                        <td class="text-xs">{{ ClaimController::FASE[$c->fase] }}</td>
                        <td class="angka">{{ FormatCpl::hari($umur) }}</td>
                        {{-- Keterangan menempel di sini, bukan jadi baris sendiri:
                             baris tambahan memutus selang-seling warna tabel. --}}
                        <td class="max-w-xs text-xs">
                            {{ $c->diagnosa ?: '—' }}
                            @if ($c->keterangan)
                                <span class="block text-ink-mute">{{ $c->keterangan }}</span>
                            @endif
                        </td>
                        <td class="angka">{{ FormatCpl::kg($c->berat) }}</td>
                        <td class="angka">{{ $c->nilai_klaim === null ? '—' : Format::rupiah($c->nilai_klaim) }}</td>
                        <td>
                            <span @class([
                                'lencana',
                                'bg-masuk-soft text-masuk' => $c->status_klaim === 'disetujui',
                                'bg-keluar-soft text-keluar' => $c->status_klaim === 'ditolak',
                                'bg-tanda-soft text-tanda' => $c->status_klaim === 'diajukan',
                            ])>{{ ClaimController::STATUS[$c->status_klaim] }}</span>
                        </td>
                        <td class="text-right">
                            <a href="{{ route('cpl.claim.edit', $c) }}"
                               class="text-xs font-semibold text-accent hover:underline">Ubah</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="py-8 text-center text-ink-mute">
                            Belum ada claim yang cocok dengan penyaring ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $daftar->links() }}</div>

@endsection
