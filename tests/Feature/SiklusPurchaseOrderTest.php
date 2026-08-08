<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\Penerimaan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseOrderService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Siklus PO ketika barang tidak datang penuh.
 *
 * Kondisi nyata yang ditangani: barang kosong di supplier, datang kurang dari
 * yang diminta, PO perlu ditambah atau dikurangi setelah dibuat, dan
 * keputusan untuk menyudahi PO meski belum terpenuhi.
 */
class SiklusPurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Barang $barang;
    private Supplier $supplier;
    private PurchaseOrderService $po;
    private StokService $stok;

    protected function setUp(): void
    {
        parent::setUp();

        $this->po = app(PurchaseOrderService::class);
        $this->stok = app(StokService::class);

        $this->user = User::create([
            'name' => 'Sidiq',
            'email' => 'sidiq@example.test',
            'password' => 'rahasia',
            'peran' => 'admin',
        ]);

        $kategori = KategoriBarang::create(['nama' => 'Obat Cair']);
        $this->supplier = Supplier::create(['kode' => 'SUP-01', 'nama' => 'Supplier A']);

        $this->barang = Barang::create([
            'kode' => 'OVK-001',
            'nama' => 'Limoxin-200 LA',
            'kategori_barang_id' => $kategori->id,
            'satuan' => 'botol',
        ]);
    }

    public function test_po_terpenuhi_penuh_jadi_selesai(): void
    {
        $po = $this->buatPo(qty: 10);

        $this->terimaSebagian($po, 10);
        $this->po->segarkanStatus($po);

        $this->assertSame('selesai', $po->fresh()->status);
    }

    public function test_barang_datang_bertahap_statusnya_sebagian(): void
    {
        $po = $this->buatPo(qty: 10);

        $this->terimaSebagian($po, 4);
        $this->po->segarkanStatus($po);

        $this->assertSame('sebagian', $po->fresh()->status);
        $this->assertSame(6.0, $po->items()->first()->sisa());
    }

    public function test_po_bisa_ditutup_meski_barang_kurang(): void
    {
        $po = $this->buatPo(qty: 10);
        $this->terimaSebagian($po, 4);
        $this->po->segarkanStatus($po);

        $ditutup = $this->po->tutup($po, 'Sisa 6 botol kosong di supplier', $this->user);

        $this->assertSame('ditutup', $ditutup->status);
        $this->assertSame('Sisa 6 botol kosong di supplier', $ditutup->alasan_penutupan);
        $this->assertSame($this->user->id, $ditutup->ditutup_oleh);

        // Kekurangannya ikut tercatat, supaya laporan bisa menunjukkan
        // berapa yang tidak terpenuhi.
        // Dibaca balik dari kolom JSON, jadi angkanya dicocokkan sebagai nilai
        // (JSON tidak membedakan 6 dan 6.0).
        $riwayat = $ditutup->riwayat()->where('aksi', 'ditutup')->first();
        $this->assertSame(6.0, (float) $riwayat->perubahan['kekurangan'][0]['kurang']);
    }

    public function test_penutupan_wajib_beralasan(): void
    {
        $po = $this->buatPo(qty: 10);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/wajib disertai alasan/');

        $this->po->tutup($po, '   ', $this->user);
    }

    public function test_po_yang_belum_ada_barang_masuk_bisa_dibatalkan(): void
    {
        $po = $this->buatPo(qty: 10);

        $batal = $this->po->batalkan($po, 'Salah supplier', $this->user);

        $this->assertSame('batal', $batal->status);
    }

    /**
     * Membatalkan PO yang barangnya sudah sebagian datang akan membuat
     * penerimaan itu yatim. Jalurnya harus "tutup", bukan "batal".
     */
    public function test_po_yang_sudah_ada_barang_masuk_tidak_bisa_dibatalkan(): void
    {
        $po = $this->buatPo(qty: 10);
        $this->terimaSebagian($po, 3);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Gunakan tutup PO/');

        $this->po->batalkan($po, 'Berubah pikiran', $this->user);
    }

    public function test_qty_po_bisa_ditambah(): void
    {
        $po = $this->buatPo(qty: 10);

        $this->po->revisi($po, [
            ['barang_id' => $this->barang->id, 'qty' => 15, 'harga_satuan' => 85_000],
        ], 'Kebutuhan naik', $this->user);

        $this->assertSame(15.0, (float) $po->items()->first()->qty);
        $this->assertSame('revisi', $po->riwayat()->latest('id')->first()->aksi);
    }

    public function test_qty_po_bisa_dikurangi_selama_di_atas_yang_sudah_diterima(): void
    {
        $po = $this->buatPo(qty: 10);
        $this->terimaSebagian($po, 4);

        $this->po->revisi($po, [
            ['barang_id' => $this->barang->id, 'qty' => 6, 'harga_satuan' => 85_000],
        ], 'Supplier cuma sanggup 6', $this->user);

        $this->assertSame(6.0, (float) $po->items()->first()->qty);
    }

    /**
     * Barangnya sudah jadi stok. PO yang menyatakan lebih sedikit dari yang
     * sudah datang itu bohong, jadi harus ditolak.
     */
    public function test_qty_po_tidak_bisa_diturunkan_di_bawah_yang_sudah_diterima(): void
    {
        $po = $this->buatPo(qty: 10);
        $this->terimaSebagian($po, 7);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/sudah diterima/');

        $this->po->revisi($po, [
            ['barang_id' => $this->barang->id, 'qty' => 5, 'harga_satuan' => 85_000],
        ], 'Coba turunkan', $this->user);
    }

    public function test_po_yang_sudah_ditutup_tidak_bisa_direvisi(): void
    {
        $po = $this->buatPo(qty: 10);
        $this->po->tutup($po, 'Barang kosong', $this->user);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/tidak bisa direvisi/');

        $this->po->revisi($po->fresh(), [
            ['barang_id' => $this->barang->id, 'qty' => 12, 'harga_satuan' => 85_000],
        ], 'Coba revisi', $this->user);
    }

    public function test_penerimaan_menaikkan_qty_diterima_di_po(): void
    {
        $po = $this->buatPo(qty: 10);

        $this->terimaSebagian($po, 3);
        $this->terimaSebagian($po, 2);

        $this->assertSame(5.0, (float) $po->items()->first()->fresh()->qty_diterima);
        $this->assertSame(5.0, $this->barang->stok());
    }

    // ── Pembantu ────────────────────────────────────────────────────

    private function buatPo(float $qty): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'nomor' => 'SCK-OVK-P-II-26-001',
            'tanggal' => '2026-02-01',
            'supplier_id' => $this->supplier->id,
            'status' => 'terbuka',
            'dibuat_oleh' => $this->user->id,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'barang_id' => $this->barang->id,
            'qty' => $qty,
            'harga_satuan' => 85_000,
        ]);

        return $po;
    }

    private function terimaSebagian(PurchaseOrder $po, float $qty): Penerimaan
    {
        $penerimaan = Penerimaan::create([
            'nomor' => 'SCK-OVK-M-II-26-'.str_pad((string) rand(1, 999), 3, '0', STR_PAD_LEFT),
            'tanggal' => '2026-02-05',
            'supplier_id' => $this->supplier->id,
            'purchase_order_id' => $po->id,
            'dibuat_oleh' => $this->user->id,
        ]);

        return $this->stok->catatPenerimaan($penerimaan, [[
            'barang_id' => $this->barang->id,
            'qty' => $qty,
            'harga_satuan' => 85_000,
            'purchase_order_item_id' => $po->items()->first()->id,
        ]], $this->user);
    }
}
