<?php

namespace App\Http\Controllers\Cpl;

use App\Http\Controllers\Controller;
use App\Services\Cpl\AgregatCpl;
use App\Services\Cpl\KueriCpl;
use App\Services\Ekspor\EksporCsv;
use App\Services\Ekspor\EksporExcel;
use App\Services\Ekspor\EksporPdf;
use App\Support\KolomCpl;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use RuntimeException;

/**
 * Laporan CPL — dua jalur, mengikuti Streamlit lama.
 *
 *   Detail  : satu tabel per customer dengan baris per ekor
 *   Closing : ringkasan tanpa baris detail, untuk penutupan
 *
 * Keduanya membaca lewat KueriCpl yang sama dengan dashboard, sehingga angka
 * di ketiga tempat itu tidak mungkin berbeda.
 */
class LaporanCplController extends Controller
{
    public function __construct(
        private readonly KueriCpl $kueri,
        private readonly EksporCsv $csv,
        private readonly EksporExcel $excel,
        private readonly EksporPdf $pdf,
    ) {}

    public function detail(Request $request): View
    {
        [$baris, $saring, $sembunyikan] = $this->susun($request);

        return view('cpl.laporan', [
            'baris' => $baris,
            'saring' => $saring,
            'pilihan' => $this->pilihanPenyaring($saring),
            'sembunyikan' => $sembunyikan,
            'kolomOpsional' => KolomCpl::opsional(),
            'perCustomer' => $this->perCustomer($baris),
        ]);
    }

    public function closing(Request $request): View
    {
        [$baris, $saring] = $this->susun($request);

        return view('cpl.closing', [
            'baris' => $baris,
            'saring' => $saring,
            'pilihan' => $this->pilihanPenyaring($saring),
            'ringkasan' => AgregatCpl::dari($baris)->semua(),
            'perCustomer' => $this->perCustomer($baris)
                ->map(fn ($grup) => AgregatCpl::dari($grup)->semua()),
            'perShipment' => AgregatCpl::dari($baris)->kelompok('shipment'),
            'perJenis' => AgregatCpl::dari($baris)->kelompok('jenis'),
        ]);
    }

    public function unduh(Request $request)
    {
        [$baris, $saring, $sembunyikan] = $this->susun($request);

        if ($baris->isEmpty()) {
            return back()->with('gagal', 'Tidak ada data yang cocok dengan penyaring ini.');
        }

        $kolom = KolomCpl::tampil($sembunyikan);
        $judulKolom = array_map(fn ($k) => str_replace("\n", ' ', $k['judul']), $kolom);
        $berkas = 'CPL-'.($saring['tanggal'] ?? $saring['dari'].'-sd-'.$saring['sampai']);

        $isi = $baris->values()->map(function ($b, $i) use ($kolom) {
            return array_map(fn ($k) => $this->nilaiKolom($b, $k, $i), $kolom);
        });

        if ($request->string('format')->value() === 'pdf') {
            return $this->pdf->tampilkan($berkas, 'cetak.cpl', [
                'perCustomer' => $this->perCustomer($baris),
                'kolom' => $kolom,
                'saring' => $saring,
            ], 'a3');
        }

        if ($request->string('format')->value() === 'excel') {
            try {
                return $this->excel->unduh(
                    $berkas, 'Cattle Performance Log', $judulKolom, $isi,
                    $this->teksPeriode($saring),
                );
            } catch (RuntimeException $e) {
                return back()->with('gagal', $e->getMessage());
            }
        }

        return $this->csv->unduh($berkas, $judulKolom, $isi, fn ($b) => $b);
    }

    // ── Pembantu ────────────────────────────────────────────────────

