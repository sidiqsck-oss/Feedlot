<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\Induksi;
use App\Models\PembelianShipment;
use App\Models\Penjualan;
use App\Models\Property;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Impor\TemplatImpor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Impor property, pembelian per shipment, dan penjualan.
 *
 * Judul kolomnya memakai nama asli dari berkas yang dipakai sekarang
 * (PIC NT, Cattle Performance Log, dan sheet Transaksi di SJ INV), supaya
 * berkas yang sudah ada bisa langsung diunggah tanpa diketik ulang.
 */
class ImporCplTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name' => 'Sidiq', 'email' => 'sidiq@example.test',
            'password' => 'rahasia', 'peran' => 'admin', 'aktif' => true,
        ]));
    }

    public function test_templat_semua_jenis_bisa_diunduh(): void
    {
        foreach (TemplatImpor::semuaJenis() as $jenis) {
            $this->get(route('impor.templat', $jenis))->assertOk();
        }

        $this->assertCount(5, TemplatImpor::semuaJenis());
    }

    public function test_property_diimpor_dengan_region_dan_lga(): void
    {
        $batch = $this->unggah(TemplatImpor::PROPERTY, null,
            ['PIC', 'Property Name / Holding', 'Region', 'Local Government Area (LGA)'],
            [
                ['QABC123', 'Brighton Downs', 'Northern Territory', 'Barkly'],
                ['QDEF456', 'Carlton Hill', 'Western Australia', 'Wyndham-East Kimberley'],
            ],
        );

        $this->post(route('impor.proses', $batch))->assertRedirect();

        $this->assertSame(2, Property::count());

        $p = Property::where('kode', 'QABC123')->firstOrFail();
        $this->assertSame('Brighton Downs', $p->nama);
        $this->assertSame('Barkly', $p->lga);
    }

    /** Property itu master: unggah ulang memperbarui, bukan menolak. */
    public function test_property_diunggah_ulang_memperbarui(): void
    {
        $b1 = $this->unggah(TemplatImpor::PROPERTY, null,
            ['PIC', 'Property Name / Holding'],
            [['QABC123', 'Nama Lama']],
        );
        $this->post(route('impor.proses', $b1));

        $b2 = $this->unggah(TemplatImpor::PROPERTY, null,
            ['PIC', 'Property Name / Holding', 'Region'],
            [['QABC123', 'Nama Baru', 'NT']],
        );
        $this->post(route('impor.proses', $b2));

        $this->assertSame(1, Property::count());
        $this->assertSame('Nama Baru', Property::first()->nama);
        $this->assertSame(1, $b2->fresh()->jumlah_diperbarui);
    }

    public function test_pembelian_membuat_shipment_yang_belum_ada(): void
    {
        $batch = $this->unggah(TemplatImpor::PEMBELIAN, null,
            ['Ship', 'Jenis', 'Total Jenis', 'Tanggal', 'Shipping', 'Tanggal2', 'Feedlot (Kg)',
                'Importir', 'Salvage (Jumlah)', 'Mati', 'Harga (USD)', 'Daerah'],
            [
                ['90', 'Steer', '120', '2026-01-05', '333.4', '2026-01-08', '326.0',
                    'PT Importir', '3', '2', '3.15', 'Barkly'],
            ],
        );

        $this->post(route('impor.proses', $batch))->assertRedirect();

        // Angka "90" dinormalkan jadi SCK90, dan shipment-nya dibuat otomatis.
        $shipment = Shipment::where('kode', 'SCK90')->firstOrFail();

        $p = PembelianShipment::firstOrFail();
        $this->assertSame($shipment->id, $p->shipment_id);
        $this->assertSame('Steer', $p->jenis);
        $this->assertSame(120, $p->jumlah_ekor);
        $this->assertSame(333.4, (float) $p->berat_muat);
        $this->assertSame('2026-01-05', $p->tanggal_muat->toDateString());
        $this->assertSame(326.0, (float) $p->berat_tiba);
        $this->assertSame(3, $p->salvage_jumlah);
        $this->assertSame(2, $p->mati_jumlah);
        $this->assertSame('Barkly', $p->daerah);
    }

    public function test_penjualan_menempel_ke_sapi_lewat_rfid_dan_shipment(): void
    {
        $shipment = Shipment::create(['kode' => 'SCK90', 'nomor' => 90]);

        $induksi = Induksi::create([
            'shipment_id' => $shipment->id,
            'rfid' => '982000000000001',
            'ear_tag' => '4250',
            'tanggal_induksi' => '2026-01-10',
            'berat_induksi' => 300,
            'jenis' => 'Steer',
        ]);

        $batch = $this->unggah(TemplatImpor::PENJUALAN, null,
            ['Nomor RFID', 'Ship', 'Tanggal', 'Jumlah Berat', 'Cust', 'Kode Cust',
                'No Invoice', 'No Surat Jalan', 'Harga', 'Realisasi', 'Total', 'Potongan', 'Status Sapi'],
            [
                ['982000000000001', '90', '2026-06-01', '520.5', 'PT Berkah Daging', 'C-014',
                    '0091/INV-SCK/VI/26', 'SJ-0091/26', '52000', '27060000', '27066000', '0', 'Sehat'],
            ],
        );

        $this->post(route('impor.proses', $batch))->assertRedirect();

        $jual = Penjualan::firstOrFail();
        $this->assertSame($induksi->id, $jual->induksi_id);
        $this->assertSame(520.5, (float) $jual->berat);
        $this->assertSame('PT Berkah Daging', $jual->customer);
        $this->assertSame('SJ-0091/26', $jual->no_surat_jalan);
        $this->assertSame(27_066_000.0, (float) $jual->total);
    }

    public function test_penjualan_untuk_sapi_yang_belum_diinduksi_ditolak(): void
    {
        Shipment::create(['kode' => 'SCK90', 'nomor' => 90]);

        $batch = $this->unggah(TemplatImpor::PENJUALAN, null,
            ['Nomor RFID', 'Ship', 'Tanggal', 'Jumlah Berat'],
            [['982999999999999', '90', '2026-06-01', '520']],
        );

        $this->assertSame(1, $batch->jumlah_bermasalah);
        $this->assertStringContainsString(
            'Unggah berkas induksinya dulu',
            $batch->baris()->bermasalah()->first()->masalah[0],
        );

        $this->assertSame(0, Penjualan::count());
    }

    public function test_jenis_lintas_shipment_tidak_meminta_shipment(): void
    {
        foreach ([TemplatImpor::PROPERTY, TemplatImpor::PEMBELIAN, TemplatImpor::PENJUALAN] as $jenis) {
            $this->assertFalse(TemplatImpor::perShipment($jenis), "{$jenis} seharusnya lintas shipment");
        }

        foreach ([TemplatImpor::INDUKSI, TemplatImpor::REWEIGHT] as $jenis) {
            $this->assertTrue(TemplatImpor::perShipment($jenis), "{$jenis} seharusnya per shipment");
        }
    }

    public function test_induksi_tetap_wajib_menyebut_shipment(): void
    {
        $this->post(route('impor.store'), [
            'jenis' => TemplatImpor::INDUKSI,
            'berkas' => $this->berkas(['RFID', 'EAR TAG'], [['982000000000001', '4250']], 'INDUKSI'),
        ])->assertSessionHasErrors('shipment_id');
    }

    public function test_kolom_wajib_yang_hilang_ditolak(): void
    {
        // Berkas property tanpa kolom nama.
        $this->post(route('impor.store'), [
            'jenis' => TemplatImpor::PROPERTY,
            'berkas' => $this->berkas(['PIC'], [['QABC123']]),
        ])->assertSessionHas('gagal', fn ($p) => str_contains($p, 'Property Name'));

        $this->assertSame(0, ImportBatch::count());
    }

    // ── Pembantu ────────────────────────────────────────────────────

    private function unggah(string $jenis, ?Shipment $shipment, array $judul, array $baris): ImportBatch
    {
        $sebelum = ImportBatch::max('id') ?? 0;

        $lembar = TemplatImpor::definisi($jenis)['lembar'];

        $this->post(route('impor.store'), array_filter([
            'jenis' => $jenis,
            'shipment_id' => $shipment?->id,
            'berkas' => $this->berkas($judul, $baris, $lembar),
        ]));

        return ImportBatch::where('id', '>', $sebelum)->firstOrFail();
    }

    private function berkas(array $judul, array $baris, ?string $lembar = null): UploadedFile
    {
        $buku = new Spreadsheet;
        $kertas = $buku->getActiveSheet();

        if ($lembar) {
            $kertas->setTitle($lembar);
        }

        $kertas->fromArray(array_merge([$judul], $baris), null, 'A1');

        $jalur = sys_get_temp_dir().'/'.uniqid('uji-cpl-', true).'.xlsx';
        (new Xlsx($buku))->save($jalur);
        $buku->disconnectWorksheets();

        return new UploadedFile($jalur, 'uji-'.md5(json_encode($baris)).'.xlsx', null, null, true);
    }
}
