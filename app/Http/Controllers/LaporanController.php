<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\PergerakanStok;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Laporan masuk, keluar, stok, dan kartu stok.
 *
 * Semuanya dibaca dari pergerakan_stok. Tidak ada laporan yang menyimpan
 * angkanya sendiri, sehingga tidak mungkin ada dua laporan yang menyebut angka
 * berbeda untuk hal yang sama.
 */
class LaporanController extends Controller
{
    /** Posisi stok dan nilainya per barang. */
    public function stok(Request $request): View
    {
        $perTanggal = $request->date('per_tanggal')?->toDateString();

        $saldo = PergerakanStok::query()
            ->selectRaw('barang_id, COALESCE(SUM(qty), 0) as stok')
            ->when($perTanggal, fn ($q) => $q->whereDate('tanggal', '<=', $perTanggal))
            ->groupBy('barang_id')
            ->pluck('stok', 'barang_id');

        $daftar = Barang::query()
            ->with('kategori')
            ->when($request->filled('kategori'), fn ($q) => $q->where('kategori_barang_id', $request->integer('kategori')))
            ->when(! $request->boolean('termasuk_nonaktif'), fn ($q) => $q->aktif())
            ->orderBy('nama')
            ->get()
            ->map(function ($b) use ($saldo) {
                $b->stok_kini = (float) ($saldo[$b->id] ?? 0);
                $b->nilai_kini = $b->nilaiPersediaan();

                return $b;
            })
            ->when($request->boolean('hanya_bersisa'), fn ($c) => $c->filter(fn ($b) => $b->stok_kini != 0.0))
            ->values();

        return view('laporan.stok', [
            'daftar' => $daftar,
            'kategori' => KategoriBarang::orderBy('urutan')->get(),
            'totalNilai' => $daftar->sum('nilai_kini'),
            'perTanggal' => $perTanggal,
        ]);
    }

    /** Rekap barang masuk dan keluar dalam satu rentang. */
    public function mutasi(Request $request): View
    {
        $dari = $request->date('dari')?->toDateString() ?? Carbon::now()->startOfMonth()->toDateString();
        $sampai = $request->date('sampai')?->toDateString() ?? Carbon::now()->endOfMonth()->toDateString();

        // Saldo awal dihitung dari seluruh pergerakan sebelum tanggal mulai,
        // sehingga rentang mana pun tetap berimbang: awal + masuk − keluar = akhir.
        $awal = $this->saldoSampai(Carbon::parse($dari)->subDay()->toDateString());
        $akhir = $this->saldoSampai($sampai);

        $mutasi = PergerakanStok::query()
            ->selectRaw('barang_id')
            ->selectRaw('SUM(CASE WHEN qty > 0 THEN qty ELSE 0 END) as qty_masuk')
            ->selectRaw('SUM(CASE WHEN qty < 0 THEN -qty ELSE 0 END) as qty_keluar')
            ->selectRaw('SUM(CASE WHEN qty > 0 THEN nilai ELSE 0 END) as nilai_masuk')
            ->selectRaw('SUM(CASE WHEN qty < 0 THEN -nilai ELSE 0 END) as nilai_keluar')
            ->whereBetween('tanggal', [$dari, $sampai])
            ->groupBy('barang_id')
            ->get()
            ->keyBy('barang_id');

        $daftar = Barang::with('kategori')
            ->whereIn('id', $mutasi->keys()->merge($awal->keys())->unique())
            ->orderBy('nama')
            ->get()
            ->map(function ($b) use ($mutasi, $awal, $akhir) {
                $m = $mutasi->get($b->id);

                $b->saldo_awal = (float) ($awal[$b->id] ?? 0);
                $b->qty_masuk = (float) ($m->qty_masuk ?? 0);
                $b->qty_keluar = (float) ($m->qty_keluar ?? 0);
                $b->nilai_masuk = (float) ($m->nilai_masuk ?? 0);
                $b->nilai_keluar = (float) ($m->nilai_keluar ?? 0);
                $b->saldo_akhir = (float) ($akhir[$b->id] ?? 0);

                return $b;
            })
            ->filter(fn ($b) => $b->qty_masuk != 0.0 || $b->qty_keluar != 0.0 || $b->saldo_awal != 0.0)
            ->values();

        return view('laporan.mutasi', [
            'daftar' => $daftar,
            'dari' => $dari,
            'sampai' => $sampai,
            'totalMasuk' => $daftar->sum('nilai_masuk'),
            'totalKeluar' => $daftar->sum('nilai_keluar'),
        ]);
    }

    /** Kartu stok satu barang: tiap baris pergerakan beserta saldo berjalan. */
    public function kartu(Request $request): View
    {
        $barang = $request->filled('barang')
            ? Barang::find($request->integer('barang'))
            : null;

        $baris = collect();
        $saldoAwal = 0.0;

        if ($barang) {
            $dari = $request->date('dari')?->toDateString();
            $sampai = $request->date('sampai')?->toDateString();

            if ($dari) {
                $saldoAwal = (float) PergerakanStok::where('barang_id', $barang->id)
                    ->whereDate('tanggal', '<', $dari)
                    ->sum('qty');
            }

            $baris = PergerakanStok::with(['pembuat', 'sumber'])
                ->where('barang_id', $barang->id)
                ->when($dari, fn ($q) => $q->whereDate('tanggal', '>=', $dari))
                ->when($sampai, fn ($q) => $q->whereDate('tanggal', '<=', $sampai))
                ->orderBy('tanggal')->orderBy('id')
                ->get();
        }

        return view('laporan.kartu', [
            'barang' => $barang,
            'baris' => $baris,
            'saldoAwal' => $saldoAwal,
            'daftarBarang' => Barang::orderBy('nama')->get(),
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, float> */
    private function saldoSampai(string $tanggal)
    {
        return PergerakanStok::query()
            ->selectRaw('barang_id, COALESCE(SUM(qty), 0) as stok')
            ->whereDate('tanggal', '<=', $tanggal)
            ->groupBy('barang_id')
            ->pluck('stok', 'barang_id');
    }
}
