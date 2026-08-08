@extends('layouts.app')
@section('judul', 'Dashboard CPL')

@php
    use App\Support\Format;
    use App\Support\FormatCpl as F;
@endphp

@section('isi')

{{-- Penyaring saling terhubung: pilihannya dipersempit oleh rentang tanggal --}}
<form method="GET" class="kartu mb-4 flex flex-wrap items-end gap-3 p-3">
    <div>
        <label for="dari" class="label">Dari</label>
        <input id="dari" name="dari" type="date" value="{{ $saring['dari'] }}" class="input">
    </div>
    <div>
        <label for="sampai" class="label">Sampai</label>
        <input id="sampai" name="sampai" type="date" value="{{ $saring['sampai'] }}" class="input">
    </div>

    @foreach ([
        'shipment' => 'Shipment',
        'property' => 'Property',
        'jenis' => 'Jenis',
        'customer' => 'Customer',
    ] as $kunci => $label)
        <div class="min-w-36">
            <label for="{{ $kunci }}" class="label">{{ $label }}</label>
            <select id="{{ $kunci }}" name="{{ $kunci }}" class="input">
                <option value="">Semua</option>
                @foreach ($pilihan[$kunci] as $nilai)
                    <option value="{{ $nilai }}" @selected($saring[$kunci] === $nilai)>{{ $nilai }}</option>
                @endforeach
            </select>
        </div>
    @endforeach

    <button type="submit" class="tombol tombol-biasa">Terapkan</button>
    <a href="{{ route('cpl.dashboard') }}" class="tombol tombol-biasa">Reset</a>

    <span class="ml-auto self-center text-xs text-ink-mute">
        {{ number_format($ekor, 0, ',', '.') }} ekor terjual di rentang ini
    </span>
</form>

@if ($ekor === 0)
    <div class="kartu p-10 text-center text-ink-mute">
        Tidak ada sapi terjual dengan penyaring ini.
    </div>
@else

{{-- ── Ringkasan atas: delapan kartu dari HTML, plus kartu susut ── --}}
<div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
    <x-cpl-kartu judul="Ekor Terjual"
        :nilai="number_format($ekor, 0, ',', '.')"
        :catatan="\Illuminate\Support\Carbon::parse($saring['dari'])->translatedFormat('d M') . ' – ' . \Illuminate\Support\Carbon::parse($saring['sampai'])->translatedFormat('d M Y')"
        :delta="F::delta($ekor, $sebelumnya ? $sebelumnya['ekor'] : null)" />

    <x-cpl-kartu judul="Rata-rata Exit Wt"
        :nilai="F::kg($ringkasan['berat_jual']['nilai'])"
        :catatan="'total ' . F::kg($ringkasan['total_berat_jual']['nilai'])"
        :delta="F::delta($ringkasan['berat_jual']['nilai'], $sebelumnya['berat_jual']['nilai'] ?? null)" />

    <x-cpl-kartu judul="Rata-rata Induct Wt"
        :nilai="F::kg($ringkasan['berat_induksi']['nilai'])"
        :catatan="'gain ' . F::kg($ringkasan['gain_kg']['nilai']) . '/ekor'"
        :delta="F::delta($ringkasan['berat_induksi']['nilai'], $sebelumnya['berat_induksi']['nilai'] ?? null)" />

    <x-cpl-kartu judul="ADG Induction" tebal
        :nilai="F::adg($ringkasan['adg_induction']['nilai'])"
        :catatan="F::hari($ringkasan['dof_induction']['nilai']) . ' · n=' . $ringkasan['adg_induction']['n']"
        :delta="F::delta($ringkasan['adg_induction']['nilai'], $sebelumnya['adg_induction']['nilai'] ?? null)" />

    <x-cpl-kartu judul="ADG Discharge"
        :nilai="F::adg($ringkasan['adg_discharge']['nilai'])"
        :catatan="F::hari($ringkasan['dof_discharge']['nilai']) . ' · n=' . $ringkasan['adg_discharge']['n']"
        :delta="F::delta($ringkasan['adg_discharge']['nilai'], $sebelumnya['adg_discharge']['nilai'] ?? null)" />

    <x-cpl-kartu judul="ADG RWT"
        :nilai="F::adg($ringkasan['adg_rwt']['nilai'])"
        :catatan="$ringkasan['adg_rwt']['n'] . ' dari ' . $ekor . ' ekor punya data RWT'"
        :delta="F::delta($ringkasan['adg_rwt']['nilai'], $sebelumnya['adg_rwt']['nilai'] ?? null)" />

    <x-cpl-kartu judul="ADG JUAL"
        :nilai="F::adg($ringkasan['adg_jual']['nilai'])"
        :catatan="'selisih ' . F::adg($ringkasan['selisih_rwt_jual']['nilai'])"
        :delta="F::delta($ringkasan['adg_jual']['nilai'], $sebelumnya['adg_jual']['nilai'] ?? null)" />

    <x-cpl-kartu judul="Melambat pasca RWT"
        :nilai="$ringkasan['melambat']['jumlah'] . ' / ' . $ringkasan['melambat']['n']"
        :catatan="$ringkasan['melambat']['persen'] === null ? '—' : round($ringkasan['melambat']['persen']) . '% ekor'" />

    {{-- Sengaja sederet dengan ADG, bukan di blok terpisah: ADG bagus
         artinya berbeda kalau banyak yang mati. --}}
    <x-cpl-kartu judul="Susut (Claim)" bahaya
        :nilai="$corong['tiba'] > 0 ? round($corong['claim'] / $corong['tiba'] * 100, 1) . '%' : ($claim['total'] . ' ekor')"
        :catatan="$claim['mati_sebelum'] + $claim['mati_sesudah'] . ' mati · ' . $claim['salvage'] . ' salvage'" />
