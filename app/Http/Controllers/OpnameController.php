<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\Opname;
use App\Models\OpnameItem;
use App\Services\NomorDokumenService;
use App\Services\StokService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/**
 * Stok opname bulanan.
 *
 * Alurnya dua tahap: buat dulu (stok sistem dibekukan saat itu juga), lalu isi
 * hasil hitungan fisik dan finalkan. Pembekuan itu penting — kalau stok sistem
 * dihitung ulang saat difinalkan, transaksi yang masuk di sela-sela penghitungan
 * fisik akan membuat selisihnya bohong.
 */
class OpnameController extends Controller
{
    public function __construct(
        private readonly StokService $stok,
        private readonly NomorDokumenService $nomor,
    ) {}

    public function index(): View
    {
        return view('opname.index', [
            'daftar' => Opname::withCount('items')
                ->orderByDesc('periode_tahun')->orderByDesc('periode_bulan')
                ->paginate(24),
        ]);
    }

    public function create(): View
    {
        $bulanLalu = Carbon::now()->subMonthNoOverflow();

        return view('opname.create', [
            'tahunUsulan' => $bulanLalu->year,
            'bulanUsulan' => $bulanLalu->month,
            'sudahAda' => Opname::orderByDesc('periode_tahun')->orderByDesc('periode_bulan')->limit(6)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'periode_bulan' => ['required', 'integer', 'between:1,12'],
            'periode_tahun' => ['required', 'integer', 'between:2020,2100'],
            'tanggal' => ['required', 'date'],
            'catatan' => ['nullable', 'string'],
        ]);

        $bentrok = Opname::where('periode_tahun', $data['periode_tahun'])
            ->where('periode_bulan', $data['periode_bulan'])
            ->first();

        if ($bentrok) {
            return redirect()
                ->route('opname.show', $bentrok)
                ->with('gagal', 'Opname untuk periode itu sudah ada. Ini yang sudah dibuat.');
        }

        $opname = DB::transaction(function () use ($data) {
            $opname = Opname::create([
                'nomor' => $this->nomor->berikutnya(NomorDokumenService::OPNAME, Carbon::parse($data['tanggal'])),
                'tanggal' => $data['tanggal'],
                'periode_bulan' => $data['periode_bulan'],
                'periode_tahun' => $data['periode_tahun'],
                'catatan' => $data['catatan'] ?? null,
                'status' => 'draft',
                'dibuat_oleh' => auth()->id(),
            ]);

            // Semua barang aktif ikut dihitung, termasuk yang stoknya nol —
            // barang yang sistemnya bilang habis tapi fisiknya ada juga selisih
            // yang perlu ketahuan.
            foreach (Barang::aktif()->orderBy('nama')->get() as $barang) {
                OpnameItem::create([
                    'opname_id' => $opname->id,
                    'barang_id' => $barang->id,
                    'stok_sistem' => $barang->stokPerTanggal($data['tanggal']),
                    'stok_fisik' => null,
                ]);
            }

            return $opname;
        });

        return redirect()
            ->route('opname.show', $opname)
            ->with('sukses', "Opname {$opname->nomor} dibuat. Stok sistem sudah dibekukan, silakan isi hasil hitungan fisik.");
    }

    public function show(Opname $opname): View
    {
        return view('opname.show', [
            'opname' => $opname->load(['items.barang.kategori', 'pembuat']),
        ]);
    }

    /** Menyimpan angka hitungan fisik. Belum menyentuh stok. */
    public function update(Request $request, Opname $opname): RedirectResponse
    {
        if ($opname->sudahFinal()) {
            return back()->with('gagal', 'Opname ini sudah difinalkan dan tidak bisa diubah lagi.');
        }

        $data = $request->validate([
            'fisik' => ['required', 'array'],
            'fisik.*' => ['nullable', 'numeric', 'min:0'],
            'keterangan' => ['array'],
            'keterangan.*' => ['nullable', 'string', 'max:191'],
        ]);

        DB::transaction(function () use ($opname, $data) {
            foreach ($opname->items as $item) {
                $fisik = $data['fisik'][$item->id] ?? null;

                $item->update([
                    'stok_fisik' => $fisik === '' ? null : $fisik,
                    'keterangan' => $data['keterangan'][$item->id] ?? null,
                ]);
            }
        });

        return back()->with('sukses', 'Hasil hitungan tersimpan. Belum memengaruhi stok sampai difinalkan.');
    }

    public function finalkan(Opname $opname): RedirectResponse
    {
        try {
            $this->stok->finalkanOpname($opname, auth()->user());
        } catch (RuntimeException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return redirect()
            ->route('opname.show', $opname)
            ->with('sukses', 'Opname difinalkan. Selisihnya sudah tercatat di kartu stok.');
    }
}
