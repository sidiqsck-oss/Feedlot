<?php

namespace Tests\Feature;

use App\Models\Induksi;
use App\Models\PembelianShipment;
use App\Models\Penjualan;
use App\Models\Property;
use App\Models\Reweight;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Cpl\AgregatCpl;
use App\Services\Cpl\KueriCpl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menguji dua aturan yang menentukan seluruh angka CPL:
 *
 *   1. Aturan populasi — pembilang dan penyebut harus dari ekor yang sama
 *   2. Campuran tertimbang dan rata-rata biasa, mengikuti laporan lama
 *
 * Kasus utamanya meniru persis kesalahan yang ditemukan di sistem lama: ada
 * sapi yang tidak punya data reweight, dan laporan lama tetap memasukkan berat
 * induksinya ke pembilang.
 */
class AgregatCplTest extends TestCase
{
    use RefreshDatabase;

    private Shipment $shipment;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Sidiq', 'email' => 'sidiq@example.test',
            'password' => 'rahasia', 'peran' => 'admin', 'aktif' => true,
        ]);

        $this->shipment = Shipment::create(['kode' => 'SCK90', 'nomor' => 90]);

        Property::create(['kode' => 'QABC', 'nama' => 'Brighton Downs']);

        PembelianShipment::create([
            'shipment_id' => $this->shipment->id,
            'jenis' => 'Steer',
            'tanggal_muat' => '2026-01-01',
            'berat_muat' => 300,
            'tanggal_tiba' => '2026-01-05',
            'berat_tiba' => 290,
            'jumlah_ekor' => 10,
        ]);
    }

    /**
     * Inti dari perbaikan.
     *
     * Dua ekor punya reweight, satu tidak. Cara lama memasukkan berat induksi
     * ketiganya ke pembilang tapi hanya DOF dua ekor ke penyebut — hasilnya
     * kacau. Cara benar membuang ekor ketiga dari ketiga penjumlahan.
     */
    public function test_sapi_tanpa_reweight_keluar_dari_seluruh_perhitungan_adg_rwt(): void
    {
        // Dua ekor di-reweight: 300 → 400 kg dalam 100 hari
        $this->sapi('A1', '1001', berat: 300, reweight: ['2026-04-11', 400]);
        $this->sapi('A2', '1002', berat: 300, reweight: ['2026-04-11', 400]);

        // Satu ekor TIDAK di-reweight, tapi berat induksinya sama
        $this->sapi('A3', '1003', berat: 300, reweight: null);

        $agregat = $this->agregat();
        $adgRwt = $agregat['adg_rwt'];

        // Benar: (400+400 − 300−300) ÷ (100+100) = 200 ÷ 200 = 1,0
        $this->assertSame(1.0, round($adgRwt['nilai'], 4));

        // Dasarnya 2 ekor, bukan 3 — dan itu ditampilkan.
        $this->assertSame(2, $adgRwt['n']);

        // Cara lama akan menghasilkan (800 − 900) ÷ 200 = −0,5.
        // Assertion ini menjaga supaya kesalahan itu tidak kembali.
        $this->assertNotEqualsWithDelta(-0.5, $adgRwt['nilai'], 0.001);
    }

    public function test_nilai_kosong_tidak_dihitung_sebagai_nol(): void
    {
        // ADG RWT dua ekor ini 1,0 dan 3,0 → rata-rata benar 2,0
        $this->sapi('B1', '2001', berat: 300, reweight: ['2026-04-11', 400]);  // 1,0
        $this->sapi('B2', '2002', berat: 300, reweight: ['2026-04-11', 600]);  // 3,0
        $this->sapi('B3', '2003', berat: 300, reweight: null);                  // kosong

        $agregat = $this->agregat();

        // Tertimbang: (400+600 − 600) ÷ 200 = 2,0
        $this->assertSame(2.0, round($agregat['adg_rwt']['nilai'], 4));
        $this->assertSame(2, $agregat['adg_rwt']['n']);

        // Kalau yang kosong dihitung nol, rata-ratanya jadi 1,333 — itu persis
        // yang membuat dashboard lama menampilkan 1,943 alih-alih 2,117.
        $this->assertNotEqualsWithDelta(1.333, $agregat['adg_rwt']['nilai'], 0.01);
    }

    public function test_adg_induction_tertimbang(): void
    {
        // Dua ekor dengan lama pemeliharaan berbeda. Tertimbang memberi bobot
        // lebih besar pada yang lebih lama, rata-rata biasa tidak.
        $this->sapi('C1', '3001', berat: 300, jual: ['2026-02-01', 331]);   // 31 hari, +31 → 1,0
        $this->sapi('C2', '3002', berat: 300, jual: ['2026-07-01', 600]);   // 181 hari, +300 → 1,657

        $agregat = $this->agregat();

        // Tertimbang: (331+600 − 600) ÷ (31+181) = 331 ÷ 212 = 1,5613
        $this->assertEqualsWithDelta(1.5613, $agregat['adg_induction']['nilai'], 0.001);

        // Rata-rata biasa akan menghasilkan 1,3286 — sengaja berbeda.
        $this->assertNotEqualsWithDelta(1.3286, $agregat['adg_induction']['nilai'], 0.001);
    }

    /**
     * ADG JUAL sengaja memakai rata-rata biasa, bukan tertimbang — mengikuti
     * laporan lama, dan pemiliknya menyatakan itu memang disengaja.
     */
    public function test_adg_jual_memakai_rata_rata_biasa(): void
    {
        $this->sapi('D1', '4001', berat: 300, reweight: ['2026-03-01', 400], jual: ['2026-03-31', 430]);
        $this->sapi('D2', '4002', berat: 300, reweight: ['2026-03-01', 400], jual: ['2026-08-28', 700]);

        $baris = $this->baris();

        // ADG JUAL per ekor: 30/30 = 1,0 dan 300/180 = 1,6667
        $perEkor = $baris->map(fn ($b) => round((float) $b->adg_jual, 4))->sort()->values();
        $this->assertSame(1.0, $perEkor[0]);
        $this->assertEqualsWithDelta(1.6667, $perEkor[1], 0.001);

        // Rata-rata biasa: (1,0 + 1,6667) ÷ 2 = 1,3333
        $agregat = $this->agregat();
        $this->assertEqualsWithDelta(1.3333, $agregat['adg_jual']['nilai'], 0.001);

        // Tertimbang akan menghasilkan 330 ÷ 210 = 1,5714.
        $this->assertNotEqualsWithDelta(1.5714, $agregat['adg_jual']['nilai'], 0.001);
    }

    public function test_melambat_pasca_reweight(): void
    {
        // Keduanya ADG RWT = (400−300) ÷ 100 hari = 1,0

        // Melambat: naik 15 kg dalam 30 hari → ADG jual 0,5 < 1,0
        $this->sapi('E1', '5001', berat: 300, reweight: ['2026-04-11', 400], jual: ['2026-05-11', 415]);

        // Membaik: naik 90 kg dalam 30 hari → ADG jual 3,0 > 1,0
        $this->sapi('E2', '5002', berat: 300, reweight: ['2026-04-11', 400], jual: ['2026-05-11', 490]);
        // Tanpa reweight — tidak ikut dihitung sama sekali
        $this->sapi('E3', '5003', berat: 300, jual: ['2026-05-11', 500]);

        $melambat = $this->agregat()['melambat'];

        $this->assertSame(1, $melambat['jumlah']);
        $this->assertSame(2, $melambat['n']);
        $this->assertSame(50.0, round($melambat['persen'], 2));
    }

    public function test_status_diturunkan_bukan_diketik(): void
    {
        $this->sapi('F1', '6001', berat: 300, jual: ['2026-05-11', 400]);
        $this->sapi('F2', '6002', berat: 300);

        $status = $this->baris()->pluck('status', 'rfid');

        $this->assertSame('terjual', $status['F1']);
        $this->assertSame('aktif', $status['F2']);
    }

    public function test_kelompok_per_shipment_menghitung_terpisah(): void
    {
        $lain = Shipment::create(['kode' => 'SCK91', 'nomor' => 91]);

        PembelianShipment::create([
            'shipment_id' => $lain->id, 'jenis' => 'Steer',
            'tanggal_muat' => '2026-01-01', 'berat_muat' => 300,
            'tanggal_tiba' => '2026-01-05', 'berat_tiba' => 290, 'jumlah_ekor' => 5,
        ]);

        $this->sapi('G1', '7001', berat: 300, reweight: ['2026-04-11', 400]);
        $this->sapi('G2', '7002', berat: 300, reweight: ['2026-04-11', 600], shipment: $lain);

        $kelompok = AgregatCpl::dari($this->baris())->kelompok('shipment');

        $this->assertSame(1.0, round($kelompok['SCK90']['adg_rwt']['nilai'], 4));
        $this->assertSame(3.0, round($kelompok['SCK91']['adg_rwt']['nilai'], 4));
    }

    public function test_semuanya_null_kalau_tidak_ada_data(): void
    {
        $this->sapi('H1', '8001', berat: 300);

        $agregat = $this->agregat();

        $this->assertNull($agregat['adg_rwt']['nilai']);
        $this->assertSame(0, $agregat['adg_rwt']['n']);
        $this->assertNull($agregat['adg_induction']['nilai']);
        $this->assertSame(1, $agregat['ekor']);
    }

    // ── Pembantu ────────────────────────────────────────────────────

    private function sapi(
        string $rfid,
        string $earTag,
        float $berat,
        ?array $reweight = null,
        ?array $jual = null,
        ?Shipment $shipment = null,
    ): Induksi {
        $induksi = Induksi::create([
            'shipment_id' => ($shipment ?? $this->shipment)->id,
            'rfid' => $rfid,
            'ear_tag' => $earTag,
            'tanggal_induksi' => '2026-01-01',
            'berat_induksi' => $berat,
            'jenis' => 'Steer',
            'kode_prop' => 'QABC',
        ]);

        if ($reweight) {
            Reweight::create([
                'induksi_id' => $induksi->id,
                'tanggal_reweight' => $reweight[0],
                'berat_reweight' => $reweight[1],
            ]);
        }

        if ($jual) {
            Penjualan::create([
                'induksi_id' => $induksi->id,
                'tanggal' => $jual[0],
                'berat' => $jual[1],
                'customer' => 'PT Uji',
                'no_invoice' => 'INV-001',
                'status_sapi' => 'Sehat',
            ]);
        }

        return $induksi;
    }

    private function baris()
    {
        return collect(app(KueriCpl::class)->lengkap()->get());
    }

    private function agregat(): array
    {
        return AgregatCpl::dari($this->baris())->semua();
    }
}