</div>

{{-- ── Corong shipment ── --}}
<div class="kartu mb-4 p-4">
    <h2 class="text-sm font-bold text-ink">Corong Shipment</h2>
    <p class="mt-0.5 text-xs text-ink-mute">Dari yang tiba, berapa yang jadi uang.</p>

    <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
        @php
            $tahap = [
                ['Tiba', $corong['tiba'], 'bg-ground text-ink'],
                ['Induksi', $corong['induksi'], 'bg-accent-soft text-accent'],
                ['Aktif', $corong['aktif'], 'bg-tanda-soft text-tanda'],
                ['Terjual', $corong['terjual'], 'bg-masuk-soft text-masuk'],
                ['Claim', $corong['claim'], 'bg-keluar-soft text-keluar'],
            ];
        @endphp

        @foreach ($tahap as $i => [$nama, $jumlah, $warna])
            <div class="rounded-md px-3 py-2 {{ $warna }}">
                <span class="block text-[0.65rem] font-semibold uppercase tracking-wide opacity-70">{{ $nama }}</span>
                <span class="angka block text-lg font-bold">{{ number_format($jumlah, 0, ',', '.') }}</span>
                @if ($i > 0 && $corong['tiba'] > 0)
                    <span class="block text-[0.65rem] opacity-70">
                        {{ round($jumlah / $corong['tiba'] * 100, 1) }}%
                    </span>
                @endif
            </div>
            @if ($i < count($tahap) - 1)
                <span class="text-ink-mute">→</span>
            @endif
        @endforeach
    </div>

    @if ($corong['susut_sebelum_induksi'] > 0)
        <p class="mt-3 rounded-md border border-tanda bg-tanda-soft px-3 py-2 text-sm text-tanda">
            <strong>{{ $corong['susut_sebelum_induksi'] }} ekor</strong> hilang antara tiba dan induksi —
            mati sebelum sempat diinduksi. Di sistem lama angka ini tidak terlihat di mana pun.
        </p>
    @endif
</div>

{{-- ── Grafik ── --}}
<div class="mb-4 grid gap-4 lg:grid-cols-2">
    <div class="kartu p-4">
        <h2 class="text-sm font-bold text-ink">Distribusi ADG Induction</h2>
        <p class="mt-0.5 mb-3 text-xs text-ink-mute">Sebaran ADG (kg/hari) sapi yang terjual.</p>
        <div class="h-64"><canvas id="grafikSebaran"></canvas></div>
    </div>

    <div class="kartu p-4">
        <h2 class="text-sm font-bold text-ink">ADG RWT vs ADG JUAL</h2>
        <p class="mt-0.5 mb-3 text-xs text-ink-mute">
            Periode induksi→reweight dibanding reweight→jual. Batang merah berarti melambat.
        </p>
        <div class="h-64"><canvas id="grafikBanding"></canvas></div>
    </div>
</div>

<div class="kartu mb-4 p-4">
    <h2 class="text-sm font-bold text-ink">Tren Bulanan</h2>
    <p class="mt-0.5 mb-3 text-xs text-ink-mute">ADG Induction dan jumlah ekor terjual, 12 bulan terakhir.</p>
    <div class="h-56"><canvas id="grafikTren"></canvas></div>
</div>

{{-- ── Empat tabel pembanding ── --}}
<div class="grid gap-4 xl:grid-cols-2">
    <x-cpl-pembanding judul="Per Shipment" kolom="Shipment" :data="$pembanding['shipment']" />
    <x-cpl-pembanding judul="Per Property"
        kolom="Property" :data="$pembanding['property']"
        catatan="Menentukan mau beli dari mana lagi." />
    <x-cpl-pembanding judul="Per Jenis" kolom="Jenis" :data="$pembanding['jenis']" />
    <x-cpl-pembanding judul="Per Customer" kolom="Customer" :data="$pembanding['customer']" />
