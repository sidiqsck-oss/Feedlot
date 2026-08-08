<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\Penerimaan;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\NomorDokumenService;
use App\Services\PurchaseOrderService;
use App\Services\StokService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class PenerimaanController extends Controller
{
    public function __construct(
        private readonly StokService $stok,
        private readonly NomorDokumenService $nomor,
        private readonly PurchaseOrderService $po,
    ) {}

    public function index(Request $request): View
    {
        $daftar = Penerimaan::query()
            ->with('supplier')
            ->withSum('items as total', 'subtotal')
            ->when($request->filled('dari'), fn ($q) => $q->whereDate('tanggal', '>=', $request->date('dari')))
            ->when($request->filled('sampai'), fn ($q) => $q->whereDate('tanggal', '<=', $request->date('sampai')))
            ->when($request->filled('supplier'), fn ($q) => $q->where('supplier_id', $request->integer('supplier')))
            ->latest('tanggal')->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('penerimaan.index', [
            'daftar' => $daftar,
            'supplier' => Supplier::orderBy('nama')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        // PO yang masih bisa dipenuhi. Dipakai untuk mengisi otomatis baris
        // barang beserta sisa yang belum datang.
        $poTerbuka = PurchaseOrder::whereIn('status', ['terbuka', 'sebagian'])
            ->with(['supplier', 'items.barang'])
            ->latest('tanggal')
            ->get();

        return view('penerimaan.form', [
            'supplier' => Supplier::aktif()->orderBy('nama')->get(),
            'barang' => Barang::aktif()->orderBy('nama')->get(),
            'poTerbuka' => $poTerbuka,
            'poDipilih' => $request->filled('po')
                ? $poTerbuka->firstWhere('id', $request->integer('po'))
                : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'no_faktur_supplier' => ['nullable', 'string', 'max:191'],
            'catatan' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.barang_id' => ['required', 'exists:barang,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.harga_satuan' => ['required', 'numeric', 'min:0'],
            'items.*.purchase_order_item_id' => ['nullable', 'exists:purchase_order_items,id'],
        ], [], [
            'items' => 'daftar barang',
            'items.*.qty' => 'jumlah',
            'items.*.harga_satuan' => 'harga satuan',
        ]);

        // Satu barang dua baris bikin alokasi PO dan lot jadi rancu.
        $barangIds = array_column($data['items'], 'barang_id');
        if (count($barangIds) !== count(array_unique($barangIds))) {
            return back()->withInput()->with('gagal', 'Ada barang yang dimasukkan dua kali. Gabungkan jadi satu baris.');
        }

        try {
            $penerimaan = DB::transaction(function () use ($data) {
                $penerimaan = Penerimaan::create([
                    'nomor' => $this->nomor->berikutnya(NomorDokumenService::MASUK, Carbon::parse($data['tanggal'])),
                    'tanggal' => $data['tanggal'],
                    'supplier_id' => $data['supplier_id'],
                    'purchase_order_id' => $data['purchase_order_id'] ?? null,
                    'no_faktur_supplier' => $data['no_faktur_supplier'] ?? null,
                    'catatan' => $data['catatan'] ?? null,
                    'dibuat_oleh' => auth()->id(),
                ]);

                $this->stok->catatPenerimaan($penerimaan, $data['items'], auth()->user());

                if ($penerimaan->purchase_order_id) {
                    $this->po->segarkanStatus($penerimaan->purchaseOrder);
                }

                return $penerimaan;
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->with('gagal', $e->getMessage());
        }

        return redirect()
            ->route('penerimaan.show', $penerimaan)
            ->with('sukses', "Barang masuk {$penerimaan->nomor} tersimpan.");
    }

    public function show(Penerimaan $penerimaan): View
    {
        return view('penerimaan.show', [
            'penerimaan' => $penerimaan->load([
                'supplier', 'purchaseOrder', 'pembuat',
                'items.barang', 'items.lot',
            ]),
        ]);
    }
}
