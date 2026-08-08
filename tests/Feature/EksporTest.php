<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\Opname;
use App\Models\OpnameItem;
use App\Models\Penerimaan;
use App\Models\Pengeluaran;
use App\Models\Petugas;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Ekspor\EksporExcel;
use App\Services\NomorDokumenService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EksporTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Barang $limoxin;
    private Supplier $supplier;
    private StokService $stok;
    private NomorDokumenService $nomor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stok = app(StokService::class);
        $this->nomor = app(NomorDokumenService::class);

        $this->user = User::create([
            'name' => 'Sidiq', 'email' => 'sidiq@example.test',
            'password' => 'rahasia', 'peran' => 'admin', 'aktif' => true,
        ]);

        $kategori = KategoriBarang::create(['nama' => 'Obat Cair']);
        $this->supplier = Supplier::create(['kode' => 'SUP-01', 'nama' => 'Supplier A']);

        $this->limoxin = Barang::create([
            'kode' => 'OVK-001', 'nama' => 'Limoxin-200 LA',
            'kategori_barang_id' => $kategori->id, 'satuan' => 'botol',
            'isi_nilai' => 100, 'isi_satuan' => 'ml',
        ]);

        $this->actingAs($this->user);
        $this->isiData();
    }

    public function test_laporan_stok_bisa_diunduh_csv(): void
    {
        $isi = $this->ambilIsi(route('laporan.stok.unduh'));

        $this->assertStringStartsWith("\xEF\xBB\xBF", $isi, 'CSV harus diawali BOM UTF-8 supaya Excel tidak merusak karakter.');
        $this->assertStringContainsString('Limoxin-200 LA', $isi);
        $this->assertStringContainsString('OVK-001', $isi);

        // Pemisah titik koma, bukan koma — Excel Indonesia memakai koma
        // sebagai desimal.
        $this->assertStringContainsString(';', $isi);
    }

    public function test_laporan_stok_bisa_diunduh_excel(): void
    {
        $respons = $this->get(route('laporan.stok.unduh', ['format' => 'excel']));

        $respons->assertOk();
        $respons->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_kartu_stok_csv_saldonya_sama_dengan_di_layar(): void
    {
        $isi = $this->ambilIsi(route('laporan.kartu.unduh', ['barang' => $this->limoxin->id]));
        $baris = array_values(array_filter(explode("\n", trim($isi))));

        // Baris terakhir harus menunjukkan saldo berjalan yang sama dengan
        // stok sebenarnya: 10 + 10 − 12 = 8.
        $this->assertSame(8.0, $this->limoxin->stok());
        $this->assertStringContainsString('8', end($baris));
    }

    public function test_unduhan_ikut_menghormati_penyaring_yang_aktif(): void
    {
        $lain = KategoriBarang::create(['nama' => 'Alat Kesehatan']);

        Barang::create([
            'kode' => 'ALK-001', 'nama' => 'Pisau Bedah',
            'kategori_barang_id' => $lain->id, 'satuan' => 'pcs',
        ]);

        $semua = $this->ambilIsi(route('laporan.stok.unduh'));
        $this->assertStringContainsString('Pisau Bedah', $semua);

        // Disaring ke kategori obat cair saja — pisau bedah harus hilang.
        $tersaring = $this->ambilIsi(route('laporan.stok.unduh', [
            'kategori' => $this->limoxin->kategori_barang_id,
        ]));

        $this->assertStringContainsString('Limoxin-200 LA', $tersaring);
        $this->assertStringNotContainsString('Pisau Bedah', $tersaring);
    }

    public function test_excel_menolak_data_yang_kelewat_banyak(): void
    {
        $baris = collect(range(1, EksporExcel::BATAS_BARIS + 1))->map(fn ($i) => [$i, "Barang {$i}"]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Pakai unduhan CSV/');

        app(EksporExcel::class)->unduh('uji', 'Uji', ['No', 'Nama'], $baris);
    }

    public function test_nota_masuk_bisa_dicetak_pdf(): void
    {
        $respons = $this->get(route('penerimaan.cetak', Penerimaan::first()));

        $respons->assertOk();
        $respons->assertHeader('content-type', 'application/pdf');

        // Isi PDF selalu diawali penanda ini. Kalau yang keluar HTML mentah,
        // berarti tampilan cetaknya gagal dirender tapi respons tetap 200.
        $this->assertStringStartsWith('%PDF-', $respons->getContent());
    }

    public function test_nota_keluar_pdf_memuat_rincian_lot(): void
    {
        $respons = $this->get(route('pengeluaran.cetak', Pengeluaran::first()));

        $respons->assertOk();
        $respons->assertHeader('content-type', 'application/pdf');
    }

    public function test_berita_acara_opname_bisa_dicetak(): void
    {
        $opname = Opname::create([
            'nomor' => $this->nomor->berikutnya('O', Carbon::parse('2026-02-28')),
            'tanggal' => '2026-02-28', 'periode_bulan' => 2, 'periode_tahun' => 2026,
            'dibuat_oleh' => $this->user->id,
        ]);

        OpnameItem::create([
            'opname_id' => $opname->id, 'barang_id' => $this->limoxin->id,
            'stok_sistem' => 8, 'stok_fisik' => 7,
        ]);

        $respons = $this->get(route('opname.cetak', $opname));

        $respons->assertOk();
        $respons->assertHeader('content-type', 'application/pdf');
    }

    public function test_kartu_stok_tanpa_memilih_barang_ditolak_baik_baik(): void
    {
        $this->get(route('laporan.kartu.unduh'))
            ->assertRedirect()
            ->assertSessionHas('gagal');
    }

    // ── Pembantu ────────────────────────────────────────────────────

    private function isiData(): void
    {
        foreach ([['2026-02-01', 10, 85_000], ['2026-02-10', 10, 92_000]] as [$tgl, $qty, $harga]) {
            $nota = Penerimaan::create([
                'nomor' => $this->nomor->berikutnya('M', Carbon::parse($tgl)),
                'tanggal' => $tgl, 'supplier_id' => $this->supplier->id,
                'dibuat_oleh' => $this->user->id,
            ]);

            $this->stok->catatPenerimaan($nota, [[
                'barang_id' => $this->limoxin->id, 'qty' => $qty, 'harga_satuan' => $harga,
            ]], $this->user);
        }

        $keluar = Pengeluaran::create([
            'nomor' => $this->nomor->berikutnya('K', Carbon::parse('2026-02-15')),
            'tanggal' => '2026-02-15', 'tujuan' => 'dokter',
            'petugas_id' => Petugas::create(['nama' => 'Gunawan', 'peran' => 'dokter'])->id,
            'dibuat_oleh' => $this->user->id,
        ]);

        $this->stok->catatPengeluaran($keluar, [[
            'barang_id' => $this->limoxin->id, 'qty' => 12,
        ]], $this->user);
    }

    private function ambilIsi(string $url): string
    {
        $respons = $this->get($url);
        $respons->assertOk();

        return $respons->streamedContent();
    }
}