    /** @return array{0: Collection, 1: array, 2: array} */
    private function susun(Request $request): array
    {
        $saring = [
            'tanggal' => $request->date('tanggal')?->toDateString(),
            'dari' => $request->date('dari')?->toDateString(),
            'sampai' => $request->date('sampai')?->toDateString(),
            'shipment' => $request->string('shipment')->value() ?: null,
            'jenis' => $request->input('jenis') ?: null,
            'customer' => $request->string('customer')->value() ?: null,
            'invoice' => $request->string('invoice')->value() ?: null,
            'status' => $request->input('status') ?: null,
        ];

        $q = $this->kueri->saring($this->kueri->terjual(), $saring);

        /*
         * Perilaku bawaan dari Streamlit: kalau belum ada tanggal maupun
         * invoice yang dipilih, tampilkan sepuluh invoice terakhir — supaya
         * halaman tidak pernah kosong saat pertama dibuka, dan tidak pula
         * menarik ribuan baris sekaligus.
         */
        if (! $saring['tanggal'] && ! $saring['dari'] && ! $saring['invoice']) {
            $invoiceTerakhir = $this->kueri->terjual()
                ->whereNotNull('penjualan.no_invoice')
                ->orderByDesc('penjualan.tanggal')
                ->distinct()
                ->limit(10)
                ->pluck('penjualan.no_invoice');

            $q = $q->whereIn('penjualan.no_invoice', $invoiceTerakhir);
            $saring['bawaan_10_invoice'] = true;
        }

        $baris = collect($q->orderBy('shipments.kode')->orderBy('induksi.ear_tag')->get());

        return [$baris, $saring, $this->sembunyikanDari($request)];
    }

    /**
     * Pilihan kolom yang disembunyikan.
     *
     * Bawaannya semua tersembunyi, sama seperti Streamlit — laporan standarnya
     * memang ringkas. Pilihannya disimpan di sesi supaya tidak perlu diatur
     * ulang tiap membuka halaman.
     */
    private function sembunyikanDari(Request $request): array
    {
        if ($request->has('atur_kolom')) {
            $dipilih = array_values(array_intersect(
                (array) $request->input('sembunyikan', []),
                array_keys(KolomCpl::opsional()),
            ));

            session(['cpl.sembunyikan' => $dipilih]);

            return $dipilih;
        }

        return session('cpl.sembunyikan', KolomCpl::bawaanDisembunyikan());
    }

    private function perCustomer(Collection $baris): Collection
    {
        return $baris
            ->groupBy(fn ($b) => $b->customer ?: '(tanpa customer)')
            ->map(fn ($grup) => $grup->sortBy('ear_tag')->values())
            ->sortKeys();
    }

    private function pilihanPenyaring(array $saring): array
    {
        // Sama seperti dashboard: pilihan dipersempit oleh tanggal yang aktif,
        // tapi tidak oleh penyaring lain, supaya alternatifnya tetap terlihat.
        $q = $this->kueri->saring(
            $this->kueri->terjual(),
            array_intersect_key($saring, array_flip(['tanggal', 'dari', 'sampai'])),
        );

        $baris = collect($q->get());

        return [
            'tanggal' => collect($this->kueri->terjual()
                ->selectRaw('penjualan.tanggal')
                ->orderByDesc('penjualan.tanggal')
                ->pluck('tanggal'))->unique()->take(60)->values(),
            'shipment' => $baris->pluck('shipment')->filter()->unique()->sortDesc()->values(),
            'jenis' => $baris->pluck('jenis')->filter()->unique()->sort()->values(),
            'customer' => $baris->pluck('customer')->filter()->unique()->sort()->values(),
            'invoice' => $baris->pluck('no_invoice')->filter()->unique()->sortDesc()->values(),
            'status' => $baris->pluck('status_sapi')->filter()->unique()->sort()->values(),
        ];
    }

    private function nilaiKolom(object $b, array $k, int $i): mixed
    {
        $nilai = match ($k['kunci']) {
            '_no' => $i + 1,
            'selisih' => ($b->adg_jual !== null && $b->adg_rwt !== null)
                ? (float) $b->adg_jual - (float) $b->adg_rwt
                : null,
            default => $b->{$k['kunci']} ?? null,
        };

        if ($nilai === null) {
            return null;
        }

        return match ($k['format']) {
            'desimal' => round((float) $nilai, 2),
            'bulat' => (int) round((float) $nilai),
            'tanggal' => Carbon::parse($nilai)->format('d-m-Y'),
            default => $nilai,
        };
    }

    private function teksPeriode(array $saring): string
    {
        if ($saring['tanggal'] ?? null) {
            return Carbon::parse($saring['tanggal'])->translatedFormat('d F Y');
        }

        if (($saring['dari'] ?? null) && ($saring['sampai'] ?? null)) {
            return Carbon::parse($saring['dari'])->translatedFormat('d M Y')
                .' – '.Carbon::parse($saring['sampai'])->translatedFormat('d M Y');
        }

        return '10 invoice terakhir';
    }
}
