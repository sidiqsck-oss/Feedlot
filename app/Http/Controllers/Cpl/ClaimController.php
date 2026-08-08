<?php

namespace App\Http\Controllers\Cpl;

use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Models\Induksi;
use App\Models\PembelianShipment;
use App\Models\Shipment;
use App\Services\Ekspor\EksporCsv;
use App\Services\Ekspor\EksporExcel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Claim ke importir.
 *
 * Bedanya dengan halaman CPL yang lain: di sini datanya diketik, bukan
 * diunggah. Kejadiannya satu-satu dan tidak terjadwal — sapi mati di pen,
 * dijual salvage, atau ketahuan sakit bawaan — jadi tidak pernah ada berkas
 * Excel yang bisa diunggah untuk itu.
 *
 * Yang paling sering justru mati SEBELUM induksi, dan sapi itu belum punya
 * baris induksi sama sekali. Karena itu form ini tidak pernah mewajibkan
 * induksi: penambatnya shipment, dan RFID cuma dicocokkan kalau kebetulan
 * ada.
 */
class ClaimController extends Controller
{
    public function __construct(
        private readonly EksporCsv $csv,
        private readonly EksporExcel $excel,
    ) {}

    public function index(Request $request): View
    {
        $saring = $this->saring($request);
        $daftar = $this->kueri($saring)->paginate(30)->withQueryString();

        return view('cpl.claim.index', [
            'daftar' => $daftar,
            'saring' => $saring,
            'shipment' => Shipment::orderByDesc('nomor')->get(),
            'ringkasan' => $this->ringkasan($saring),
            'tibaFeedlot' => $this->tanggalTiba(),
        ]);
    }

    public function create(Request $request): View
    {
        $claim = new Claim([
            'shipment_id' => $request->integer('shipment_id') ?: null,
            'tanggal_kejadian' => now()->toDateString(),
            'fase' => 'sebelum_induksi',
            'jenis_claim' => 'mati',
            'status_klaim' => 'diajukan',
        ]);

        return view('cpl.claim.form', $this->bahanForm($claim));
    }

    public function store(Request $request): RedirectResponse
    {
        $claim = Claim::create($this->validasi($request) + ['dibuat_oleh' => $request->user()->id]);

        return redirect()
            ->route('cpl.claim.index', ['shipment' => $claim->shipment->kode])
            ->with('sukses', 'Claim dicatat.');
    }

    public function edit(Claim $claim): View
    {
        return view('cpl.claim.form', $this->bahanForm($claim));
    }

    public function update(Request $request, Claim $claim): RedirectResponse
    {
        $claim->update($this->validasi($request, $claim));

        return redirect()->route('cpl.claim.index')->with('sukses', 'Perubahan tersimpan.');
    }

    public function destroy(Claim $claim): RedirectResponse
    {
        $claim->delete();

        return redirect()->route('cpl.claim.index')->with('sukses', 'Claim dihapus.');
    }

    /**
     * Cari sapi berdasarkan RFID di dalam satu shipment.
     *
     * Dipakai form untuk mengisi sendiri ear tag dan berat induksi begitu RFID
     * diketik. Kalau tidak ketemu, itu bukan kesalahan — kemungkinan besar
     * sapinya memang mati sebelum sempat diinduksi.
     */
    public function cari(Request $request)
    {
        $induksi = Induksi::where('shipment_id', $request->integer('shipment_id'))
            ->where('rfid', trim((string) $request->string('rfid')))
            ->first();

        if (! $induksi) {
            return response()->json(['ketemu' => false]);
        }

        return response()->json([
            'ketemu' => true,
            'induksi_id' => $induksi->id,
            'ear_tag' => $induksi->ear_tag,
            'berat_induksi' => $induksi->berat_induksi === null ? null : (float) $induksi->berat_induksi,
            'tanggal_induksi' => $induksi->tanggal_induksi?->toDateString(),
        ]);
    }

