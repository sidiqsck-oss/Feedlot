<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShipmentController extends Controller
{
    public function index(): View
    {
        return view('shipment.index', [
            'daftar' => Shipment::withCount('pengeluaran')->orderByDesc('nomor')->paginate(30),
        ]);
    }

    public function create(): View
    {
        return view('shipment.form', ['shipment' => new Shipment]);
    }

    public function store(Request $request): RedirectResponse
    {
        Shipment::create($this->validasi($request));

        return redirect()->route('shipment.index')->with('sukses', 'Shipment ditambahkan.');
    }

    public function edit(Shipment $shipment): View
    {
        return view('shipment.form', ['shipment' => $shipment]);
    }

    public function update(Request $request, Shipment $shipment): RedirectResponse
    {
        $shipment->update($this->validasi($request, $shipment));

        return redirect()->route('shipment.index')->with('sukses', 'Perubahan tersimpan.');
    }

    public function destroy(Shipment $shipment): RedirectResponse
    {
        if ($shipment->pengeluaran()->exists() || $shipment->treatment()->exists()) {
            $shipment->update(['aktif' => false]);

            return redirect()->route('shipment.index')
                ->with('sukses', "{$shipment->kode} dinonaktifkan.");
        }

        $kode = $shipment->kode;
        $shipment->delete();

        return redirect()->route('shipment.index')->with('sukses', "{$kode} dihapus.");
    }

    private function validasi(Request $request, ?Shipment $shipment = null): array
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:50'],
            'tanggal_masuk' => ['nullable', 'date'],
            'keterangan' => ['nullable', 'string', 'max:191'],
        ]);

        // "90" yang ditulis tangan di form kertas jadi "SCK90". Dinormalkan di
        // sini supaya kodenya seragam apa pun cara mengetiknya.
        $data['kode'] = Shipment::normalisasiKode($data['kode']);

        $request->merge(['kode' => $data['kode']]);
        $request->validate([
            'kode' => [Rule::unique('shipments', 'kode')->ignore($shipment)],
        ], ['kode.unique' => 'Shipment dengan kode itu sudah ada.']);

        $data['nomor'] = preg_match('/^SCK(\d+)$/', $data['kode'], $c) ? (int) $c[1] : null;
        $data['aktif'] = $request->boolean('aktif');

        return $data;
    }
}
