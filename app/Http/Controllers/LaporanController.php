<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\PergerakanStok;
use App\Services\Ekspor\EksporCsv;
use App\Services\Ekspor\EksporExcel;
use App\Services\Ekspor\EksporPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use RuntimeException;

/**
 * Laporan masuk, keluar, stok, dan kartu stok.
 *
 * Semuanya dibaca dari pergerakan_stok. Tidak ada laporan yang menyimpan
 * angkanya sendiri, sehingga tidak mungkin ada dua laporan yang menyebut angka
 * berbeda untuk hal yang sama.
 *
 * Tampilan dan unduhan memakai pembangun data yang sama persis (susunStok,
 * susunMutasi, susunKartu). Kalau masing-masing punya query sendiri, cepat atau
 * lambat keduanya akan menyimpang tanpa ada yang sadar.
 */
class LaporanController extends Controller
{
    public function __construct(
        private readonly EksporCsv $csv,
        private readonly EksporExcel $excel,
        private readonly EksporPdf $pdf,
    ) {}

    // ── Stok & nilai ──────────────────────────────────────────────

    public function stok(Request $request): View
    {
        [$daftar, $perTanggal] = $this->susunStok($request);

        return view('laporan.stok', [
            'daftar' => $daftar,
            'kategori' => KategoriBarang::orderBy('urutan')->get(),
            'totalNilai' => $daftar->sum('nilai_kini'),
            'perTanggal' => $perTanggal,
        ]);
    }

    public function stokUnduh(Request $request)
    {
        [$daftar, $perTanggal] = $this->susunStok($request);

        $judulKolom = ['Kode', 'Nama Barang', 'Kategori', 'Satuan', 'Stok', 'Stok Minimum', 'Nilai Persediaan'];
        $berkas = 'laporan-stok-'.($perTanggal ?? now()->toDateString());

        $baris = $daftar->map(fn ($b) => [
            $b->kode,
            $b->nama,
            $b->kategori->nama,
            $b->satuan,
            (float) $b->stok_kini,
            (float) $b->stok_minimum,
            (float) $b->nilai_kini,
        ]);

        if ($request->string('format')->value() === 'excel') {
            return $this->excelAtauPesan(fn () => $this->excel->unduh(
                $berkas,
                'Laporan Stok & Nilai Persediaan',
                $judulKolom,
                $baris,
                'Posisi '.($perTanggal
                    ? Carbon::parse($perTanggal)->translatedFormat('d F Y')
                    : 'terkini, '.now()->translatedFormat('d F Y')),
                [4 => '#,##0.###', 5 => '#,##0.###', 6 => '#,##0'],
            ));
        }

        return $this->csv->unduh($berkas, $judulKolom, $baris, fn ($b) => $b);
    }

    // ── Mutasi masuk & keluar ─────────────────────────────────────

    public function mutasi(Request $request): View
    {
        [$daftar, $dari, $sampai] = $this->susunMutasi($request);

        return view('laporan.mutasi', [
            'daftar' => $daftar,
            'dari' => $dari,
            'sampai' => $sampai,
            'totalMasuk' => $daftar->sum('nilai_masuk'),
            'totalKeluar' => $daftar->sum('nilai_keluar'),
        ]);
    }

    public function mutasiUnduh(Request $request)
    {
        [$daftar, $dari, $sampai] = $this->susunMutasi($request);

        $judulKolom = [
            'Kode', 'Nama Barang', 'Satuan', 'Saldo Awal',
            'Qty Masuk', 'Nilai Masuk', 'Qty Keluar', 'Nilai Keluar', 'Saldo Akhir',
        ];

        $baris = $daftar->map(fn ($b) => [
            $b->kode, $b->nama, $b->satuan,
            (float) $b->saldo_awal,
            (float) $b->qty_masuk, (float) $b->nilai_masuk,
            (float) $b->qty_keluar, (float) $b->nilai_keluar,
            (float) $b->saldo_akhir,
        ]);

        $berkas = "laporan-mutasi-{$dari}-sd-{$sampai}";

        if ($request->string('format')->value() === 'excel') {
            return $this->excelAtauPesan(fn () => $this->excel->unduh(
                $berkas,
                'Laporan Barang Masuk & Keluar',
                $judulKolom,
                $baris,
                Carbon::parse($dari)->translatedFormat('d F Y').' sampai '.Carbon::parse($sampai)->translatedFormat('d F Y'),
                [3 => '#,##0.###', 4 => '#,##0.###', 5 => '#,##0', 6 => '#,##0.###', 7 => '#,##0', 8 => '#,##0.###'],
            ));
        }

        return $this->csv->unduh($berkas, $judulKolom, $baris, fn ($b) => $b);
    }

    // ── Kartu stok ────────────────────────────────────────────────

