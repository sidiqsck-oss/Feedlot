@extends('layouts.app')
@section('judul', 'Closing CPL')

@php use App\Support\FormatCpl as F; @endphp

@section('aksi')
    <a href="{{ route('cpl.laporan', request()->query()) }}" class="tombol tombol-biasa">Lihat Detail</a>
@endsection

@section('isi')

<p class="mb-4 max-w-3xl text-sm text-ink-soft">
    Ringkasan tanpa baris per ekor, untuk penutupan. Untuk memeriksa sapi
    satu-satu, buka Laporan CPL Detail.
</p>

<form method="GET" class="kartu mb-4 flex flex-wrap items-end gap-3 p-3">
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

    @foreach (['shipment' => 'Shipment', 'jenis' => 'Jenis', 'customer' => 'Customer', 'invoice' => 'Invoice'] as $kunci => $label)
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
    <a href="{{ route('cpl.closing') }}" class="tombol tombol-biasa">Reset</a>
</form>

@if ($baris->isEmpty())
    <div class="kartu p-10 text-center text-ink-mute">Tidak ada data dengan penyaring ini.</div>
@else

<div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <x-cpl-kartu judul="Ekor" :nilai="number_format($baris->count(), 0, ',', '.')" />
    <x-cpl-kartu judul="ADG Induction" tebal
        :nilai="F::adg($ringkasan['adg_induction']['nilai'])"
        :catatan="'n=' . $ringkasan['adg_induction']['n']" />
    <x-cpl-kartu judul="Total Exit Wt" :nilai="F::kg($ringkasan['total_berat_jual']['nilai'])" />
    <x-cpl-kartu judul="Gain per Ekor" :nilai="F::kg($ringkasan['gain_kg']['nilai'])" />
</div>

@foreach ([
    'Per Customer' => $perCustomer,
    'Per Shipment' => $perShipment,
    'Per Jenis' => $perJenis,
] as $judul => $data)
    <div class="kartu mb-4 overflow-hidden">
        <div class="border-b border-rule px-4 py-3">
            <h2 class="text-sm font-bold text-ink">{{ $judul }}</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="tabel">
                <thead>
                    <tr>
                        <th>{{ str_replace('Per ', '', $judul) }}</th>
                        <th class="text-right">Ekor</th>
                        <th class="text-right">Induct Wt</th>
                        <th class="text-right">Exit Wt</th>
                        <th class="text-right">Gain/ekor</th>
                        <th class="text-right">DOF</th>
                        <th class="text-right">ADG Induct</th>
                        <th class="text-right">ADG RWT</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data as $nama => $d)
                        <tr>
                            <td class="font-medium text-ink">{{ $nama }}</td>
                            <td class="angka">{{ number_format($d['ekor'], 0, ',', '.') }}</td>
                            <td class="angka">{{ F::kg($d['berat_induksi']['nilai']) }}</td>
                            <td class="angka">{{ F::kg($d['berat_jual']['nilai']) }}</td>
                            <td class="angka">{{ F::kg($d['gain_kg']['nilai']) }}</td>
                            <td class="angka">{{ F::hari($d['dof_induction']['nilai']) }}</td>
                            <td class="angka font-semibold text-ink">{{ F::adg($d['adg_induction']['nilai']) }}</td>
                            <td class="angka">
                                {{ F::adg($d['adg_rwt']['nilai']) }}
                                @if ($d['adg_rwt']['n'] < $d['ekor'])
                                    <span class="block text-[0.65rem] font-normal text-ink-mute">
                                        n={{ $d['adg_rwt']['n'] }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endforeach

<p class="text-xs text-ink-mute">
    ADG Induction dan ADG RWT dihitung tertimbang dari total, sedangkan yang lain
    rata-rata biasa — sama seperti laporan yang dipakai sekarang. Angka <code>n</code>
    muncul kalau tidak semua ekor punya data reweight.
</p>

@endif
@endsection
