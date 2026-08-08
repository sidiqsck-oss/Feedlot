<?php

namespace Tests\Feature;

use App\Models\Claim;
use App\Models\Induksi;
use App\Models\PembelianShipment;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Claim ke importir.
 *
 * Yang paling penting diuji di sini bukan CRUD-nya, melainkan bahwa sapi yang
 * mati SEBELUM induksi tetap bisa dicatat — kasus itu yang paling sering
 * terjadi, dan sapinya tidak punya baris induksi sama sekali.
 */
class ClaimTest extends TestCase
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

        PembelianShipment::create([
            'shipment_id' => $this->shipment->id,
            'jenis' => 'Steer',
            'jumlah_ekor' => 120,
            'tanggal_tiba' => '2026-01-08',
        ]);
    }

    public function test_halaman_claim_terbuka(): void
    {
        $this->get(route('cpl.claim.index'))->assertOk();
        $this->get(route('cpl.claim.create'))->assertOk();
    }

    public function test_sapi_mati_sebelum_induksi_tetap_bisa_dicatat(): void
    {
        $this->post(route('cpl.claim.store'), [
            'shipment_id' => $this->shipment->id,
            'rfid' => '982000000000999',
            'tanggal_kejadian' => '2026-01-11',
            'jenis_claim' => 'mati',
            'fase' => 'sebelum_induksi',
            'diagnosa' => 'Pneumonia',
            'status_klaim' => 'diajukan',
        ])->assertRedirect();

        $claim = Claim::firstOrFail();

        $this->assertNull($claim->induksi_id);
        $this->assertSame('sebelum_induksi', $claim->fase);
        $this->assertSame('Pneumonia', $claim->diagnosa);
    }

    /** RFID yang cocok disambungkan sendiri — form tidak pernah mengirim induksi_id. */
    public function test_rfid_yang_cocok_menyambung_ke_induksi(): void
    {
        $induksi = Induksi::create([
            'shipment_id' => $this->shipment->id,
            'rfid' => '982000000000001',
            'ear_tag' => '4250',
            'tanggal_induksi' => '2026-01-10',
            'berat_induksi' => 300,
        ]);

        $this->post(route('cpl.claim.store'), [
            'shipment_id' => $this->shipment->id,
            'rfid' => '982000000000001',
            'tanggal_kejadian' => '2026-02-01',
            'jenis_claim' => 'salvage',
            'fase' => 'sesudah_induksi',
            'berat' => 280,
            'nilai_klaim' => 7_500_000,
            'status_klaim' => 'diajukan',
        ]);

        $claim = Claim::firstOrFail();

        $this->assertSame($induksi->id, $claim->induksi_id);
        // Ear tag ikut terisi dari induksi walau tidak diketik.
        $this->assertSame('4250', $claim->ear_tag);
        $this->assertSame('sesudah_induksi', $claim->fase);
    }

    /** Mengaku sesudah induksi tanpa baris induksi itu tidak mungkin benar. */
    public function test_fase_dikoreksi_kalau_induksinya_tidak_ada(): void
    {
        $this->post(route('cpl.claim.store'), [
            'shipment_id' => $this->shipment->id,
            'rfid' => '982000000000777',
            'tanggal_kejadian' => '2026-01-11',
            'jenis_claim' => 'mati',
            'fase' => 'sesudah_induksi',
            'status_klaim' => 'diajukan',
        ]);

        $this->assertSame('sebelum_induksi', Claim::firstOrFail()->fase);
    }

    public function test_umur_dihitung_dari_tanggal_tiba_feedlot(): void
    {
        $claim = $this->buat(['tanggal_kejadian' => '2026-01-11']);

        // Tiba 8 Januari, mati 11 Januari.
        $this->assertSame(3, $claim->umurHari());

        $this->get(route('cpl.claim.index'))->assertSee('3 hari');
    }

    public function test_pencarian_rfid_mengembalikan_data_induksi(): void
    {
        Induksi::create([
            'shipment_id' => $this->shipment->id,
            'rfid' => '982000000000001',
            'ear_tag' => '4250',
            'tanggal_induksi' => '2026-01-10',
            'berat_induksi' => 300,
        ]);

        $this->getJson(route('cpl.claim.cari', [
            'shipment_id' => $this->shipment->id, 'rfid' => '982000000000001',
        ]))->assertOk()->assertJson([
            'ketemu' => true,
            'ear_tag' => '4250',
            'berat_induksi' => 300,
        ]);

        $this->getJson(route('cpl.claim.cari', [
            'shipment_id' => $this->shipment->id, 'rfid' => '982999999999999',
        ]))->assertOk()->assertJson(['ketemu' => false]);
    }

    public function test_penyaring_mempersempit_rekap_dan_tabel(): void
    {
        $this->buat(['jenis_claim' => 'mati', 'fase' => 'sebelum_induksi', 'diagnosa' => 'Pneumonia']);
        $this->buat(['jenis_claim' => 'salvage', 'fase' => 'sebelum_induksi', 'diagnosa' => 'Patah kaki']);

        $this->get(route('cpl.claim.index', ['jenis_claim' => 'mati']))
            ->assertSee('Pneumonia')
            ->assertDontSee('Patah kaki');
    }

    public function test_unduhan_ikut_penyaring(): void
    {
        $this->buat(['jenis_claim' => 'mati', 'diagnosa' => 'Pneumonia']);
        $this->buat(['jenis_claim' => 'salvage', 'diagnosa' => 'Patah kaki']);

        $isi = $this->get(route('cpl.claim.unduh', ['jenis_claim' => 'salvage']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Patah kaki', $isi);
        $this->assertStringNotContainsString('Pneumonia', $isi);
    }

    public function test_unduhan_kosong_tidak_mengirim_berkas(): void
    {
        $this->get(route('cpl.claim.unduh'))
            ->assertRedirect()
            ->assertSessionHas('gagal');
    }

    public function test_claim_bisa_diubah_dan_dihapus(): void
    {
        $claim = $this->buat(['status_klaim' => 'diajukan']);

        $this->put(route('cpl.claim.update', $claim), [
            'shipment_id' => $this->shipment->id,
            'tanggal_kejadian' => '2026-01-11',
            'jenis_claim' => 'mati',
            'fase' => 'sebelum_induksi',
            'status_klaim' => 'disetujui',
            'nilai_klaim' => 9_000_000,
        ])->assertRedirect();

        $this->assertSame('disetujui', $claim->fresh()->status_klaim);

        $this->delete(route('cpl.claim.destroy', $claim))->assertRedirect();
        $this->assertSame(0, Claim::count());
    }

    private function buat(array $ubah = []): Claim
    {
        return Claim::create($ubah + [
            'shipment_id' => $this->shipment->id,
            'tanggal_kejadian' => '2026-01-11',
            'jenis_claim' => 'mati',
            'fase' => 'sebelum_induksi',
            'status_klaim' => 'diajukan',
            'dibuat_oleh' => auth()->id(),
        ]);
    }
}