    public function kartu(Request $request): View
    {
        [$barang, $baris, $saldoAwal] = $this->susunKartu($request);

        return view('laporan.kartu', [
            'barang' => $barang,
            'baris' => $baris,
            'saldoAwal' => $saldoAwal,
            'daftarBarang' => Barang::orderBy('nama')->get(),
        ]);
    }

    public function kartuUnduh(Request $request)
    {
        [$barang, $baris, $saldoAwal] = $this->susunKartu($request);

        if (! $barang) {
            return back()->with('gagal', 'Pilih barangnya dulu sebelum mengunduh.');
        }

        $berkas = 'kartu-stok-'.$barang->kode;
        $periode = $this->teksPeriode($request);

        if ($request->string('format')->value() === 'pdf') {
            return $this->pdf->tampilkan($berkas, 'cetak.kartu-stok', [
                'barang' => $barang,
                'baris' => $baris,
                'saldoAwal' => $saldoAwal,
                'periode' => $periode,
            ]);
        }

        $judulKolom = ['Tanggal', 'Jenis', 'Keterangan', 'Masuk', 'Keluar', 'Saldo', 'Harga Satuan', 'Dibuat Oleh'];

        // Saldo berjalan dihitung sambil menyusun baris, sama seperti di layar,
        // supaya angka saldo di berkas unduhan identik dengan yang dilihat.
        $saldo = $saldoAwal;

        $isi = $baris->map(function ($p) use (&$saldo) {
            $saldo += (float) $p->qty;

            return [
                $p->tanggal->format('d/m/Y'),
                $p->tipe,
                $p->keterangan,
                (float) $p->qty > 0 ? (float) $p->qty : null,
                (float) $p->qty < 0 ? abs((float) $p->qty) : null,
                $saldo,
                (float) $p->harga_satuan,
                $p->pembuat->name,
            ];
        });

        if ($request->string('format')->value() === 'excel') {
            return $this->excelAtauPesan(fn () => $this->excel->unduh(
                $berkas,
                'Kartu Stok',
                $judulKolom,
                $isi,
                "{$barang->nama} ({$barang->kode}) · {$periode}",
                [3 => '#,##0.###', 4 => '#,##0.###', 5 => '#,##0.###', 6 => '#,##0'],
            ));
        }

        return $this->csv->unduh($berkas, $judulKolom, $isi, fn ($b) => $b);
    }

    /**
     * Excel punya batas jumlah baris karena PhpSpreadsheet menyusun seluruh
     * berkas di memori. Kalau kelewat, kembalikan pesan yang menyuruh pakai
     * CSV — jauh lebih baik daripada halaman putih karena kehabisan memori,
     * yang justru terlihat seperti aplikasinya rusak.
     */
    private function excelAtauPesan(callable $buat)
    {
        try {
            return $buat();
        } catch (RuntimeException $e) {
            return back()->with('gagal', $e->getMessage());
        }
    }

    // ── Pembangun data ────────────────────────────────────────────

    /** @return array{0: Collection, 1: ?string} */
    private function susunStok(Request $request): array
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

        return [$daftar, $perTanggal];
    }

    /** @return array{0: Collection, 1: string, 2: string} */
    private function susunMutasi(Request $request): array
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

        return [$daftar, $dari, $sampai];
    }

    /** @return array{0: ?Barang, 1: Collection, 2: float} */
    private function susunKartu(Request $request): array
    {
        $barang = $request->filled('barang') ? Barang::find($request->integer('barang')) : null;

        if (! $barang) {
            return [null, collect(), 0.0];
        }

        $dari = $request->date('dari')?->toDateString();
        $sampai = $request->date('sampai')?->toDateString();

        $saldoAwal = $dari
            ? (float) PergerakanStok::where('barang_id', $barang->id)
                ->whereDate('tanggal', '<', $dari)
                ->sum('qty')
            : 0.0;

        $baris = PergerakanStok::with(['pembuat', 'sumber'])
            ->where('barang_id', $barang->id)
            ->when($dari, fn ($q) => $q->whereDate('tanggal', '>=', $dari))
            ->when($sampai, fn ($q) => $q->whereDate('tanggal', '<=', $sampai))
            ->orderBy('tanggal')->orderBy('id')
            ->get();

        return [$barang, $baris, $saldoAwal];
    }

    private function teksPeriode(Request $request): string
    {
        $dari = $request->date('dari');
        $sampai = $request->date('sampai');

        return match (true) {
            $dari && $sampai => $dari->translatedFormat('d M Y').' – '.$sampai->translatedFormat('d M Y'),
            (bool) $dari => 'sejak '.$dari->translatedFormat('d M Y'),
            (bool) $sampai => 'sampai '.$sampai->translatedFormat('d M Y'),
            default => 'seluruh riwayat',
        };
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