    public function unduh(Request $request)
    {
        $saring = $this->saring($request);
        $baris = $this->kueri($saring)->get();

        if ($baris->isEmpty()) {
            return back()->with('gagal', 'Tidak ada claim yang cocok dengan penyaring ini.');
        }

        $tiba = $this->tanggalTiba();

        $judul = [
            'Tanggal', 'Shipment', 'RFID', 'Ear Tag', 'Jenis Claim', 'Fase',
            'Umur (hari)', 'Diagnosa', 'Berat (kg)', 'Nilai Klaim', 'Status', 'Keterangan',
        ];

        $isi = $baris->map(fn (Claim $c) => [
            $c->tanggal_kejadian->format('d/m/Y'),
            $c->shipment->kode,
            $c->rfid ?: '',
            $c->ear_tag ?: '',
            self::JENIS[$c->jenis_claim],
            self::FASE[$c->fase],
            $this->umurHari($c, $tiba) ?? '',
            $c->diagnosa ?: '',
            $c->berat === null ? '' : (float) $c->berat,
            $c->nilai_klaim === null ? '' : (float) $c->nilai_klaim,
            self::STATUS[$c->status_klaim],
            $c->keterangan ?: '',
        ]);

        $berkas = 'Claim-'.now()->format('Ymd');

        if ($request->string('format')->value() === 'excel') {
            try {
                return $this->excel->unduh($berkas, 'Claim ke Importir', $judul, $isi, $this->teksPeriode($saring));
            } catch (RuntimeException $e) {
                return back()->with('gagal', $e->getMessage());
            }
        }

        return $this->csv->unduh($berkas, $judul, $isi, fn ($b) => $b);
    }

    // ── Label ───────────────────────────────────────────────────────

    public const JENIS = [
        'mati' => 'Mati',
        'salvage' => 'Salvage',
        'sakit_bawaan' => 'Sakit Bawaan',
    ];

    public const FASE = [
        'sebelum_induksi' => 'Sebelum Induksi',
        'sesudah_induksi' => 'Sesudah Induksi',
    ];

    public const STATUS = [
        'diajukan' => 'Diajukan',
        'disetujui' => 'Disetujui',
        'ditolak' => 'Ditolak',
    ];

    // ── Pembantu ────────────────────────────────────────────────────

    private function saring(Request $request): array
    {
        return [
            'dari' => $request->date('dari')?->toDateString(),
            'sampai' => $request->date('sampai')?->toDateString(),
            'shipment' => $request->string('shipment')->value() ?: null,
            'jenis_claim' => $request->string('jenis_claim')->value() ?: null,
            'fase' => $request->string('fase')->value() ?: null,
            'status_klaim' => $request->string('status_klaim')->value() ?: null,
        ];
    }

    private function kueri(array $saring)
    {
        return Claim::query()
            ->with(['shipment', 'pembuat'])
            ->when($saring['dari'], fn ($q, $v) => $q->whereDate('tanggal_kejadian', '>=', $v))
            ->when($saring['sampai'], fn ($q, $v) => $q->whereDate('tanggal_kejadian', '<=', $v))
            ->when($saring['shipment'], fn ($q, $v) => $q->whereHas('shipment', fn ($s) => $s->where('kode', $v)))
            ->when($saring['jenis_claim'], fn ($q, $v) => $q->where('jenis_claim', $v))
            ->when($saring['fase'], fn ($q, $v) => $q->where('fase', $v))
            ->when($saring['status_klaim'], fn ($q, $v) => $q->where('status_klaim', $v))
            ->orderByDesc('tanggal_kejadian')
            ->orderByDesc('id');
    }

    /**
     * Rekap dihitung dari kueri yang sama dengan tabelnya, jadi angka di kartu
     * tidak pernah bercerita lain dari baris di bawahnya.
     */
    private function ringkasan(array $saring): array
    {
        $semua = $this->kueri($saring)->get();

        return [
            'total' => $semua->count(),
            'mati_sebelum' => $semua->where('jenis_claim', 'mati')->where('fase', 'sebelum_induksi')->count(),
            'mati_sesudah' => $semua->where('jenis_claim', 'mati')->where('fase', 'sesudah_induksi')->count(),
            'salvage' => $semua->where('jenis_claim', 'salvage')->count(),
            'sakit_bawaan' => $semua->where('jenis_claim', 'sakit_bawaan')->count(),
            'nilai' => $semua->whereNotNull('nilai_klaim')->sum(fn ($c) => (float) $c->nilai_klaim),
            'disetujui' => $semua->where('status_klaim', 'disetujui')->count(),
            'ditolak' => $semua->where('status_klaim', 'ditolak')->count(),
        ];
    }

