@props([
    'baris',
    'judul' => 'PT. SUMBER CIPTA KENCANA',
    'subjudul' => null,
    'sembunyikan' => [],
])

@php
    use App\Services\Cpl\AgregatCpl;
    use App\Support\KolomCpl;

    $kolom = KolomCpl::tampil($sembunyikan);
    $ringkas = AgregatCpl::dari($baris)->semua();

    /**
     * Baris RATA-RATA di atas judul kolom memakai rata-rata biasa untuk semua
     * kolom, sedangkan baris TOTAL di bawah memakai jumlah untuk bobot dan DOF
     * serta perhitungan tertimbang untuk ADG Induction dan ADG RWT.
     *
     * Campuran itu dibawa apa adanya dari laporan yang dipakai sekarang.
     */
    $nilaiRingkas = function (array $k) use ($ringkas, $baris) {
        if (! $k['ringkas'] || ! $k['agregat']) {
            return null;
        }

        // Kolom yang di baris Total dihitung tertimbang, di baris atas tetap
        // ditampilkan sebagai rata-rata biasa — sama seperti laporan lama.
        if ($k['ringkas'] === 'tertimbang') {
            $nilai = $baris->pluck($k['kunci'])->filter(fn ($v) => $v !== null);

            return $nilai->isEmpty() ? null : $nilai->map(fn ($v) => (float) $v)->avg();
        }

        return $ringkas[$k['agregat']]['nilai'] ?? null;
    };

    $nilaiTotal = function (array $k) use ($ringkas, $baris) {
        if (! $k['ringkas'] || ! $k['agregat']) {
            return null;
        }

        return match ($k['ringkas']) {
            // Dihitung ulang dari total, bukan dirata-rata dari nilai per ekor.
            'tertimbang' => $ringkas[$k['agregat']]['nilai'] ?? null,
            'avg' => $ringkas[$k['agregat']]['nilai'] ?? null,
            'sum' => $baris->pluck($k['kunci'])->filter(fn ($v) => $v !== null)->sum(),
            default => null,
        };
    };

    $format = function (?array $k, $nilai) {
        if ($nilai === null || $nilai === '') {
            return '';
        }

        return match ($k['format']) {
            'desimal' => number_format((float) $nilai, 2, ',', '.'),
            'bulat' => number_format(round((float) $nilai), 0, ',', '.'),
            'tanggal' => \Illuminate\Support\Carbon::parse($nilai)->format('d-M'),
            default => $nilai,
        };
    };
@endphp

<div class="kartu overflow-hidden">
    <div class="p-3">
        <div class="kop-cpl">
            <h3>{{ $judul }}</h3>
            @if ($subjudul)
                <p>{{ $subjudul }}</p>
            @endif
        </div>
    </div>

    <div class="max-h-[32rem] overflow-auto border-t border-rule">
        <table class="tabel-cpl">
            <thead>
                {{-- Baris rata-rata sengaja DI ATAS judul kolom, persis
                     seperti laporan yang dipakai sekarang. --}}
                <tr class="ringkas">
                    <td class="kiri">Rata-rata</td>
                    <td>{{ $baris->count() }}</td>
                    @foreach (array_slice($kolom, 2) as $k)
                        @php $v = $nilaiRingkas($k); @endphp
                        <td class="{{ $k['ringkas'] ? '' : 'kosong' }}">{{ $format($k, $v) }}</td>
                    @endforeach
                </tr>

                <tr>
                    @foreach ($kolom as $k)
                        <th class="{{ $k['warna'] === 'kuning' ? 'kuning' : '' }}">{{ $k['judul'] }}</th>
                    @endforeach
                </tr>
            </thead>

            <tbody>
                @foreach ($baris as $i => $b)
                    <tr>
                        @foreach ($kolom as $k)
                            @php
                                $nilai = $k['kunci'] === '_no'
                                    ? $i + 1
                                    : ($k['kunci'] === 'selisih'
                                        ? (($b->adg_jual !== null && $b->adg_rwt !== null)
                                            ? (float) $b->adg_jual - (float) $b->adg_rwt
                                            : null)
                                        : ($b->{$k['kunci']} ?? null));

                                $kelas = match ($k['warna']) {
                                    'tengah' => 'tengah polos',
                                    'kiri' => 'kiri polos',
                                    'polos' => 'tengah polos',
                                    default => $k['warna'],
                                };

                                if ($k['kunci'] === 'selisih' && $nilai !== null) {
                                    $kelas .= $nilai < 0 ? ' turun' : ($nilai > 0 ? ' naik' : '');
                                }
                            @endphp
                            <td class="{{ $kelas }}">{{ $format($k, $nilai) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>

            <tfoot>
                <tr class="total">
                    <td class="kiri">Total</td>
                    <td></td>
                    @foreach (array_slice($kolom, 2) as $k)
                        <td>{{ $format($k, $nilaiTotal($k)) }}</td>
                    @endforeach
                </tr>
            </tfoot>
        </table>
    </div>
</div>
