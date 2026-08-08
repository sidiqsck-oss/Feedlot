<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\Penerimaan;
use App\Models\Pengeluaran;
use App\Models\Petugas;
use App\Models\Shipment;
use App\Models\Supplier;
use App\Models\Treatment;
use App\Models\TreatmentItem;
use App\Models\User;
use App\Services\BiayaObatService;
use App\Services\NomorDokumenService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Biaya obat per ekor.
 *
 * Angka acuannya sama dengan docs/rancangan-database.md bagian 11: 10 botol
 * @ 85.000 lalu 10 @ 92.000, dokter ambil 12 botol (HPP 1.034.000), sehingga
 * harga rata-rata pengambilan 86.166,67/botol. Botol isi 100 ml, jadi dosis
 * 20 ml bernilai sekitar Rp 17.233.
 */
class BiayaObatTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Barang $limoxin;

    private Supplier $supplier;

    private Shipment $shipment;

    private StokService $stok;

    private NomorDokumenService $nomor;

    private BiayaObatService $biaya;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stok = app(StokService::class);
        $this->nomor = app(NomorDokumenService::class);
        $this->biaya = app(BiayaObatService::class);

        $this->user = User::create([
            'name' => 'Sidiq', 'email' => 'sidiq@example.test',
            'password' => 'rahasia', 'peran' => 'admin', 'aktif' => true,
        ]);

        $this->actingAs($this->user);

        $kategori = KategoriBarang::create(['nama' => 'Obat Cair']);
        $this->supplier = Supplier::create(['kode' => 'SUP-01', 'nama' => 'Supplier A']);
        $this->shipment = Shipment::create(['kode' => 'SCK90', 'nomor' => 90]);

        $this->limoxin = Barang::create([
            'kode' => 'OVK-001',
            'nama' => 'Limoxin-200 LA',
            'kategori_barang_id' => $kategori->id,
            'satuan' => 'botol',
            'isi_nilai' => 100,
            'isi_satuan' => 'ml',
        ]);
    }

    public function test_dosis_dokter_dinilai_dengan_harga_pengambilan(): void
    {
        $this->terima('2026-02-01', 10, 85_000);
        $this->terima('2026-02-10', 10, 92_000);
        $this->keluarkan('2026-02-15', 12);

        $item = $this->rawat('4250', '2026-02-27', 20);

        // 1.034.000 / 12 = 86.166,67 per botol
        $this->assertEqualsWithDelta(86_166.67, $this->biaya->hargaSatuan($this->limoxin, '2026-02-27'), 0.01);

        // Botol isi 100 ml → 861,67 per ml
        $this->assertEqualsWithDelta(861.67, $this->biaya->hargaPerDosis($item, '2026-02-27'), 0.01);

        // Dosis 20 ml → Rp 17.233
        $this->assertEqualsWithDelta(17_233.33, $this->biaya->biayaItem($item, '2026-02-27'), 0.01);
    }

    /**
     * Harga yang dipakai harga saat perawatan, bukan harga stok hari ini.
     * Botol yang sudah disuntikkan bulan lalu tidak ikut naik harganya cuma
     * karena pembelian berikutnya lebih mahal.
     */
    public function test_pengambilan_sesudah_perawatan_tidak_ikut_menghitung(): void
    {
        $this->terima('2026-02-01', 10, 85_000);
        $this->keluarkan('2026-02-15', 5);

        $item = $this->rawat('4250', '2026-02-20', 20);
        $awal = $this->biaya->biayaItem($item, '2026-02-20');

        // Pembelian dan pengambilan jauh lebih mahal, tapi sesudah tanggal
        // perawatan.
        $this->terima('2026-03-01', 10, 200_000);
        $this->keluarkan('2026-03-05', 10);

        $ulang = app(BiayaObatService::class);

        $this->assertEqualsWithDelta(17_000.0, $awal, 0.01);
        $this->assertEqualsWithDelta(17_000.0, $ulang->biayaItem($item->fresh(), '2026-02-20'), 0.01);
    }

    public function test_obat_yang_belum_dipetakan_tidak_dianggap_gratis(): void
    {
        $this->terima('2026-02-01', 10, 85_000);
        $this->keluarkan('2026-02-15', 5);

        $rawat = $this->treatment('4250', '2026-02-20');

        // Nama obat yang belum ada di master: barang_id kosong.
        $item = TreatmentItem::create([
            'treatment_id' => $rawat->id,
            'nama_obat_asli' => 'vit b komplek',
            'dosis' => 10,
            'satuan_dosis' => 'ml',
        ]);

        $this->assertNull($this->biaya->biayaItem($item, '2026-02-20'));
        $this->assertStringContainsString('belum dipetakan', $this->biaya->alasanKosong($item));

        // Biaya per ekornya NULL, bukan nol.
        $baris = $this->biaya->perEkor(Treatment::with(['items.barang', 'shipment'])->get())->first();

        $this->assertNull($baris['biaya']);
        $this->assertNotEmpty($baris['masalah']);
    }

    public function test_satuan_dosis_yang_tidak_sepadan_ditolak(): void
    {
        $this->terima('2026-02-01', 10, 85_000);
        $this->keluarkan('2026-02-15', 5);

        // Botolnya isi ml, tapi dokter menulis dosis dalam mg.
        $item = $this->rawat('4250', '2026-02-20', 20, 'mg');

        $this->assertNull($this->biaya->biayaItem($item, '2026-02-20'));
        $this->assertStringContainsString('tidak cocok', $this->biaya->alasanKosong($item));
    }

    /** Barang yang dosisnya memang dalam satuan stoknya sendiri. */
    public function test_dosis_dalam_satuan_stok_dinilai_langsung(): void
    {
        $sarung = Barang::create([
            'kode' => 'BHP-002',
            'nama' => 'Sarung Tangan Latex',
            'kategori_barang_id' => $this->limoxin->kategori_barang_id,
            'satuan' => 'pcs',
        ]);

        $this->terima('2026-02-01', 100, 1_500, $sarung);
        $this->keluarkan('2026-02-15', 10, $sarung);

        $rawat = $this->treatment('4250', '2026-02-20');

        $item = TreatmentItem::create([
            'treatment_id' => $rawat->id,
            'barang_id' => $sarung->id,
            'nama_obat_asli' => 'Sarung tangan',
            'dosis' => 2,
            'satuan_dosis' => 'pcs',
        ]);

        $this->assertEqualsWithDelta(3_000.0, $this->biaya->biayaItem($item, '2026-02-20'), 0.01);
        $this->assertNull($this->biaya->alasanKosong($item));
    }

    /**
     * Ear tag berulang antar shipment. Kalau digabung, biaya dua ekor berbeda
     * akan menumpuk jadi satu.
     */
    public function test_ear_tag_sama_di_shipment_berbeda_tidak_digabung(): void
    {
        $lain = Shipment::create(['kode' => 'SCK91', 'nomor' => 91]);

        $this->terima('2026-02-01', 20, 85_000);
        $this->keluarkan('2026-02-15', 12);

        $this->rawat('4250', '2026-02-20', 20);
        $this->rawat('4250', '2026-02-21', 20, 'ml', $lain);

        $baris = $this->biaya->perEkor(Treatment::with(['items.barang', 'shipment'])->get());

        $this->assertCount(2, $baris);
        $this->assertSame(['SCK90', 'SCK91'], $baris->pluck('shipment')->sort()->values()->all());
    }

    public function test_halaman_biaya_obat_menampilkan_angkanya(): void
    {
        $this->terima('2026-02-01', 10, 85_000);
        $this->terima('2026-02-10', 10, 92_000);
        $this->keluarkan('2026-02-15', 12);
        $this->rawat('4250', '2026-02-27', 20);

        $this->get(route('laporan.biaya-obat'))
            ->assertOk()
            ->assertSee('4250')
            ->assertSee('Rp 17.233');
    }

    public function test_unduhan_ikut_penyaring(): void
    {
        $lain = Shipment::create(['kode' => 'SCK91', 'nomor' => 91]);

        $this->terima('2026-02-01', 20, 85_000);
        $this->keluarkan('2026-02-15', 12);

        $this->rawat('4250', '2026-02-20', 20);
        $this->rawat('9999', '2026-02-21', 20, 'ml', $lain);

        $isi = $this->get(route('laporan.biaya-obat.unduh', ['shipment' => 'SCK91']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('9999', $isi);
        $this->assertStringNotContainsString('4250', $isi);
    }

    // ── Pembantu ────────────────────────────────────────────────────

    private function terima(string $tanggal, float $qty, float $harga, ?Barang $barang = null): void
    {
        $penerimaan = Penerimaan::create([
            'nomor' => $this->nomor->berikutnya('M', Carbon::parse($tanggal)),
            'tanggal' => $tanggal,
            'supplier_id' => $this->supplier->id,
            'dibuat_oleh' => $this->user->id,
        ]);

        $this->stok->catatPenerimaan($penerimaan, [[
            'barang_id' => ($barang ?? $this->limoxin)->id,
            'qty' => $qty,
            'harga_satuan' => $harga,
        ]], $this->user);
    }

    private function keluarkan(string $tanggal, float $qty, ?Barang $barang = null): void
    {
        $petugas = Petugas::firstOrCreate(['nama' => 'Gunawan'], ['peran' => 'dokter']);

        $pengeluaran = Pengeluaran::create([
            'nomor' => $this->nomor->berikutnya('K', Carbon::parse($tanggal)),
            'tanggal' => $tanggal,
            'tujuan' => 'dokter',
            'petugas_id' => $petugas->id,
            'dibuat_oleh' => $this->user->id,
        ]);

        $this->stok->catatPengeluaran($pengeluaran, [[
            'barang_id' => ($barang ?? $this->limoxin)->id,
            'qty' => $qty,
        ]], $this->user);
    }

    private function treatment(string $earTag, string $tanggal, ?Shipment $shipment = null): Treatment
    {
        return Treatment::create([
            'shipment_id' => ($shipment ?? $this->shipment)->id,
            'ear_tag' => $earTag,
            'tanggal' => $tanggal,
            'diagnosa' => 'Pincang',
            'hash_baris' => Treatment::hash([$earTag, $tanggal, uniqid()]),
        ]);
    }

    private function rawat(
        string $earTag,
        string $tanggal,
        float $dosis,
        string $satuanDosis = 'ml',
        ?Shipment $shipment = null,
    ): TreatmentItem {
        return TreatmentItem::create([
            'treatment_id' => $this->treatment($earTag, $tanggal, $shipment)->id,
            'barang_id' => $this->limoxin->id,
            'nama_obat_asli' => 'Limoxin 200',
            'dosis' => $dosis,
            'satuan_dosis' => $satuanDosis,
        ]);
    }
}
