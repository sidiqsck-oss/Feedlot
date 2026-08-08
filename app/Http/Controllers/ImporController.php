<?php

namespace App\Http\Controllers;

use App\Jobs\ProsesImporBatch;
use App\Models\ImportBatch;
use App\Models\Shipment;
use App\Services\Impor\ImporService;
use App\Services\Impor\PembuatTemplat;
use App\Services\Impor\TemplatImpor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class ImporController extends Controller
{
    public function __construct(
        private readonly ImporService $impor,
        private readonly PembuatTemplat $templat,
    ) {}

    public function index(): View
    {
        return view('impor.index', [
            'daftar' => ImportBatch::with(['shipment', 'pengunggah'])
                ->latest('id')
                ->paginate(20),
            'shipment' => Shipment::aktif()->orderByDesc('nomor')->get(),
            'jenis' => collect(TemplatImpor::semuaJenis())
                ->mapWithKeys(fn ($j) => [$j => TemplatImpor::definisi($j)['nama']]),
        ]);
    }

    /** Unduh berkas templat kosong beserta lembar petunjuknya. */
    public function templat(string $jenis)
    {
        if (! in_array($jenis, TemplatImpor::semuaJenis(), true)) {
            abort(404);
        }

        return $this->templat->unduh($jenis);
    }

    /**
     * Unggah berkas. Belum memasukkan data apa pun — hasilnya cuma pratinjau
     * yang harus dikonfirmasi.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'jenis' => ['required', Rule::in(TemplatImpor::semuaJenis())],
            'shipment_id' => ['required', 'exists:shipments,id'],
            'berkas' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ], [
            'berkas.mimes' => 'Berkas harus berformat Excel (.xlsx / .xls) atau CSV.',
            'berkas.max' => 'Ukuran berkas maksimal 10 MB. Kalau lebih, pecah per shipment.',
            'shipment_id.required' => 'Pilih dulu shipment tujuan berkas ini.',
        ]);

        try {
            $batch = $this->impor->siapkan(
                $request->file('berkas'),
                $data['jenis'],
                Shipment::findOrFail($data['shipment_id']),
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('gagal', $e->getMessage());
        }

        return redirect()
            ->route('impor.show', $batch)
            ->with('sukses', 'Berkas terbaca. Periksa dulu hasilnya sebelum diproses — belum ada data yang masuk.');
    }

    public function show(Request $request, ImportBatch $impor): View
    {
        $saring = $request->string('saring')->value();

        return view('impor.show', [
            'batch' => $impor->load(['shipment', 'pengunggah']),
            'definisi' => TemplatImpor::definisi($impor->jenis),
            'baris' => $impor->baris()
                ->when($saring === 'bermasalah', fn ($q) => $q->bermasalah())
                ->when($saring === 'valid', fn ($q) => $q->valid())
                ->orderBy('nomor_baris')
                ->paginate(50)
                ->withQueryString(),
            'saring' => $saring,
        ]);
    }

    /**
     * Konfirmasi pratinjau.
     *
     * Batch kecil diproses langsung supaya hasilnya terlihat seketika; yang
     * besar dilempar ke antrean, karena lewat HTTP pasti menabrak batas waktu
     * eksekusi PHP di shared hosting.
     */
    public function proses(ImportBatch $impor): RedirectResponse
    {
        if (! $impor->siapDiproses()) {
            return back()->with('gagal', $impor->jumlah_valid === 0
                ? 'Tidak ada baris yang bisa diproses. Betulkan berkasnya lalu unggah ulang.'
                : "Unggahan ini berstatus \"{$impor->status}\" dan tidak bisa diproses.");
        }

        if ($impor->jumlah_valid > 100) {
            ProsesImporBatch::dispatch($impor->id);

            return back()->with(
                'sukses',
                "{$impor->jumlah_valid} baris sedang diproses di latar belakang. ".
                'Muat ulang halaman ini sebentar lagi untuk melihat hasilnya.'
            );
        }

        try {
            $this->impor->proses($impor);
        } catch (RuntimeException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        $hasil = $impor->fresh();

        return back()->with('sukses', sprintf(
            '%d baris masuk, %d dilewati.',
            $hasil->jumlah_baru,
            $hasil->jumlah_dilewati,
        ));
    }

    public function batal(ImportBatch $impor): RedirectResponse
    {
        try {
            $this->impor->batalkan($impor);
        } catch (RuntimeException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return redirect()->route('impor.index')->with('sukses', 'Unggahan dibatalkan.');
    }
}
