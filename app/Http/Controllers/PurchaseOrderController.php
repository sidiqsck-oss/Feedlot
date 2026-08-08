<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderRiwayat;
use App\Models\Supplier;
use App\Services\NomorDokumenService;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $po,
        private readonly NomorDokumenService $nomor,
    ) {}

    public function index(Request $request): View
    {
        $daftar = PurchaseOrder::query()
            ->with('supplier')
            ->withCount('penerimaan')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('supplier'), fn ($q) => $q->where('supplier_id', $request->integer('supplier')))
            ->latest('tanggal')->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('purchase-order.index', [
            'daftar' => $daftar,
            'supplier' => Supplier::orderBy('nama')->get(),
        ]);
    }

    public function create(): View
    {
        return view('purchase-order.form', [
            'po' => new PurchaseOrder,
            'supplier' => Supplier::aktif()->orderBy('nama')->get(),
            'barang' => Barang::aktif()->orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);

        $po = DB::transaction(function () use ($data) {
            $po = PurchaseOrder::create([
                'nomor' => $this->nomor->berikutnya(NomorDokumenService::PO, Carbon::parse($data['tanggal'])),
                'tanggal' => $data['tanggal'],
                'supplier_id' => $data['supplier_id'],
                'status' => $data['status'],
                'catatan' => $data['catatan'] ?? null,
                'dibuat_oleh' => auth()->id(),
            ]);

            foreach ($data['items'] as $baris) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'barang_id' => $baris['barang_id'],
                    'qty' => $baris['qty'],
                    'harga_satuan' => $baris['harga_satuan'],
                ]);
            }

            PurchaseOrderRiwayat::create([
                'purchase_order_id' => $po->id,
                'aksi' => 'dibuat',
                'oleh' => auth()->id(),
            ]);

            return $po;
        });

        return redirect()->route('purchase-order.show', $po)->with('sukses', "PO {$po->nomor} dibuat.");
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        return view('purchase-order.show', [
            'po' => $purchaseOrder->load([
                'supplier', 'pembuat', 'items.barang',
                'penerimaan', 'riwayat.pelaku',
            ]),
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder): View
    {
        return view('purchase-order.form', [
            'po' => $purchaseOrder->load('items.barang'),
            'supplier' => Supplier::aktif()->orderBy('nama')->get(),
            'barang' => Barang::aktif()->orderBy('nama')->get(),
        ]);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $data = $this->validasi($request);

        try {
            DB::transaction(function () use ($purchaseOrder, $data, $request) {
                $purchaseOrder->update([
                    'tanggal' => $data['tanggal'],
                    'supplier_id' => $data['supplier_id'],
                    'catatan' => $data['catatan'] ?? null,
                ]);

                $this->po->revisi(
                    $purchaseOrder,
                    $data['items'],
                    $request->string('alasan')->trim()->value() ?: 'Revisi tanpa keterangan',
                    auth()->user(),
                );
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->with('gagal', $e->getMessage());
        }

        return redirect()->route('purchase-order.show', $purchaseOrder)->with('sukses', 'PO direvisi.');
    }

    public function tutup(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:500'],
        ], ['alasan.required' => 'Sebutkan alasan penutupannya, mis. barang kosong di supplier.']);

        try {
            $this->po->tutup($purchaseOrder, $data['alasan'], auth()->user());
        } catch (RuntimeException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return back()->with('sukses', 'PO ditutup. Kekurangannya tercatat di riwayat.');
    }

    public function batal(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:500'],
        ], ['alasan.required' => 'Sebutkan alasan pembatalannya.']);

        try {
            $this->po->batalkan($purchaseOrder, $data['alasan'], auth()->user());
        } catch (RuntimeException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return back()->with('sukses', 'PO dibatalkan.');
    }

    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'status' => ['nullable', Rule::in(['draft', 'terbuka'])],
            'catatan' => ['nullable', 'string'],
            'alasan' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.barang_id' => ['required', 'exists:barang,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.harga_satuan' => ['required', 'numeric', 'min:0'],
        ], [], [
            'items' => 'daftar barang',
            'items.*.qty' => 'jumlah',
            'items.*.harga_satuan' => 'harga satuan',
        ]);

        // Satu barang dua baris bikin akumulasi qty_diterima jadi rancu saat
        // barangnya datang bertahap.
        $barangIds = array_column($data['items'], 'barang_id');

        if (count($barangIds) !== count(array_unique($barangIds))) {
            throw ValidationException::withMessages([
                'items' => 'Ada barang yang dimasukkan dua kali. Gabungkan jadi satu baris.',
            ]);
        }

        $data['status'] ??= 'terbuka';

        return $data;
    }
}
