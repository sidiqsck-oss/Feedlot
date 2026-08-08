<?php

namespace Tests\Feature;

use App\Models\Induksi;
use App\Models\PembelianShipment;
use App\Models\Penjualan;
use App\Models\Property;
use App\Models\Reweight;
use App\Models\Shipment;
use App\Models\User;
use App\Support\KolomCpl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanCplTest extends TestCase
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
        Property::create(['kode' => 'QABC', 'nama' => 'Brighton Downs']);

        PembelianShipment::create([
            'shipment_id' => $this->shipment->id, 'jenis' => 'Steer',
            'tanggal_muat' => '2026-01-01', 'berat_muat' => 300,
            'tanggal_tiba' => '2026-01-05', 'berat_tiba' => 290, 'jumlah_ekor' => 10,
        ]);

        // Dua customer, salah satu sapinya tanpa reweight
        $this->sapi('R1', '101', 'PT Alfa', reweight: true);
        $this->sapi('R2', '102', 'PT Alfa', reweight: true);
        $this->sapi('R3', '103', 'CV Beta', reweight: false);
    }

    public function test_semua_halaman_cpl_terbuka(): void
    {
        foreach (['cpl.dashboard', 'cpl.laporan', 'cpl.closing'] as $nama) {
            $this->get(route($nama))->assertOk();
        }
    }

    public function test_laporan_dipecah_per_customer(): void
    {
        $html = $this->get(route('cpl.laporan', ['tanggal' => '2026-06-01']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('PT Alfa', $html);
        $this->assertStringContainsString('CV Beta', $html);

        // Lebih dari satu customer, jadi ada tabel gabungannya.
        $this->assertStringContainsString('GABUNGAN SEMUA CUSTOMER', $html);
    }

    public function test_kolom_opsional_tersembunyi_secara_bawaan(): void
    {
        $html = $this->get(route('cpl.laporan', ['tanggal' => '2026-06-01']))->getContent();

        // Diperiksa di judul kolom tabelnya, bukan di seluruh halaman — nama
        // kolom opsional memang selalu muncul di daftar centang
        // "Personalisasi Kolom", dan itu justru harus begitu.
        $this->assertFalse($this->adaJudulKolom($html, 'SELISIH'));
        $this->assertFalse($this->adaJudulKolom($html, 'RWT Wt'));

        $this->assertTrue($this->adaJudulKolom($html, 'Exit Wt'));
        $this->assertTrue($this->adaJudulKolom($html, 'ADG'));
    }

    public function test_kolom_bisa_dimunculkan_dan_pilihannya_diingat(): void
    {
        // Hanya RFID yang disembunyikan, sisanya dimunculkan.
        $html = $this->get(route('cpl.laporan', [
            'tanggal' => '2026-06-01',
            'atur_kolom' => 1,
            'sembunyikan' => ['rfid'],
        ]))->assertOk()->getContent();

        $this->assertTrue($this->adaJudulKolom($html, 'SELISIH'));
        $this->assertFalse($this->adaJudulKolom($html, 'RFID'));

        // Dibuka lagi tanpa parameter — pilihannya harus tetap diingat.
        $lagi = $this->get(route('cpl.laporan', ['tanggal' => '2026-06-01']))
            ->assertOk()->getContent();

        $this->assertTrue($this->adaJudulKolom($lagi, 'SELISIH'));
        $this->assertFalse($this->adaJudulKolom($lagi, 'RFID'));
    }

    /** Apakah teks itu muncul sebagai judul kolom <th>, bukan di tempat lain. */
    private function adaJudulKolom(string $html, string $teks): bool
    {
        return (bool) preg_match(
            '/<th[^>]*>[^<]*'.preg_quote($teks, '/').'/i',
            $html,
        );
    }

    public function test_bawaan_menampilkan_sepuluh_invoice_terakhir(): void
    {
        $this->get(route('cpl.laporan'))
            ->assertOk()
            ->assertSee('10 invoice terakhir');
    }

    public function test_closing_tidak_memuat_baris_per_ekor(): void
    {
        $html = $this->get(route('cpl.closing', ['tanggal' => '2026-06-01']))
            ->assertOk()->getContent();

        // Ringkasannya ada, tapi nomor ear tag per ekor tidak.
        $this->assertStringContainsString('Per Customer', $html);
        $this->assertStringContainsString('Per Shipment', $html);
        $this->assertStringNotContainsString('>101<', $html);
    }

    public function test_closing_menampilkan_n_kalau_reweight_tidak_lengkap(): void
    {
        // CV Beta punya satu ekor tanpa reweight, jadi n-nya berbeda dari ekor.
        $html = $this->get(route('cpl.closing', ['tanggal' => '2026-06-01']))->getContent();

        $this->assertStringContainsString('n=0', $html);
    }

    public function test_unduh_csv_menghormati_kolom_yang_disembunyikan(): void
    {
        $respons = $this->get(route('cpl.laporan.unduh', [
            'tanggal' => '2026-06-01',
            'atur_kolom' => 1,
            'sembunyikan' => KolomCpl::bawaanDisembunyikan(),
        ]));

        $respons->assertOk();
        $isi = $respons->streamedContent();

        $this->assertStringContainsString('Exit Wt', $isi);
        $this->assertStringNotContainsString('SELISIH', $isi);
        $this->assertStringContainsString('SCK90', $isi);
    }

    public function test_unduh_pdf(): void
    {
        $this->get(route('cpl.laporan.unduh', ['tanggal' => '2026-06-01', 'format' => 'pdf']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function sapi(string $rfid, string $earTag, string $customer, bool $reweight): void
    {
        $induksi = Induksi::create([
            'shipment_id' => $this->shipment->id,
            'rfid' => $rfid,
            'ear_tag' => $earTag,
            'tanggal_induksi' => '2026-01-10',
            'berat_induksi' => 300,
            'jenis' => 'Steer',
            'kode_prop' => 'QABC',
            'frame' => 'M',
        ]);

        if ($reweight) {
            Reweight::create([
                'induksi_id' => $induksi->id,
                'tanggal_reweight' => '2026-04-20',
                'berat_reweight' => 400,
            ]);
        }

        Penjualan::create([
            'induksi_id' => $induksi->id,
            'tanggal' => '2026-06-01',
            'berat' => 520,
            'customer' => $customer,
            'no_invoice' => 'INV-'.$earTag,
            'status_sapi' => 'Sehat',
        ]);
    }
}
