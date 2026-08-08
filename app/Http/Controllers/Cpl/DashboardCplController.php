<?php

namespace App\Http\Controllers\Cpl;

use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Models\PembelianShipment;
use App\Models\Shipment;
use App\Services\Cpl\AgregatCpl;
use App\Services\Cpl\KueriCpl;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Dashboard CPL untuk pimpinan.
 *
 * Susunan dan perilakunya mengikuti berkas HTML yang dipakai sekarang;
 * cara menghitungnya mengikuti Streamlit lama lewat AgregatCpl.
 *
 * Bedanya dengan dashboard lama: yang itu penampil laporan satu tanggal jual,
 * yang ini pembanding. Bos meminta titik-titik yang bisa dibandingkan, dan
 * satu angka tanpa pembanding tidak memberi tahu apa pun.
 */
class DashboardCplController extends Controller
{
    /** Populasi aktif dibatasi supaya shipment lama yang tinggal sisa tidak memenuhi tabel. */
    private const BATAS_SHIPMENT_AKTIF = 8;

    public function __construct(private readonly KueriCpl $kueri) {}

    public function __invoke(Request $request): View
    {
        $saring = $this->saringDari($request);

        $baris = collect($this->kueri->saring($this->kueri->terjual(), $saring)->get());
        $agregat = AgregatCpl::dari($baris);

        return view('cpl.dashboard', [
            'saring' => $saring,
            'pilihan' => $this->pilihanPenyaring($saring),
            'ekor' => $baris->count(),

            'ringkasan' => $agregat->semua(),
            'pembanding' => $this->bandingkan($agregat, $baris),
            'corong' => $this->corong($saring),
            'claim' => $this->ringkasanClaim($saring),
            'aktif' => $this->populasiAktif(),
            'tren' => $this->trenBulanan($saring),

            // Bahan grafik: nilai per ekor, digambar di sisi browser.
            'adgInduction' => $baris->pluck('adg_induction')->filter()->map(fn ($v) => (float) $v)->values(),
            'perbandinganAdg' => $this->bahanGrafikAdg($baris),

            'sebelumnya' => $this->periodeSebelumnya($saring),
        ]);
    }

    /**
     * Penyaring saling terhubung: pilihan tiap penyaring dipersempit oleh
     * penyaring lain yang sedang aktif, sehingga tidak pernah ada pilihan
     * yang hasilnya kosong. Meniru subFilters() di HTML.
     */
    private function pilihanPenyaring(array $saring): array
    {
        $dasar = $this->kueri->saring(
            $this->kueri->terjual(),
            // Rentang tanggal tetap dipakai; sisanya dilepas supaya tiap
            // penyaring tetap menampilkan alternatif yang masih mungkin.
            array_intersect_key($saring, array_flip(['dari', 'sampai'])),
        );

        $baris = collect($dasar->get());

        return [
            'shipment' => $baris->pluck('shipment')->filter()->unique()->sortDesc()->values(),
            'jenis' => $baris->pluck('jenis')->filter()->unique()->sort()->values(),
            'property' => $baris->pluck('kode_prop')->filter()->unique()->sort()->values(),
            'customer' => $baris->pluck('customer')->filter()->unique()->sort()->values(),
            'invoice' => $baris->pluck('no_invoice')->filter()->unique()->sortDesc()->take(50)->values(),
        ];
    }

    /** Empat tabel pembanding — inti dashboard ini. */
    private function bandingkan(AgregatCpl $agregat, Collection $baris): array
    {
        return [
            'shipment' => $agregat->kelompok('shipment'),
            'property' => $agregat->kelompok('property'),
            'jenis' => $agregat->kelompok('jenis'),
            'customer' => $agregat->kelompok('customer'),
        ];
    }

    /**
     * Corong shipment: dari yang tiba, berapa yang jadi uang.
     *
     * Selisih antara tiba dan induksi adalah sapi yang mati sebelum sempat
     * diinduksi — di sistem lama angka itu tidak terlihat di mana pun.
     */
    private function corong(array $saring): array
    {
        $shipmentIds = Shipment::query()
            ->when($saring['shipment'] ?? null, fn ($q, $v) => $q->where('kode', $v))
            ->pluck('id');

        $tiba = (int) PembelianShipment::whereIn('shipment_id', $shipmentIds)->sum('jumlah_ekor');

        $induksi = (int) \App\Models\Induksi::whereIn('shipment_id', $shipmentIds)->count();

        $terjual = (int) $this->kueri->terjual()
            ->whereIn('induksi.shipment_id', $shipmentIds)->count();

        $claim = (int) Claim::whereIn('shipment_id', $shipmentIds)->count();

        return [
            'tiba' => $tiba,
            'induksi' => $induksi,
            'aktif' => max(0, $induksi - $terjual - $claim),
            'terjual' => $terjual,
            'claim' => $claim,
            'susut_sebelum_induksi' => max(0, $tiba - $induksi),
        ];
    }

