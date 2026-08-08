<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\Opname;
use App\Models\PergerakanStok;
use App\Models\PurchaseOrder;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $awalBulan = Carbon::now()->startOfMonth()->toDateString();
        $akhirBulan = Carbon::now()->endOfMonth()->toDateString();

        // Saldo tiap barang dihitung dari kartu stok, bukan dibaca dari kolom.
        // Diambil sekali sebagai satu query supaya tidak jadi N+1 saat daftar
        // barangnya panjang.
        $saldo = PergerakanStok::query()
            ->selectRaw('barang_id, COALESCE(SUM(qty), 0) as stok')
            ->groupBy('barang_id')
            ->pluck('stok', 'barang_id');

        $barang = Barang::aktif()->with('kategori')->orderBy('nama')->get();

        $menipis = $barang
            ->filter(fn ($b) => (float) $b->stok_minimum > 0
                && (float) ($saldo[$b->id] ?? 0) <= (float) $b->stok_minimum)
            ->values();

        return view('dashboard', [
            'jumlahBarang' => $barang->count(),
            'nilaiPersediaan' => $barang->sum(fn ($b) => $b->nilaiPersediaan()),
            'menipis' => $menipis,
            'saldo' => $saldo,

            'masukBulanIni' => (float) PergerakanStok::masuk()
                ->periode($awalBulan, $akhirBulan)->sum('nilai'),

            'keluarBulanIni' => abs((float) PergerakanStok::keluar()
                ->periode($awalBulan, $akhirBulan)->sum('nilai')),

            'poBerjalan' => PurchaseOrder::whereIn('status', ['terbuka', 'sebagian'])->count(),

            'opnameBulanIni' => Opname::where('periode_tahun', Carbon::now()->year)
                ->where('periode_bulan', Carbon::now()->month)
                ->first(),

            'pergerakanTerakhir' => PergerakanStok::with('barang')
                ->latest('id')->limit(12)->get(),
        ]);
    }
}
