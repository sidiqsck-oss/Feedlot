<?php

namespace App\Http\Controllers;

use App\Models\AliasBarang;
use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\PergerakanStok;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BarangController extends Controller
{
    public function index(Request $request): View
    {
        $saldo = PergerakanStok::query()
            ->selectRaw('barang_id, COALESCE(SUM(qty), 0) as stok')
            ->groupBy('barang_id')
            ->pluck('stok', 'barang_id');

        $barang = Barang::query()
            ->with('kategori')
            ->when($request->string('cari')->trim()->value(), function ($q, $cari) {
                $q->where(fn ($w) => $w->where('nama', 'like', "%{$cari}%")
                    ->orWhere('kode', 'like', "%{$cari}%"));
            })
            ->when($request->filled('kategori'), fn ($q) => $q->where('kategori_barang_id', $request->integer('kategori')))
            ->when(! $request->boolean('semua'), fn ($q) => $q->aktif())
            ->orderBy('nama')
            ->paginate(25)
            ->withQueryString();

        return view('barang.index', [
            'daftar' => $barang,
            'saldo' => $saldo,
            'kategori' => KategoriBarang::orderBy('urutan')->get(),
        ]);
    }

    public function create(): View
    {
        return view('barang.form', [
            'barang' => new Barang,
            'kategori' => KategoriBarang::orderBy('urutan')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);

        $barang = Barang::create($data);

        return redirect()
            ->route('barang.edit', $barang)
            ->with('sukses', "Barang {$barang->nama} berhasil ditambahkan.");
    }

    public function edit(Barang $barang): View
    {
        return view('barang.form', [
            'barang' => $barang->load('alias'),
            'kategori' => KategoriBarang::orderBy('urutan')->get(),
            'stok' => $barang->stok(),
            'nilai' => $barang->nilaiPersediaan(),
        ]);
    }

    public function update(Request $request, Barang $barang): RedirectResponse
    {
        $barang->update($this->validasi($request, $barang));

        return redirect()
            ->route('barang.edit', $barang)
            ->with('sukses', 'Perubahan tersimpan.');
    }

    /**
     * Barang yang sudah punya riwayat stok tidak dihapus, cuma dinonaktifkan.
     * Menghapusnya bikin nota lama menunjuk ke barang yang tidak ada.
     */
    public function destroy(Barang $barang): RedirectResponse
    {
        if ($barang->pergerakan()->exists()) {
            $barang->update(['aktif' => false]);

            return redirect()
                ->route('barang.index')
                ->with('sukses', "{$barang->nama} dinonaktifkan. Riwayat transaksinya tetap tersimpan.");
        }

        $nama = $barang->nama;
        $barang->delete();

        return redirect()
            ->route('barang.index')
            ->with('sukses', "{$nama} dihapus.");
    }

    public function tambahAlias(Request $request, Barang $barang): RedirectResponse
    {
        $data = $request->validate([
            'alias' => ['required', 'string', 'max:191', Rule::unique('alias_barang', 'alias')],
        ], [
            'alias.unique' => 'Alias itu sudah dipakai barang lain.',
        ]);

        // Disimpan huruf kecil supaya pencocokan saat impor tidak perlu
        // memikirkan besar-kecil huruf yang ditulis dokter.
        $barang->alias()->create(['alias' => mb_strtolower(trim($data['alias']))]);

        return back()->with('sukses', 'Alias ditambahkan.');
    }

    public function hapusAlias(AliasBarang $alias): RedirectResponse
    {
        $alias->delete();

        return back()->with('sukses', 'Alias dihapus.');
    }

    private function validasi(Request $request, ?Barang $barang = null): array
    {
        return $request->validate([
            'kode' => ['required', 'string', 'max:50', Rule::unique('barang', 'kode')->ignore($barang)],
            'nama' => ['required', 'string', 'max:191'],
            'kategori_barang_id' => ['required', 'exists:kategori_barang,id'],
            'satuan' => ['required', 'string', 'max:20'],
            'isi_nilai' => ['nullable', 'numeric', 'min:0.001'],
            'isi_satuan' => ['nullable', 'string', 'max:20', 'required_with:isi_nilai'],
            'stok_minimum' => ['required', 'numeric', 'min:0'],
            'aktif' => ['boolean'],
            'keterangan' => ['nullable', 'string'],
        ], [
            'isi_satuan.required_with' => 'Kalau isi diisi, satuan isinya harus diisi juga (mis. ml).',
        ]) + ['aktif' => $request->boolean('aktif')];
    }
}