</div>

{{-- ── Claim ── --}}
<div class="mt-4 grid gap-4 lg:grid-cols-3">
    <div class="kartu p-4">
        <div class="flex items-baseline justify-between">
            <h2 class="text-sm font-bold text-ink">Claim</h2>
            <a href="{{ route('cpl.claim.index', array_filter(['shipment' => $saring['shipment'] ?? null])) }}"
               class="text-xs font-semibold text-accent hover:underline">Rincian &amp; catat</a>
        </div>
        <dl class="mt-3 space-y-2 text-sm">
            @foreach ([
                'Mati sebelum induksi' => $claim['mati_sebelum'],
                'Mati sesudah induksi' => $claim['mati_sesudah'],
                'Salvage' => $claim['salvage'],
                'Sakit bawaan' => $claim['sakit_bawaan'],
            ] as $label => $jumlah)
                <div class="flex justify-between border-b border-rule pb-1.5">
                    <dt class="text-ink-soft">{{ $label }}</dt>
                    <dd class="angka font-semibold {{ $jumlah > 0 ? 'text-keluar' : 'text-ink-mute' }}">{{ $jumlah }}</dd>
                </div>
            @endforeach
            <div class="flex justify-between pt-1">
                <dt class="text-ink-soft">Umur rata-rata saat claim</dt>
                <dd class="angka font-semibold text-ink">
                    {{ $claim['umur_rata'] === null ? '—' : round($claim['umur_rata']) . ' hari' }}
                </dd>
            </div>
        </dl>
        <p class="mt-2 text-xs text-ink-mute">Dihitung sejak tanggal tiba, bukan tanggal induksi.</p>
    </div>

    <div class="kartu p-4">
        <h2 class="text-sm font-bold text-ink">Diagnosa Terbanyak</h2>
        @if ($claim['diagnosa']->isEmpty())
            <p class="mt-3 text-sm text-ink-mute">Belum ada data.</p>
        @else
            <ul class="mt-3 space-y-1.5 text-sm">
                @foreach ($claim['diagnosa'] as $nama => $jumlah)
                    <li class="flex justify-between border-b border-rule pb-1.5">
                        <span class="text-ink-soft">{{ $nama }}</span>
                        <span class="angka font-semibold text-ink">{{ $jumlah }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="kartu p-4">
        <h2 class="text-sm font-bold text-ink">Claim per Shipment</h2>
        @if ($claim['per_shipment']->isEmpty())
            <p class="mt-3 text-sm text-ink-mute">Belum ada data.</p>
        @else
            <ul class="mt-3 space-y-1.5 text-sm">
                @foreach ($claim['per_shipment'] as $kode => $jumlah)
                    <li class="flex justify-between border-b border-rule pb-1.5">
                        <span class="font-mono text-ink-soft">{{ $kode }}</span>
                        <span class="angka font-semibold text-keluar">{{ $jumlah }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>

{{-- ── Populasi aktif ── --}}
<div class="kartu mt-4 overflow-hidden">
    <div class="border-b border-rule px-4 py-3">
        <h2 class="text-sm font-bold text-ink">Populasi Aktif</h2>
        <p class="mt-0.5 text-xs text-ink-mute">
            Sapi yang masih di kandang, 8 shipment terakhir. Belum terjual dan belum di-claim.
        </p>
    </div>

    <div class="overflow-x-auto">
        <table class="tabel">
            <thead>
                <tr>
                    <th>Shipment</th>
                    <th class="text-right">Ekor Aktif</th>
                    <th class="text-right">DOF Berjalan</th>
                    <th class="text-right">Bobot Induksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($aktif as $kode => $d)
                    <tr>
                        <td class="font-mono font-medium text-ink">{{ $kode }}</td>
                        <td class="angka font-semibold">{{ number_format($d['ekor'], 0, ',', '.') }}</td>
                        <td class="angka">{{ $d['dof_berjalan'] === null ? '—' : round($d['dof_berjalan']) . ' hari' }}</td>
                        <td class="angka">{{ F::kg($d['berat_induksi']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-ink-mute">Tidak ada sapi aktif.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ── Tabel rinci, satu per customer seperti di laporan ── --}}
<div class="mt-5 space-y-4">
    <div>
        <h2 class="text-sm font-bold text-ink">Rincian per Customer</h2>
        <p class="mt-0.5 text-xs text-ink-mute">
            Kolom opsional disembunyikan seperti bawaan laporan. Untuk mengaturnya
            dan mencetak, buka halaman Laporan CPL.
        </p>
    </div>

    @foreach ($rincian as $namaCustomer => $barisCustomer)
        <x-cpl-tabel
            :baris="$barisCustomer"
            :subjudul="'Cattle Performance Log · ' . $namaCustomer . ' · ' . $barisCustomer->count() . ' ekor'"
            :sembunyikan="\App\Support\KolomCpl::bawaanDisembunyikan()"
        />
    @endforeach

    @if ($rincian->count() > 1)
        <x-cpl-tabel
            :baris="$semuaBaris"
            :subjudul="'Cattle Performance Log · GABUNGAN SEMUA CUSTOMER · ' . $semuaBaris->count() . ' ekor'"
            :sembunyikan="\App\Support\KolomCpl::bawaanDisembunyikan()"
        />
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const Chart = window.Chart;
    if (!Chart) return;

    const warna = { merah: '#e05c5c', oranye: '#f0a500', hijauMuda: '#4fb477', hijau: '#2f8f5b' };
    const dasar = { responsive: true, maintainAspectRatio: false };

    // ── Sebaran ADG Induction: bin 0,25 dengan zona warna, sama seperti HTML
    const nilai = @json($adgInduction);

    if (nilai.length) {
        const langkah = 0.25;
        const bawah = Math.floor(Math.min(...nilai) / langkah) * langkah;
        const atas = Math.ceil(Math.max(...nilai) / langkah) * langkah;
        const label = [], jumlah = [], warnaBatang = [];

        for (let b = bawah; b < atas - 1e-9; b += langkah) {
            label.push(b.toFixed(2) + '–' + (b + langkah).toFixed(2));
            jumlah.push(nilai.filter(x => x >= b - 1e-9 && x < b + langkah - 1e-9).length);
            warnaBatang.push(
                b < 1.0 ? warna.merah : b < 1.5 ? warna.oranye : b < 2.0 ? warna.hijauMuda : warna.hijau
            );
        }

        new Chart(document.getElementById('grafikSebaran'), {
            type: 'bar',
            data: { labels: label, datasets: [{ data: jumlah, backgroundColor: warnaBatang, borderRadius: 4 }] },
            options: {
                ...dasar,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: c => c.parsed.y + ' ekor' } },
                },
                scales: {
                    x: { title: { display: true, text: 'ADG Induction (kg/hari)' }, grid: { display: false } },
                    y: { title: { display: true, text: 'Ekor' }, beginAtZero: true, ticks: { precision: 0 } },
                },
            },
        });
    }

    // ── ADG RWT vs ADG JUAL per ear tag
    const banding = @json($perbandinganAdg);

    if (banding.length) {
        new Chart(document.getElementById('grafikBanding'), {
            type: 'bar',
            data: {
                labels: banding.map(b => b.ear_tag),
                datasets: [
                    { label: 'ADG RWT', data: banding.map(b => b.adg_rwt), backgroundColor: warna.oranye, borderRadius: 3 },
                    {
                        label: 'ADG JUAL',
                        data: banding.map(b => b.adg_jual),
                        backgroundColor: banding.map(b => b.melambat ? warna.merah : warna.hijauMuda),
                        borderRadius: 3,
                    },
                ],
            },
            options: {
                ...dasar,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } },
                scales: {
                    x: { title: { display: true, text: 'Ear Tag' }, grid: { display: false }, ticks: { font: { size: 9 } } },
                    y: { title: { display: true, text: 'kg/hari' }, beginAtZero: true },
                },
            },
        });
    }

    // ── Tren bulanan: ekor sebagai batang, ADG sebagai garis
    const tren = @json($tren);
    const bulan = Object.keys(tren);

    if (bulan.length) {
        new Chart(document.getElementById('grafikTren'), {
            data: {
                labels: bulan,
                datasets: [
                    {
                        type: 'bar', label: 'Ekor terjual', yAxisID: 'y',
                        data: bulan.map(b => tren[b].ekor),
                        backgroundColor: '#dcede7', borderRadius: 4,
                    },
                    {
                        type: 'line', label: 'ADG Induction', yAxisID: 'y2',
                        data: bulan.map(b => tren[b].adg),
                        borderColor: '#0e6b5a', backgroundColor: '#0e6b5a',
                        tension: 0.3, spanGaps: true,
                    },
                ],
            },
            options: {
                ...dasar,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } },
                scales: {
                    x: { grid: { display: false } },
                    y: { position: 'left', beginAtZero: true, title: { display: true, text: 'Ekor' } },
                    y2: {
                        position: 'right', beginAtZero: true,
                        title: { display: true, text: 'ADG' },
                        grid: { drawOnChartArea: false },
                    },
                },
            },
        });
    }
});
</script>

@endif
@endsection