    /**
     * Tanggal tiba tiap shipment, diambil sekali untuk semua baris.
     *
     * Claim::umurHari() menembak database sendiri-sendiri; dipakai satu-satu
     * di halaman detail masih wajar, tapi untuk tabel dan unduhan itu jadi
     * satu kueri per baris.
     *
     * @return Collection<int, string>
     */
    private function tanggalTiba(): Collection
    {
        return PembelianShipment::whereNotNull('tanggal_tiba')
            ->orderBy('tanggal_tiba')
            ->pluck('tanggal_tiba', 'shipment_id')
            ->map(fn ($t) => Carbon::parse($t)->toDateString());
    }

    private function umurHari(Claim $claim, Collection $tiba): ?int
    {
        $awal = $tiba->get($claim->shipment_id);

        if (! $awal) {
            return null;
        }

        return (int) Carbon::parse($awal)->diffInDays($claim->tanggal_kejadian, absolute: false);
    }

    private function bahanForm(Claim $claim): array
    {
        return [
            'claim' => $claim,
            'shipment' => Shipment::orderByDesc('nomor')->get(),
            'jenisClaim' => self::JENIS,
            'fase' => self::FASE,
            'statusKlaim' => self::STATUS,
        ];
    }

    private function validasi(Request $request, ?Claim $claim = null): array
    {
        $data = $request->validate([
            'shipment_id' => ['required', 'exists:shipments,id'],
            'rfid' => ['nullable', 'string', 'max:50'],
            'ear_tag' => ['nullable', 'string', 'max:50'],
            'tanggal_kejadian' => ['required', 'date'],
            'jenis_claim' => ['required', Rule::in(array_keys(self::JENIS))],
            'fase' => ['required', Rule::in(array_keys(self::FASE))],
            'diagnosa' => ['nullable', 'string', 'max:191'],
            'berat' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'nilai_klaim' => ['nullable', 'numeric', 'min:0'],
            'status_klaim' => ['required', Rule::in(array_keys(self::STATUS))],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ], [
            'shipment_id.required' => 'Pilih dulu shipment asal sapinya.',
        ]);

        /*
         * induksi_id tidak pernah dipercayakan ke form. Kalau RFID-nya cocok
         * dengan sapi yang sudah diinduksi di shipment itu, sambungannya
         * dibuat di sini; kalau tidak, dibiarkan kosong.
         *
         * Konsekuensinya fase juga dikoreksi: mengaku "sesudah induksi" tapi
         * baris induksinya tidak ada itu tidak mungkin benar.
         */
        // Kolom nullable yang tidak diisi sama sekali tidak ikut muncul di
        // hasil validate(), jadi kuncinya dipastikan ada dulu.
        $data += array_fill_keys(
            ['rfid', 'ear_tag', 'diagnosa', 'berat', 'nilai_klaim', 'keterangan'],
            null,
        );

        $data['induksi_id'] = null;

        if ($data['rfid']) {
            $induksi = Induksi::where('shipment_id', $data['shipment_id'])
                ->where('rfid', $data['rfid'])
                ->first();

            if ($induksi) {
                $data['induksi_id'] = $induksi->id;
                $data['ear_tag'] = $data['ear_tag'] ?: $induksi->ear_tag;
            }
        }

        if ($data['fase'] === 'sesudah_induksi' && ! $data['induksi_id']) {
            $data['fase'] = 'sebelum_induksi';
        }

        return $data;
    }

    private function teksPeriode(array $saring): ?string
    {
        if (! $saring['dari'] && ! $saring['sampai']) {
            return null;
        }

        return 'Periode '.($saring['dari'] ?: '…').' s/d '.($saring['sampai'] ?: '…');
    }
}
