<?php

namespace App\Http\Controllers;

use App\Models\Petugas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Master petugas: orang yang mengambil barang dari gudang.
 *
 * Dibuat sebagai data, bukan pilihan yang di-hardcode, supaya bisa ditambah
 * dan dihapus sendiri tanpa mengubah kode.
 */
class PetugasController extends Controller
{
    public function index(): View
    {
        return view('petugas.index', [
            'daftar' => Petugas::withCount('pengeluaran')->orderBy('nama')->get(),
        ]);
    }

    public function create(): View
    {
        return view('petugas.form', ['petugas' => new Petugas]);
    }

    public function store(Request $request): RedirectResponse
    {
        Petugas::create($this->validasi($request));

        return redirect()->route('petugas.index')->with('sukses', 'Petugas ditambahkan.');
    }

    public function edit(Petugas $petugas): View
    {
        return view('petugas.form', ['petugas' => $petugas]);
    }

    public function update(Request $request, Petugas $petugas): RedirectResponse
    {
        $petugas->update($this->validasi($request, $petugas));

        return redirect()->route('petugas.index')->with('sukses', 'Perubahan tersimpan.');
    }

    public function destroy(Petugas $petugas): RedirectResponse
    {
        $nama = $petugas->nama;

        // Menghapus petugas yang sudah pernah mengambil barang bikin nota lama
        // kehilangan nama pengambilnya. Nonaktifkan saja.
        if (! $petugas->bolehDihapus()) {
            $petugas->update(['aktif' => false]);

            return redirect()->route('petugas.index')
                ->with('sukses', "{$nama} dinonaktifkan. Nota lama tetap mencantumkan namanya.");
        }

        $petugas->delete();

        return redirect()->route('petugas.index')->with('sukses', "{$nama} dihapus.");
    }

    private function validasi(Request $request, ?Petugas $petugas = null): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:191', Rule::unique('petugas', 'nama')->ignore($petugas)],
            'peran' => ['required', Rule::in(['dokter', 'operator', 'lainnya'])],
        ]) + ['aktif' => $request->boolean('aktif')];
    }
}
