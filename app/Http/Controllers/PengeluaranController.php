<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\Pengeluaran;
use App\Models\PergerakanStok;
use App\Models\Petugas;
use App\Models\Shipment;
use App\Services\Ekspor\EksporPdf;
use App\Services\NomorDokumenService;
use App\Services\StokService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class PengeluaranController extends Controller
{
    public function __construct(
        private readonly StokService $stok,
        private readonly NomorDokumenService $nomor,
        private readonly EksporPdf $pdf,
    ) {}

    public function index(Request $request): View
    {
        $daftar = Pengeluaran::query()
            ->with(['petugas', 'shipment'])
            ->withSum('items as total_hpp', 'nilai_hpp')
            ->when($request->filled('dari'), fn ($q) => $q->whereDate('tanggal', '>=', $request->date('dari')))
            ->when($request->filled('sampai'), fn ($q) => $q->whereDate('tanggal', '<=', $request->date('sampai')))
            ->when($request->filled('tujuan'), fn ($q) => $q->where('tujuan', $request->string('tujuan')))
            ->latest('tanggal')->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('pengeluaran.index', ['daftar' => $daftar]);
    }

    public function create(): View
    {
        // Sisa stok ikut dikirim ke form supaya operator tahu cukup atau tidak
        // sebelum menyimpan — bukan baru ketahuan setelah ditolak.
        $saldo = PergerakanStok::query()
            ->selectRaw('barang_id, COALESCE(SUM(qty), 0) as stok')
            ->groupBy('barang_id')
            ->pluck('stok', 'barang_id');

        return view('pengeluaran.form', [
            'barang' => Barang::aktif()->orderBy('nama')->get(),
            'saldo' => $saldo,
            'petugas' => Petugas::aktif()->orderBy('nama')->get(),
            'shipment' => Shipment::aktif()->orderByDesc('nomor')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'tujuan' => ['required', Rule::in(['dokter', 'induksi', 'reweight', 'lainnya'])],
            'petugas_id' => ['nullable', 'exists:petugas,id'],

            // Shipment wajib untuk induksi dan reweight — itu satu-satunya cara
            // biaya obatnya bisa dibebankan ke rombongan sapi yang benar.
            'shipment_id' => [
                'nullable', 'exists:shipments,id',
                Rule::requiredIf(fn () => in_array($request->input('tujuan'), ['induksi', 'reweight'], true)),
            ],

            'catatan' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.barang_id' => ['required', 'exists:barang,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
        ], [
            'shipment_id.required' => 'Pengeluaran untuk induksi dan reweight harus menyebut shipment-nya.',
        ], [
            'items' => 'daftar barang',
            'items.*.qty' => 'jumlah',
        ]);

        $barangIds = array_column($data['items'], 'barang_id');
        if (count($barangIds) !== count(array_unique($barangIds))) {
            return back()->withInput()->with('gagal', 'Ada barang yang dimasukkan dua kali. Gabungkan jadi satu baris.');
        }

        try {
            $pengeluaran = DB::transaction(function () use ($data) {
                $pengeluaran = Pengeluaran::create([
                    'nomor' => $this->nomor->berikutnya(NomorDokumenService::KELUAR, Carbon::parse($data['tanggal'])),
                    'tanggal' => $data['tanggal'],
                    'tujuan' => $data['tujuan'],
                    'petugas_id' => $data['petugas_id'] ?? null,
                    'shipment_id' => $data['shipment_id'] ?? null,
                    'catatan' => $data['catatan'] ?? null,
                    'dibuat_oleh' => auth()->id(),
                ]);

                $this->stok->catatPengeluaran($pengeluaran, $data['items'], auth()->user());

                return $pengeluaran;
            });
        } catch (RuntimeException $e) {
            // Termasuk pesan "stok tidak cukup" dari StokService, yang sudah
            // menyebut nama barang dan berapa yang tersedia.
            return back()->withInput()->with('gagal', $e->getMessage());
        }

        return redirect()
            ->route('pengeluaran.show', $pengeluaran)
            ->with('sukses', "Barang keluar {$pengeluaran->nomor} tersimpan.");
    }

    public function show(Pengeluaran $pengeluaran): View
    {
        return view('pengeluaran.show', [
            'pengeluaran' => $pengeluaran->load([
                'petugas', 'shipment', 'pembuat',
                'items.barang', 'items.alokasi.lot',
            ]),
        ]);
    }

    public function cetak(Pengeluaran $pengeluaran)
    {
        return $this->pdf->tampilkan(
            $pengeluaran->nomor,
            'cetak.pengeluaran',
            ['pengeluaran' => $pengeluaran->load([
                'petugas', 'shipment', 'pembuat', 'items.barang', 'items.alokasi.lot',
            ])],
        );
    }
}
