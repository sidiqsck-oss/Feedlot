<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\Induksi;
use App\Models\Reweight;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Impor\TemplatImpor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImporTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Sidiq', 'email' => 'sidiq@example.test',
            'password' => 'rahasia', 'peran' => 'admin', 'aktif' => true,
        ]);

        $this->shipment = Shipment::create(['kode' => 'SCK90', 'nomor' => 90]);

        $this->actingAs($this->user);
    }

    public function test_templat_bisa_diunduh(): void
    {
        foreach (TemplatImpor::semuaJenis() as $jenis) {
            $this->get(route('impor.templat', $jenis))
                ->assertOk()
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
    }

    public function test_unggah_menghasilkan_pratinjau_tanpa_memasukkan_data(): void
    {
        $berkas = $this->berkasInduksi([
            ['982000000000001', '4250', '2026-02-15', '277', '610'],
            ['982000000000002', '4251', '2026-02-15', '281', '610'],
        ]);

        $this->post(route('impor.store'), [
            'jenis' => TemplatImpor::INDUKSI,
            'shipment_id' => $this->shipment->id,
            'berkas' => $berkas,
        ])->assertRedirect();

        $batch = ImportBatch::firstOrFail();

        $this->assertSame('pratinjau', $batch->status);
        $this->assertSame(2, $batch->jumlah_baris);
        $this->assertSame(2, $batch->jumlah_valid);

        // Inti dari pratinjau: belum ada satu baris pun yang masuk.
        $this->assertSame(0, Induksi::count());
    }

    public function test_proses_memasukkan_baris_valid(): void
    {
        $batch = $this->unggahInduksi([
            ['982000000000001', '4250', '2026-02-15', '277', '610'],
            ['982000000000002', '4251', '2026-02-15', '281', '610'],
        ]);

        $this->post(route('impor.proses', $batch))->assertRedirect();

        $this->assertSame(2, Induksi::count());
        $this->assertSame('selesai', $batch->fresh()->status);

        $sapi = Induksi::where('rfid', '982000000000001')->firstOrFail();
        $this->assertSame('4250', $sapi->ear_tag);
        $this->assertSame(277.0, (float) $sapi->berat_induksi);
        $this->assertSame('2026-02-15', $sapi->tanggal_induksi->toDateString());
    }

    public function test_rfid_kosong_dan_kembar_ditandai_per_baris(): void
    {
        $batch = $this->unggahInduksi([
            ['982000000000001', '4250', '2026-02-15', '277', '610'],
            ['', '4251', '2026-02-15', '281', '610'],                     // RFID kosong
            ['982000000000001', '4252', '2026-02-15', '290', '610'],      // RFID kembar
            ['982000000000003', '4250', '2026-02-15', '295', '610'],      // ear tag kembar
        ]);

        $this->assertSame(4, $batch->jumlah_baris);
        $this->assertSame(1, $batch->jumlah_valid);
        $this->assertSame(3, $batch->jumlah_bermasalah);

        $masalah = $batch->baris()->bermasalah()->get()
            ->flatMap(fn ($b) => $b->masalah)
            ->implode(' | ');

        $this->assertStringContainsString('RFID kosong', $masalah);
        $this->assertStringContainsString('Ear tag kembar', $masalah);

        // Nomor yang ditunjuk harus nomor baris di berkas asli. Baris pertama
        // data ada di baris 2 (baris 1 judul kolom), jadi kembarannya "baris 2"
        // — bukan "baris 1" seperti kalau yang dipakai urutan pembacaan.
        $this->assertStringContainsString('RFID kembar dengan baris 2', $masalah);

        // Baris bermasalah dilewati, baris sehat tetap masuk.
        $this->post(route('impor.proses', $batch));
        $this->assertSame(1, Induksi::count());
    }

    public function test_berkas_yang_sama_tidak_bisa_diunggah_dua_kali(): void
    {
        $baris = [['982000000000001', '4250', '2026-02-15', '277', '610']];

        $this->unggahInduksi($baris);

        $this->post(route('impor.store'), [
            'jenis' => TemplatImpor::INDUKSI,
            'shipment_id' => $this->shipment->id,
            'berkas' => $this->berkasInduksi($baris),
        ])->assertSessionHas('gagal', fn ($p) => str_contains($p, 'sudah pernah diunggah'));

        $this->assertSame(1, ImportBatch::count());
    }

    public function test_rfid_yang_sudah_ada_di_database_ditolak(): void
    {
        $batch = $this->unggahInduksi([['982000000000001', '4250', '2026-02-15', '277', '610']]);
        $this->post(route('impor.proses', $batch));

        // Berkas berbeda (ear tag lain) tapi RFID-nya sama.
        $batch2 = $this->unggahInduksi([['982000000000001', '9999', '2026-02-16', '280', '611']]);

        $this->assertSame(1, $batch2->jumlah_bermasalah);
        $this->assertStringContainsString(
            'sudah ada di data induksi',
            $batch2->baris()->bermasalah()->first()->masalah[0],
        );
    }

    public function test_kolom_wajib_yang_hilang_ditolak_sebelum_dibaca(): void
    {
        // Berkas tanpa kolom EAR TAG.
        $buku = new Spreadsheet;
        $lembar = $buku->getActiveSheet();
        $lembar->setTitle('INDUKSI');
        $lembar->fromArray([['RFID', 'TGL INDUKSI'], ['982000000000001', '2026-02-15']], null, 'A1');

        $this->post(route('impor.store'), [
            'jenis' => TemplatImpor::INDUKSI,
            'shipment_id' => $this->shipment->id,
            'berkas' => $this->keUploadedFile($buku, 'tanpa-eartag.xlsx'),
        ])->assertSessionHas('gagal', fn ($p) => str_contains($p, 'EAR TAG'));

        $this->assertSame(0, ImportBatch::count());
    }

    public function test_reweight_menolak_rfid_yang_belum_diinduksi(): void
    {
        $batch = $this->unggahReweight([['982000000000009', '2026-05-20', '341', '610', 'HP2']]);

        $this->assertSame(1, $batch->jumlah_bermasalah);
        $this->assertStringContainsString(
            'Unggah berkas induksinya dulu',
            $batch->baris()->bermasalah()->first()->masalah[0],
        );
    }

    public function test_reweight_menempel_ke_sapi_yang_benar(): void
    {
        $induksi = $this->unggahInduksi([['982000000000001', '4250', '2026-02-15', '277', '610']]);
        $this->post(route('impor.proses', $induksi));

        $rwt = $this->unggahReweight([['982000000000001', '2026-05-20', '341', '610', 'HP2']]);
        $this->post(route('impor.proses', $rwt));

        $this->assertSame(1, Reweight::count());

        $sapi = Induksi::firstOrFail();
        $this->assertSame(341.0, (float) $sapi->reweightTerakhir()->berat_reweight);

        // 341 − 277 = 64 kg pertambahan bobot.
        $this->assertSame(64.0, $sapi->pertambahanBobot());
    }

    public function test_reweight_di_tanggal_yang_sama_tidak_dobel(): void
    {
        $induksi = $this->unggahInduksi([['982000000000001', '4250', '2026-02-15', '277', '610']]);
        $this->post(route('impor.proses', $induksi));

        $baris = [['982000000000001', '2026-05-20', '341', '610', 'HP2']];

        $rwt1 = $this->unggahReweight($baris);
        $this->post(route('impor.proses', $rwt1));

        // Berkas berbeda isinya sedikit, supaya hash-nya beda dan lolos
        // penjagaan berkas kembar — yang diuji di sini penjagaan di tingkat data.
        $rwt2 = $this->unggahReweight([['982000000000001', '2026-05-20', '342', '610', 'HP2']]);

        $this->assertSame(1, $rwt2->jumlah_bermasalah);
        $this->assertStringContainsString('sudah punya data reweight', $rwt2->baris()->bermasalah()->first()->masalah[0]);
        $this->assertSame(1, Reweight::count());
    }

    public function test_ear_tag_sama_boleh_di_shipment_berbeda(): void
    {
        $lain = Shipment::create(['kode' => 'SCK91', 'nomor' => 91]);

        $b1 = $this->unggahInduksi([['982000000000001', '4250', '2026-02-15', '277', '610']]);
        $this->post(route('impor.proses', $b1));

        // Ear tag 4250 dipakai ulang di shipment lain — di data asli ada 40
        // kasus seperti ini, jadi harus diterima.
        $b2 = $this->unggahInduksi([['982000000000002', '4250', '2026-03-15', '280', '620']], $lain);
        $this->post(route('impor.proses', $b2));

        $this->assertSame(2, Induksi::count());
    }

    public function test_batch_besar_dilempar_ke_antrean(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $baris = collect(range(1, 105))->map(fn ($i) => [
            '98200000000'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            (string) (5000 + $i),
            '2026-02-15', '277', '610',
        ])->all();

        $batch = $this->unggahInduksi($baris);

        $this->assertSame(105, $batch->jumlah_valid);

        $this->post(route('impor.proses', $batch))
            ->assertSessionHas('sukses', fn ($p) => str_contains($p, 'latar belakang'));

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\ProsesImporBatch::class);
    }

    // ── Pembantu ────────────────────────────────────────────────────

    private function unggahInduksi(array $baris, ?Shipment $shipment = null): ImportBatch
    {
        $sebelum = ImportBatch::max('id') ?? 0;

        $this->post(route('impor.store'), [
            'jenis' => TemplatImpor::INDUKSI,
            'shipment_id' => ($shipment ?? $this->shipment)->id,
            'berkas' => $this->berkasInduksi($baris),
        ]);

        return ImportBatch::where('id', '>', $sebelum)->firstOrFail();
    }

    private function unggahReweight(array $baris): ImportBatch
    {
        $sebelum = ImportBatch::max('id') ?? 0;

        $this->post(route('impor.store'), [
            'jenis' => TemplatImpor::REWEIGHT,
            'shipment_id' => $this->shipment->id,
            'berkas' => $this->berkasReweight($baris),
        ]);

        return ImportBatch::where('id', '>', $sebelum)->firstOrFail();
    }

    private function berkasInduksi(array $baris): UploadedFile
    {
        $buku = new Spreadsheet;
        $lembar = $buku->getActiveSheet();
        $lembar->setTitle('INDUKSI');

        $isi = array_merge(
            [['RFID', 'EAR TAG', 'TGL INDUKSI', 'BRT INDCT', 'PEN']],
            $baris,
        );

        $lembar->fromArray($isi, null, 'A1');

        return $this->keUploadedFile($buku, 'induksi-'.md5(json_encode($baris)).'.xlsx');
    }

    private function berkasReweight(array $baris): UploadedFile
    {
        $buku = new Spreadsheet;
        $lembar = $buku->getActiveSheet();
        $lembar->setTitle('RWT');

        $isi = array_merge(
            [['RFID', 'TGL REWEIGHT', 'BRT RWT', 'PEN INDUKSI', 'PEN AKHIR']],
            $baris,
        );

        $lembar->fromArray($isi, null, 'A1');

        return $this->keUploadedFile($buku, 'reweight-'.md5(json_encode($baris)).'.xlsx');
    }

    private function keUploadedFile(Spreadsheet $buku, string $nama): UploadedFile
    {
        $jalur = sys_get_temp_dir().'/'.uniqid('uji-impor-', true).'.xlsx';

        (new Xlsx($buku))->save($jalur);
        $buku->disconnectWorksheets();

        return new UploadedFile($jalur, $nama, null, null, true);
    }
}
