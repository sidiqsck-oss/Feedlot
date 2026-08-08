<?php

namespace App\Http\Controllers\Cpl;

use App\Http\Controllers\Controller;
use App\Models\Induksi;
use App\Models\Penjualan;
use App\Models\Shipment;
use App\Services\Ekspor\EksporCsv;
use App\Services\Ekspor\EksporExcel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * Penjualan sapi.
 *
 * Jalur utamanya tetap impor sheet Transaksi — satu berkas sekali jalan untuk
 * seluruh invoice. Form ini pelengkapnya: koreksi satu ekor, atau nota yang
 * belum sempat masuk ke berkas.
 *
 * Bentuknya mengikuti nota aslinya: satu kepala (invoice, surat jalan,
 * customer, harga) dengan banyak baris ekor di bawahnya. Yang tersimpan tetap
 * satu baris per ekor, karena begitulah tabelnya — kepala nota cuma diulang.
 */
class PenjualanController extends Controller
{
    public function __construct(
        private readonly EksporCsv $csv,
        private readonly EksporExcel $excel,
    ) {}

    public function index(Request $request): View
    {
        $saring = $this->saring($request);
        $daftar = $this->kueri($saring)->paginate(50)->withQueryString();

        return view('cpl.penjualan.index', [
            'daftar' => $daftar,
            'saring' => $saring,
            'shipment' => Shipment::orderByDesc('nomor')->get(),
            'pilihan' => $this->pilihanPenyaring(),
            'ringkasan' => $this->ringkasan($saring),
        ]);
    }