    private function ringkasanClaim(array $saring): array
    {
        $q = Claim::query()
            ->when($saring['dari'] ?? null, fn ($q, $v) => $q->whereDate('tanggal_kejadian', '>=', $v))
            ->when($saring['sampai'] ?? null, fn ($q, $v) => $q->whereDate('tanggal_kejadian', '<=', $v))
            ->when($saring['shipment'] ?? null, function ($q, $v) {
                $q->whereHas('shipment', fn ($s) => $s->where('kode', $v));
            });

        $semua = $q->with('shipment')->get();

        return [
            'total' => $semua->count(),
            'mati_sebelum' => $semua->where('jenis_claim', 'mati')->where('fase', 'sebelum_induksi')->count(),
            'mati_sesudah' => $semua->where('jenis_claim', 'mati')->where('fase', 'sesudah_induksi')->count(),
            'salvage' => $semua->where('jenis_claim', 'salvage')->count(),
            'sakit_bawaan' => $semua->where('jenis_claim', 'sakit_bawaan')->count(),

            'diagnosa' => $semua->whereNotNull('diagnosa')
                ->groupBy('diagnosa')
                ->map->count()
                ->sortDesc()
                ->take(6),

            'umur_rata' => $this->umurRataClaim($semua),

            'per_shipment' => $semua->groupBy(fn ($c) => $c->shipment->kode)->map->count()->sortDesc(),
        ];
    }

    /** Umur saat claim dihitung dari tanggal tiba, bukan tanggal induksi. */
    private function umurRataClaim(Collection $claim): ?float
    {
        $umur = $claim->map(fn ($c) => $c->umurHari())->filter(fn ($u) => $u !== null);

        return $umur->isEmpty() ? null : $umur->avg();
    }

    /** Sapi yang masih di kandang — titik buta dashboard lama. */
    private function populasiAktif(): Collection
    {
        $shipmentTerbaru = Shipment::orderByDesc('nomor')
            ->limit(self::BATAS_SHIPMENT_AKTIF)
            ->pluck('id');

        $baris = collect(
            $this->kueri->aktif()->whereIn('induksi.shipment_id', $shipmentTerbaru)->get()
        );

        return $baris->groupBy('shipment')->map(function ($grup) {
            $berat = $grup->pluck('berat_induksi')->filter()->map(fn ($v) => (float) $v);

            // Hari berjalan dihitung dari penimbangan terakhir yang ada:
            // reweight kalau sudah, kalau belum ya sejak induksi.
            $hari = $grup->map(function ($b) {
                $acuan = $b->tanggal_reweight ?: $b->tanggal_induksi;

                return $acuan ? Carbon::parse($acuan)->diffInDays(now()) : null;
            })->filter();

            return [
                'ekor' => $grup->count(),
                'dof_berjalan' => $hari->isEmpty() ? null : $hari->avg(),
                'berat_induksi' => $berat->isEmpty() ? null : $berat->avg(),
            ];
        })->sortKeysDesc();
    }

    private function trenBulanan(array $saring): Collection
    {
        $baris = collect(
            $this->kueri->saring($this->kueri->terjual(), array_diff_key($saring, array_flip(['dari', 'sampai'])))
                ->get()
        );

        return $baris
            ->filter(fn ($b) => $b->tanggal_jual)
            ->groupBy(fn ($b) => Carbon::parse($b->tanggal_jual)->format('Y-m'))
            ->map(fn ($grup) => [
                'ekor' => $grup->count(),
                'adg' => AgregatCpl::dari($grup)
                    ->adgTertimbang('berat_jual', 'berat_induksi', 'dof_induction')['nilai'],
            ])
            ->sortKeys()
            ->take(-12);
    }

    /** Batang per ear tag: ADG RWT dibanding ADG JUAL, seperti di HTML. */
    private function bahanGrafikAdg(Collection $baris): Collection
    {
        return $baris
            ->filter(fn ($b) => $b->adg_rwt !== null && $b->adg_jual !== null)
            ->sortByDesc(fn ($b) => (float) $b->adg_jual)
            ->take(60)
            ->map(fn ($b) => [
                'ear_tag' => $b->ear_tag,
                'adg_rwt' => round((float) $b->adg_rwt, 2),
                'adg_jual' => round((float) $b->adg_jual, 2),
                'melambat' => (float) $b->adg_jual < (float) $b->adg_rwt,
            ])
            ->values();
    }

    /**
     * Angka periode sebelumnya, dengan panjang rentang yang sama.
     * Tanpa pembanding, satu angka tidak memberi tahu apa pun.
     */
    private function periodeSebelumnya(array $saring): ?array
    {
        if (empty($saring['dari']) || empty($saring['sampai'])) {
            return null;
        }

        $dari = Carbon::parse($saring['dari']);
        $sampai = Carbon::parse($saring['sampai']);
        $panjang = max(1, $dari->diffInDays($sampai) + 1);

        $baris = collect($this->kueri->saring($this->kueri->terjual(), [
            ...$saring,
            'dari' => $dari->copy()->subDays($panjang)->toDateString(),
            'sampai' => $dari->copy()->subDay()->toDateString(),
        ])->get());

        return $baris->isEmpty() ? null : AgregatCpl::dari($baris)->semua();
    }

    private function saringDari(Request $request): array
    {
        return [
            'dari' => $request->date('dari')?->toDateString()
                ?? Carbon::now()->startOfMonth()->subMonths(2)->toDateString(),
            'sampai' => $request->date('sampai')?->toDateString()
                ?? Carbon::now()->endOfMonth()->toDateString(),
            'shipment' => $request->string('shipment')->value() ?: null,
            'jenis' => $request->input('jenis') ?: null,
            'property' => $request->string('property')->value() ?: null,
            'customer' => $request->string('customer')->value() ?: null,
            'invoice' => $request->string('invoice')->value() ?: null,
        ];
    }
}
