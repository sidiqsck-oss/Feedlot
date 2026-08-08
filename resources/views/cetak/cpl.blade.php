@extends('cetak.layout')

@section('judul', 'Cattle Performance Log')
@section('nama-dokumen', 'CATTLE PERFORMANCE LOG')
@section('nomor-dokumen', $saring['tanggal']
    ? \Illuminate\Support\Carbon::parse($saring['tanggal'])->translatedFormat('d F Y')
    : '10 invoice terakhir')

@section('isi')

@php
    /**
     * Tabel CPL untuk cetak. Kolomnya banyak, jadi kertasnya A3 melebar dan
     * ukuran hurufnya diperkecil. Ini juga alasan pilihan sembunyikan kolom
     * berguna: laporan yang dicetak sebaiknya cuma memuat yang dibaca.
     */
    $angka = function ($nilai, $format) {
        if ($nilai === null || $nilai === '') return '';
        return match ($format) {
            'desimal' => number_format((float) $nilai, 2, ',', '.'),
            'bulat' => number_format(round((float) $nilai), 0, ',', '.'),
            'tanggal' => \Illuminate\Support\Carbon::parse($nilai)->format('d-M'),
            default => $nilai,
        };
    };
@endphp

@foreach ($perCustomer as $namaCustomer => $baris)
    <p style="font-size: 9pt; font-weight: bold; margin: 10px 0 4px 0;">
        {{ $namaCustomer }} — {{ $baris->count() }} ekor
    </p>

    <table class="data" style="font-size: 6.5pt;">
        <thead>
            <tr>
                @foreach ($kolom as $k)
                    <th style="font-size: 5.5pt; padding: 3px 2px;">{{ str_replace("\n", ' ', $k['judul']) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($baris as $i => $b)
                <tr>
                    @foreach ($kolom as $k)
                        @php
                            $nilai = match ($k['kunci']) {
                                '_no' => $i + 1,
                                'selisih' => ($b->adg_jual !== null && $b->adg_rwt !== null)
                                    ? (float) $b->adg_jual - (float) $b->adg_rwt : null,
                                default => $b->{$k['kunci']} ?? null,
                            };
                        @endphp
                        <td style="padding: 2px 3px;" class="{{ in_array($k['warna'], ['tengah', 'polos']) ? 'tengah' : ($k['warna'] === 'kiri' ? '' : 'kanan') }}">
                            {{ $angka($nilai, $k['format']) }}
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
@endforeach

@endsection

@section('catatan-kaki')
    ADG Induction dan ADG RWT dihitung tertimbang; sapi tanpa data reweight tidak ikut dalam perhitungan ADG RWT.
@endsection