    public function create(): View
    {
        return view('cpl.penjualan.form', [
            'shipment' => Shipment::orderByDesc('nomor')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$kepala, $baris] = $this->validasiNota($request);

        DB::transaction(function () use ($kepala, $baris) {
            foreach ($baris as $b) {
                Penjualan::create($kepala + $b);
            }
        });

        return redirect()
            ->route('cpl.penjualan.index', array_filter(['invoice' => $kepala['no_invoice']]))
            ->with('sukses', count($baris).' ekor tercatat terjual.');
    }

    public function edit(Penjualan $penjualan): View
    {
        return view('cpl.penjualan.ubah', [
            'penjualan' => $penjualan->load('induksi.shipment'),
        ]);
    }

    /**
     * Satu ekor saja. Identitas sapinya tidak ikut diubah di sini — kalau
     * ekornya salah orang, barisnya dihapus lalu dicatat ulang, supaya tidak
     * ada penjualan yang diam-diam berpindah sapi.
     */
    public function update(Request $request, Penjualan $penjualan): RedirectResponse
    {
        $penjualan->update($this->validasiSatuBaris($request));

        return redirect()->route('cpl.penjualan.index')->with('sukses', 'Perubahan tersimpan.');
    }

    public function destroy(Penjualan $penjualan): RedirectResponse
    {
        $penjualan->delete();

        return redirect()->route('cpl.penjualan.index')->with('sukses', 'Baris penjualan dihapus.');
    }

    /** Cari sapi di satu shipment, dipakai form sambil mengetik RFID. */
    public function cari(Request $request)
    {
        $induksi = Induksi::with('shipment')
            ->where('shipment_id', $request->integer('shipment_id'))
            ->where('rfid', trim((string) $request->string('rfid')))
            ->first();

        if (! $induksi) {
            return response()->json(['ketemu' => false]);
        }

        $terjual = Penjualan::where('induksi_id', $induksi->id)
            ->orderByDesc('tanggal')
            ->first();

        return response()->json([
            'ketemu' => true,
            'ear_tag' => $induksi->ear_tag,
            'jenis' => $induksi->jenis,
            'berat_induksi' => $induksi->berat_induksi === null ? null : (float) $induksi->berat_induksi,
            // Bukan penghalang: baris terakhir yang dipakai, jadi ini memang
            // caranya mengoreksi. Tapi operator perlu tahu.
            'sudah_terjual' => $terjual?->tanggal?->toDateString(),
        ]);
    }

    public function unduh(Request $request)
    {
        $saring = $this->saring($request);
        $baris = $this->kueri($saring)->get();

        if ($baris->isEmpty()) {
            return back()->with('gagal', 'Tidak ada penjualan yang cocok dengan penyaring ini.');
        }

        $judul = [
            'Tanggal', 'No Invoice', 'No Surat Jalan', 'Cust', 'Kode Cust', 'Ship',
            'Nomor RFID', 'No Eartag', 'Nama Barang', 'Jumlah Berat', 'Satuan',
            'Harga', 'Realisasi', 'Total', 'Potongan', 'Status Sapi',
        ];

        $isi = $baris->map(fn (Penjualan $p) => [
            $p->tanggal->format('d/m/Y'),
            $p->no_invoice ?: '',
            $p->no_surat_jalan ?: '',
            $p->customer ?: '',
            $p->kode_customer ?: '',
            $p->induksi->shipment->kode,
            $p->induksi->rfid ?: '',
            $p->induksi->ear_tag ?: '',
            $p->nama_barang ?: '',
            $p->berat === null ? '' : (float) $p->berat,
            $p->satuan ?: '',
            $p->harga_per_kg === null ? '' : (float) $p->harga_per_kg,
            $p->realisasi === null ? '' : (float) $p->realisasi,
            $p->total === null ? '' : (float) $p->total,
            $p->potongan === null ? '' : (float) $p->potongan,
            $p->status_sapi ?: '',
        ]);

        $berkas = 'Penjualan-'.now()->format('Ymd');

        if ($request->string('format')->value() === 'excel') {
            try {
                return $this->excel->unduh($berkas, 'Penjualan Sapi', $judul, $isi, $this->teksPeriode($saring));
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
            'customer' => $request->string('customer')->value() ?: null,
            'invoice' => $request->string('invoice')->value() ?: null,
        ];
    }

    private function kueri(array $saring)
    {
        return Penjualan::query()
            ->with(['induksi.shipment'])
            ->when($saring['dari'], fn ($q, $v) => $q->whereDate('tanggal', '>=', $v))
            ->when($saring['sampai'], fn ($q, $v) => $q->whereDate('tanggal', '<=', $v))
            ->when($saring['customer'], fn ($q, $v) => $q->where('customer', $v))
            ->when($saring['invoice'], fn ($q, $v) => $q->where('no_invoice', $v))
            ->when($saring['shipment'], function ($q, $v) {
                $q->whereHas('induksi.shipment', fn ($s) => $s->where('kode', $v));
            })
            ->orderByDesc('tanggal')
            ->orderByDesc('id');
    }

    private function ringkasan(array $saring): array
    {
        $semua = $this->kueri($saring)->get();

        $berat = $semua->sum(fn ($p) => (float) $p->berat);
        $nilai = $semua->sum(fn ($p) => (float) $p->total);

        return [
            'ekor' => $semua->count(),
            'invoice' => $semua->pluck('no_invoice')->filter()->unique()->count(),
            'berat' => $berat,
            'nilai' => $nilai,
            // Harga rata-rata ditimbang berat, bukan rata-rata dari kolom
            // harga: satu invoice bisa memuat berat yang jauh berbeda.
            'harga_rata' => $berat > 0 ? $nilai / $berat : null,
        ];
    }

    private function pilihanPenyaring(): array
    {
        return [
            'customer' => Penjualan::query()
                ->whereNotNull('customer')->distinct()->orderBy('customer')->pluck('customer'),
            'invoice' => Penjualan::query()
                ->whereNotNull('no_invoice')->distinct()->orderByDesc('no_invoice')
                ->limit(100)->pluck('no_invoice'),
        ];
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    private function validasiNota(Request $request): array
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'no_invoice' => ['nullable', 'string', 'max:191'],
            'no_surat_jalan' => ['nullable', 'string', 'max:191'],
            'customer' => ['required', 'string', 'max:191'],
            'kode_customer' => ['nullable', 'string', 'max:191'],
            'nama_barang' => ['nullable', 'string', 'max:191'],
            'satuan' => ['nullable', 'string', 'max:20'],
            'harga_per_kg' => ['required', 'numeric', 'min:0'],
            'status_sapi' => ['nullable', 'string', 'max:30'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.shipment_id' => ['required', 'exists:shipments,id'],
            'items.*.rfid' => ['required', 'string', 'max:50'],
            'items.*.berat' => ['required', 'numeric', 'min:1', 'max:2000'],
            'items.*.potongan' => ['nullable', 'numeric', 'min:0'],
            'items.*.realisasi' => ['nullable', 'numeric', 'min:0'],
        ], [
            'items.required' => 'Isi minimal satu ekor.',
            'items.*.rfid.required' => 'RFID tiap baris wajib diisi.',
            'items.*.berat.required' => 'Berat tiap baris wajib diisi.',
        ]);

        $kepala = [
            'tanggal' => $data['tanggal'],
            'no_invoice' => $data['no_invoice'] ?? null,
            'no_surat_jalan' => $data['no_surat_jalan'] ?? null,
            'customer' => $data['customer'],
            'kode_customer' => $data['kode_customer'] ?? null,
            'nama_barang' => $data['nama_barang'] ?? null,
            'satuan' => $data['satuan'] ?? null,
            'harga_per_kg' => $data['harga_per_kg'],
            'status_sapi' => $data['status_sapi'] ?? null,
        ];

        $baris = [];
        $galat = [];
        $sudahDipakai = [];

        foreach ($data['items'] as $i => $item) {
            $rfid = trim($item['rfid']);

            $induksi = Induksi::where('shipment_id', $item['shipment_id'])
                ->where('rfid', $rfid)
                ->first();

            if (! $induksi) {
                $kode = Shipment::find($item['shipment_id'])?->kode;
                $galat["items.{$i}.rfid"] = "RFID {$rfid} tidak ada di data induksi {$kode}. ".
                    'Unggah berkas induksinya dulu, atau betulkan nomornya.';

                continue;
            }

            // Satu nota tidak boleh memuat ekor yang sama dua kali — itu selalu
            // salah ketik, bukan koreksi.
            if (isset($sudahDipakai[$induksi->id])) {
                $galat["items.{$i}.rfid"] = 'RFID kembar dengan baris '.($sudahDipakai[$induksi->id] + 1).'.';

                continue;
            }

            $sudahDipakai[$induksi->id] = $i;

            $berat = (float) $item['berat'];

            $baris[] = [
                'induksi_id' => $induksi->id,
                'berat' => $berat,
                // Total dihitung di sini, tidak diketik: di berkas aslinya
                // Total memang persis berat x harga.
                'total' => round($berat * (float) $data['harga_per_kg'], 2),
                'realisasi' => $item['realisasi'] ?? null,
                'potongan' => $item['potongan'] ?? null,
            ];
        }

        if ($galat) {
            throw ValidationException::withMessages($galat);
        }

        return [$kepala, $baris];
    }

    private function validasiSatuBaris(Request $request): array
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'no_invoice' => ['nullable', 'string', 'max:191'],
            'no_surat_jalan' => ['nullable', 'string', 'max:191'],
            'customer' => ['required', 'string', 'max:191'],
            'kode_customer' => ['nullable', 'string', 'max:191'],
            'nama_barang' => ['nullable', 'string', 'max:191'],
            'satuan' => ['nullable', 'string', 'max:20'],
            'berat' => ['required', 'numeric', 'min:1', 'max:2000'],
            'harga_per_kg' => ['required', 'numeric', 'min:0'],
            'realisasi' => ['nullable', 'numeric', 'min:0'],
            'potongan' => ['nullable', 'numeric', 'min:0'],
            'status_sapi' => ['nullable', 'string', 'max:30'],
        ]);

        $data += array_fill_keys([
            'no_invoice', 'no_surat_jalan', 'kode_customer', 'nama_barang',
            'satuan', 'realisasi', 'potongan', 'status_sapi',
        ], null);

        $data['total'] = round((float) $data['berat'] * (float) $data['harga_per_kg'], 2);

        return $data;
    }

    private function teksPeriode(array $saring): ?string
    {
        if (! $saring['dari'] && ! $saring['sampai']) {
            return null;
        }

        return 'Periode '.($saring['dari'] ?: '…').' s/d '.($saring['sampai'] ?: '…');
    }
}
