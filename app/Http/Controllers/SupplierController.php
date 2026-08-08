<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View
    {
        return view('supplier.index', [
            'daftar' => Supplier::withCount('penerimaan')->orderBy('nama')->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('supplier.form', ['supplier' => new Supplier]);
    }

    public function store(Request $request): RedirectResponse
    {
        Supplier::create($this->validasi($request));

        return redirect()->route('supplier.index')->with('sukses', 'Supplier ditambahkan.');
    }

    public function edit(Supplier $supplier): View
    {
        return view('supplier.form', ['supplier' => $supplier]);
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validasi($request, $supplier));

        return redirect()->route('supplier.index')->with('sukses', 'Perubahan tersimpan.');
    }

    /** Supplier yang sudah pernah dipakai dinonaktifkan, bukan dihapus. */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->penerimaan()->exists() || $supplier->purchaseOrders()->exists()) {
            $supplier->update(['aktif' => false]);

            return redirect()->route('supplier.index')
                ->with('sukses', "{$supplier->nama} dinonaktifkan. Nota lama tetap tersimpan.");
        }

        $nama = $supplier->nama;
        $supplier->delete();

        return redirect()->route('supplier.index')->with('sukses', "{$nama} dihapus.");
    }

    private function validasi(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'kode' => ['required', 'string', 'max:50', Rule::unique('suppliers', 'kode')->ignore($supplier)],
            'nama' => ['required', 'string', 'max:191'],
            'kontak' => ['nullable', 'string', 'max:191'],
            'telepon' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string'],
        ]) + ['aktif' => $request->boolean('aktif')];
    }
}
