<?php

namespace Tests\Feature;

use App\Models\Induksi;
use App\Models\Penjualan;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Form penjualan.
 *
 * Jalur utamanya tetap impor sheet Transaksi dari Dropbox; form ini pelengkap
 * untuk koreksi satu ekor dan nota yang belum masuk berkas. Yang diuji di sini
 * terutama hal-hal yang tidak dijaga impor: satu kepala nota dipakai banyak
 * ekor, dan total dihitung sendiri.
 */
class PenjualanFormTest extends TestCase
{
    use RefreshDatabase;

    private Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name' => 'Sidiq', 'email' => 'sidiq@example.test',
            'password' => 'rahasia', 'peran' => 'admin', 'aktif' => true,
        ]));

        $this->shipment = Shipment::create(['kode' => 'SCK90', 'nomor' => 90]);
    }

    public function test_halaman_penjualan_terbuka(): void
    {
        $this->get(route('cpl.penjualan.index'))->assertOk();
        $this->get(route('cpl.penjualan.create'))->assertOk();
    }

    public function test_satu_nota_menyimpan_banyak_ekor(): void
    {
        $a = $this->sapi('982000000000001', '4250');
        $b = $this->sapi('982000000000002', '4251');

        $this->post(route('cpl.penjualan.store'), $this->nota([
            ['shipment_id' => $this->shipment->id, 'rfid' => '982000000000001', 'berat' => 520.5],
            ['shipment_id' => $this->shipment->id, 'rfid' => '982000000000002', 'berat' => 498],
        ]))->assertRedirect();

        $this->assertSame(2, Penjualan::count());

        $satu = Penjualan::where('induksi_id', $a->id)->firstOrFail();
        $dua = Penjualan::where('induksi_id', $b->id)->firstOrFail();

        // Kepala nota diulang ke tiap baris.
        $this->assertSame('0091/INV-SCK/VI/26', $satu->no_invoice);
        $this->assertSame('0091/INV-SCK/VI/26', $dua->no_invoice);
        $this->assertSame('PT Berkah Daging', $dua->customer);

        // Total dihitung, tidak diketik: 520,5 x 52.000 = 27.066.000.
        $this->assertSame(27_066_000.0, (float) $satu->total);
        $this->assertSame(25_896_000.0, (float) $dua->total);
    }

    public function test_rfid_yang_tidak_ada_di_induksi_ditolak(): void
    {
        $this->sapi('982000000000001', '4250');

        $this->post(route('cpl.penjualan.store'), $this->nota([
            ['shipment_id' => $this->shipment->id, 'rfid' => '982000000000001', 'berat' => 520],
            ['shipment_id' => $this->shipment->id, 'rfid' => '982999999999999', 'berat' => 500],
        ]))->assertSessionHasErrors('items.1.rfid');

        // Seluruh nota gagal, bukan sebagian masuk.
        $this->assertSame(0, Penjualan::count());
    }

    public function test_ekor_kembar_dalam_satu_nota_ditolak(): void
    {
        $this->sapi('982000000000001', '4250');

        $this->post(route('cpl.penjualan.store'), $this->nota([
            ['shipment_id' => $this->shipment->id, 'rfid' => '982000000000001', 'berat' => 520],
            ['shipment_id' => $this->shipment->id, 'rfid' => '982000000000001', 'berat' => 505],
        ]))->assertSessionHasErrors(['items.1.rfid' => 'RFID kembar dengan baris 1.']);

        $this->assertSame(0, Penjualan::count());
    }

    public function test_pencarian_rfid_memberi_tahu_kalau_sudah_terjual(): void
    {
        $sapi = $this->sapi('982000000000001', '4250');

        $this->getJson(route('cpl.penjualan.cari', [
            'shipment_id' => $this->shipment->id, 'rfid' => '982000000000001',
        ]))->assertOk()->assertJson(['ketemu' => true, 'ear_tag' => '4250', 'sudah_terjual' => null]);

        Penjualan::create([
            'induksi_id' => $sapi->id, 'tanggal' => '2026-06-01',
            'berat' => 500, 'customer' => 'PT Lama',
        ]);

        $this->getJson(route('cpl.penjualan.cari', [
            'shipment_id' => $this->shipment->id, 'rfid' => '982000000000001',
        ]))->assertOk()->assertJson(['sudah_terjual' => '2026-06-01']);
    }

    public function test_ubah_menghitung_ulang_total(): void
    {
        $sapi = $this->sapi('982000000000001', '4250');

        $jual = Penjualan::create([
            'induksi_id' => $sapi->id, 'tanggal' => '2026-06-01',
            'berat' => 500, 'harga_per_kg' => 52000, 'total' => 26_000_000,
            'customer' => 'PT Berkah Daging',
        ]);

        $this->put(route('cpl.penjualan.update', $jual), [
            'tanggal' => '2026-06-01',
            'customer' => 'PT Berkah Daging',
            'berat' => 510,
            'harga_per_kg' => 52000,
        ])->assertRedirect();

        $this->assertSame(26_520_000.0, (float) $jual->fresh()->total);
    }

    public function test_ringkasan_memakai_harga_rata_tertimbang(): void
    {
        $a = $this->sapi('982000000000001', '4250');
        $b = $this->sapi('982000000000002', '4251');

        // 100 kg @ 50.000 dan 900 kg @ 60.000 → rata tertimbang 59.000/kg,
        // bukan 55.000 kalau kolom harganya yang dirata-rata.
        Penjualan::create(['induksi_id' => $a->id, 'tanggal' => '2026-06-01',
            'berat' => 100, 'harga_per_kg' => 50000, 'total' => 5_000_000]);
        Penjualan::create(['induksi_id' => $b->id, 'tanggal' => '2026-06-01',
            'berat' => 900, 'harga_per_kg' => 60000, 'total' => 54_000_000]);

        $this->get(route('cpl.penjualan.index'))->assertSee('Rp 59.000');
    }

    public function test_unduhan_ikut_penyaring(): void
    {
        $a = $this->sapi('982000000000001', '4250');
        $b = $this->sapi('982000000000002', '4251');

        Penjualan::create(['induksi_id' => $a->id, 'tanggal' => '2026-06-01',
            'berat' => 500, 'customer' => 'PT Satu']);
        Penjualan::create(['induksi_id' => $b->id, 'tanggal' => '2026-06-01',
            'berat' => 500, 'customer' => 'PT Dua']);

        $isi = $this->get(route('cpl.penjualan.unduh', ['customer' => 'PT Dua']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('PT Dua', $isi);
        $this->assertStringNotContainsString('PT Satu', $isi);
    }

    public function test_baris_penjualan_bisa_dihapus(): void
    {
        $sapi = $this->sapi('982000000000001', '4250');

        $jual = Penjualan::create([
            'induksi_id' => $sapi->id, 'tanggal' => '2026-06-01', 'berat' => 500,
        ]);

        $this->delete(route('cpl.penjualan.destroy', $jual))->assertRedirect();
        $this->assertSame(0, Penjualan::count());
    }

    // ── Pembantu ────────────────────────────────────────────────────

    private function sapi(string $rfid, string $earTag): Induksi
    {
        return Induksi::create([
            'shipment_id' => $this->shipment->id,
            'rfid' => $rfid,
            'ear_tag' => $earTag,
            'tanggal_induksi' => '2026-01-10',
            'berat_induksi' => 300,
            'jenis' => 'Steer',
        ]);
    }

    private function nota(array $items): array
    {
        return [
            'tanggal' => '2026-06-01',
            'no_invoice' => '0091/INV-SCK/VI/26',
            'no_surat_jalan' => 'SJ-0091/26',
            'customer' => 'PT Berkah Daging',
            'kode_customer' => 'C-014',
            'nama_barang' => 'Sapi Bakalan',
            'satuan' => 'Kg',
            'harga_per_kg' => 52000,
            'status_sapi' => 'Sehat',
            'items' => $items,
        ];
    }
}
