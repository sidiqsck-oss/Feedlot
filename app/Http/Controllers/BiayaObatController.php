<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\Treatment;
use App\Models\TreatmentItem;
use App\Services\BiayaObatService;
use App\Services\Ekspor\EksporCsv;
use App\Services\Ekspor\EksporExcel;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use RuntimeException;

/**
 * Biaya obat per ekor.
 *
 * Jembatan antara OVK dan CPL: dosis dari rekam medis dokter dinilai dengan
 * harga FIFO barang yang benar-benar keluar dari gudang.
 */
class BiayaObatController extends Controller
{
    public function __construct(
        private readonly BiayaObatService $biaya,
        private readonly EksporCsv $csv,
        private readonly EksporExcel $excel,
    ) {}

    public function index(Request $request): View
    {
        $saring = $this->saring($request);
        $baris = $this->biaya->perEkor($this->treatment($saring));

        return view('laporan.biaya-obat', [
            'baris' => $baris,
            'saring' => $saring,
            'shipment' => Shipment::orderByDesc('nomor')->get(),
            'ringkasan' => $this->ringkasan($baris),
            'belumDipetakan' => $this->belumDipetakan(),
        ]);
    }

    public function unduh(Request $request)
    {
        $saring = $this->saring($request);
        $baris = $this->biaya->perEkor($this->treatment($saring));

        if ($baris->isEmpty()) {
            return back()->with('gagal', 'Tidak ada rekam medis yang cocok dengan penyaring ini.');
        }

        $judul = [
            'Shipment', 'Ear Tag', 'Perawatan', 'Tanggal Awal', 'Tanggal Akhir',
            'Diagnosa', 'Jumlah Obat', 'Biaya Obat', 'Catatan',
        ];

        $isi = $baris->map(fn (array $b) => [
            $b['shipment'],
            $b['ear_tag'],
            $b['jumlah_treatment'],
            $b['tanggal_awal']?->format('d/m/Y'),
            $b['tanggal_akhir']?->format('d/m/Y'),
            $b['diagnosa'],
            $b['jumlah_item'],
            $b['biaya'] === null ? '' : round($b['biaya'], 2),
            implode(' ', $b['masalah']),
        ]);

        $berkas = 'Biaya-Obat-'.now()->format('Ymd');

        if ($request->string('format')->value() === 'excel') {
            try {
                return $this->excel->unduh($berkas, 'Biaya Obat per Ekor', $judul, $isi, $this->teksPeriode($saring));
            } catch (RuntimeException $e) {
                return back()->with('gagal', $e->getMessage());
            }
        }

        return $this->csv->unduh($berkas, $judul, $isi, fn ($b) => $b);
    }

    // ── Pembantu ────────────────────────────────────────────────────

    private function saring(Request $request): array
    {
        return [
            'dari' => $request->date('dari')?->toDateString(),
            'sampai' => $request->date('sampai')?->toDateString(),
            'shipment' => $request->string('shipment')->value() ?: null,
            'ear_tag' => $request->string('ear_tag')->value() ?: null,
        ];
    }

    /** @return Collection<int, Treatment> */
    private function treatment(array $saring): Collection
    {
        return Treatment::query()
            ->with(['items.barang', 'shipment'])
            ->when($saring['dari'], fn ($q, $v) => $q->whereDate('tanggal', '>=', $v))
            ->when($saring['sampai'], fn ($q, $v) => $q->whereDate('tanggal', '<=', $v))
            ->when($saring['ear_tag'], fn ($q, $v) => $q->where('ear_tag', 'like', "%{$v}%"))
            ->when($saring['shipment'], function ($q, $v) {
                // shipment_teks ikut dicocokkan: sheet dokter menulis
                // shipment-nya bebas dan tidak selalu ketemu masternya.
                $q->where(function ($w) use ($v) {
                    $w->whereHas('shipment', fn ($s) => $s->where('kode', $v))
                        ->orWhere('shipment_teks', $v);
                });
            })
            ->get();
    }

    private function ringkasan(Collection $baris): array
    {
        $bernilai = $baris->filter(fn ($b) => $b['biaya'] !== null);
        $total = $bernilai->sum(fn ($b) => $b['biaya']);

        return [
            'ekor' => $baris->count(),
            'perawatan' => $baris->sum(fn ($b) => $b['jumlah_treatment']),
            'total' => $total,
            // Pembilang dan penyebutnya dari kumpulan yang sama: hanya ekor
            // yang biayanya memang bisa dihitung.
            'rata' => $bernilai->isEmpty() ? null : $total / $bernilai->count(),
            'n_rata' => $bernilai->count(),
            'bermasalah' => $baris->filter(fn ($b) => $b['masalah'] !== [])->count(),
        ];
    }

    /**
     * Nama obat yang ditulis dokter tapi belum ada aliasnya di master.
     * Ditampilkan supaya biaya yang belum lengkap bisa segera dibetulkan,
     * bukan dibiarkan hilang diam-diam.
     */
    private function belumDipetakan(): Collection
    {
        return TreatmentItem::belumDipetakan()
            ->selectRaw('nama_obat_asli, COUNT(*) as jumlah')
            ->groupBy('nama_obat_asli')
            ->orderByDesc('jumlah')
            ->limit(20)
            ->get();
    }

    private function teksPeriode(array $saring): ?string
    {
        if (! $saring['dari'] && ! $saring['sampai']) {
            return null;
        }

        return 'Periode '.($saring['dari'] ?: '…').' s/d '.($saring['sampai'] ?: '…');
    }
}
