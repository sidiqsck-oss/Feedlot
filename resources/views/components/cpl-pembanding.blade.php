@props(['judul', 'kolom', 'data', 'catatan' => null])

@php use App\Support\FormatCpl as F; @endphp

{{--
    Tabel pembanding dashboard.

    Diurutkan dari ADG Induction tertinggi, karena yang dicari bos memang
    mana yang paling bagus dan mana yang paling tertinggal.
--}}
<div class="kartu overflow-hidden">
    <div class="border-b border-rule px-4 py-3">
        <h2 class="text-sm font-bold text-ink">{{ $judul }}</h2>
        @if ($catatan)
            <p class="mt-0.5 text-xs text-ink-mute">{{ $catatan }}</p>
        @endif
    </div>

    <div class="overflow-x-auto">
        <table class="tabel">
            <thead>
                <tr>
                    <th>{{ $kolom }}</th>
                    <th class="text-right">Ekor</th>
                    <th class="text-right">ADG Induct</th>
                    <th class="text-right">Gain/ekor</th>
                    <th class="text-right">DOF</th>
                    <th class="text-right">Exit Wt</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $urut = $data->sortByDesc(fn ($d) => $d['adg_induction']['nilai'] ?? -999);
                @endphp

                @forelse ($urut as $nama => $d)
                    <tr>
                        <td class="font-medium text-ink">{{ $nama }}</td>
                        <td class="angka">{{ number_format($d['ekor'], 0, ',', '.') }}</td>
                        <td class="angka font-semibold text-ink">
                            {{ F::adg($d['adg_induction']['nilai']) }}
                            @if ($d['adg_induction']['n'] < $d['ekor'])
                                <span class="block text-[0.65rem] font-normal text-ink-mute">
                                    n={{ $d['adg_induction']['n'] }}
                                </span>
                            @endif
                        </td>
                        <td class="angka">{{ F::kg($d['gain_kg']['nilai']) }}</td>
                        <td class="angka">{{ F::hari($d['dof_induction']['nilai']) }}</td>
                        <td class="angka">{{ F::kg($d['berat_jual']['nilai']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-ink-mute">Belum ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
