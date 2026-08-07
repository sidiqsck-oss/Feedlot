<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\Opname;
use App\Models\OpnameItem;
use App\Models\Penerimaan;
use App\Models\Pengeluaran;
use App\Models\PergerakanStok;
use App\Models\Petugas;
use App\Models\Supplier;
use App\Models\User;
use App\Services\NomorDokumenService;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

/**
 * Menjalankan persis alur yang disetujui di docs/rancangan-database.md bagian 11.
 *
 * Angka-angka di sini bukan karangan test — semuanya sudah dicek ulang oleh
 * pemilik proses sebelum kode ini ditulis. Kalau salah satu assertion di bawah
 * gagal, artinya perilaku sistem menyimpang dari yang disepakati, bukan
 * testnya yang perlu disesuaikan.
 */
class AlurStokFifoTest extends TestCase
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
            'name' => 'Sidiq',
            'email' => 'sidiq@example.test',
            'password' => 'rahasia',
            'peran' => 'admin',
        ]);

        $kategori = KategoriBarang::create(['nama' => 'Obat Cair']);

        $this->supplier = Supplier::create(['kode' => 'SUP-01', 'nama' => 'Supplier A']);

        $this->limoxin = Barang::create([
            'kode' => 'OVK-001',
            'nama' => 'Limoxin-200 LA',
            'kategori_barang_id' => $kategori->id,
            'satuan' => 'botol',
            'isi_nilai' => 100,
            'isi_satuan' => 'ml',
        ]);
    }

    public function test_alur_lengkap_sesuai_rancangan(): void
    {
        // ── 1 Feb: beli 10 botol @ Rp 85.000 ────────────────────────
        $this->terima('2026-02-01', 10, 85_000);

        $this->assertSame(10.0, $this->limoxin->stok());
        $this->assertSame(850_000.0, $this->limoxin->nilaiPersediaan());

        // ── 10 Feb: beli 10 botol @ Rp 92.000 (harga naik) ──────────
        $this->terima('2026-02-10', 10, 92_000);

        $this->assertSame(20.0, $this->limoxin->stok());
        $this->assertSame(1_770_000.0, $this->limoxin->nilaiPersediaan());

        // ── 15 Feb: dokter ambil 12 botol ───────────────────────────
        // FIFO: 10 dari lot #1 @85rb + 2 dari lot #2 @92rb
        $pengeluaran = $this->keluarkan('2026-02-15', 12);

        $this->assertSame(1_034_000.0, $pengeluaran->totalHpp());
        $this->assertSame(8.0, $this->limoxin->stok());
        $this->assertSame(736_000.0, $this->limoxin->nilaiPersediaan());

        // Alokasinya harus benar-benar dari dua lot berbeda, bukan
        // satu harga rata-rata yang kebetulan hasilnya sama.
        $alokasi = $pengeluaran->items()->first()->alokasi()->get();

        $this->assertCount(2, $alokasi);
        $this->assertSame(10.0, (float) $alokasi[0]->qty);
        $this->assertSame(85_000.0, (float) $alokasi[0]->harga_satuan);
        $this->assertSame(2.0, (float) $alokasi[1]->qty);
        $this->assertSame(92_000.0, (float) $alokasi[1]->harga_satuan);

        // Lot pertama harus habis, lot kedua sisa 8.
        $lots = $this->limoxin->lot()->orderBy('id')->get();
        $this->assertSame(0.0, (float) $lots[0]->qty_sisa);
        $this->assertSame(8.0, (float) $lots[1]->qty_sisa);

        // ── 28 Feb: opname, fisik 7 (sistem 8) ──────────────────────
        $opname = $this->opname('2026-02-28', 7);

        $item = $opname->items()->first();
        $this->assertSame(8.0, (float) $item->stok_sistem);
        $this->assertSame(7.0, (float) $item->stok_fisik);
        $this->assertSame(-1.0, (float) $item->selisih);
        $this->assertSame(-92_000.0, (float) $item->nilai_selisih);

        // ── Saldo akhir ─────────────────────────────────────────────
        $this->assertSame(7.0, $this->limoxin->stok());
        $this->assertSame(644_000.0, $this->limoxin->nilaiPersediaan());
    }

    public function test_biaya_obat_per_ekor_dari_dosis_dokter(): void
    {
        $this->terima('2026-02-01', 10, 85_000);
        $this->terima('2026-02-10', 10, 92_000);

        $pengeluaran = $this->keluarkan('2026-02-15', 12);
        $item = $pengeluaran->items()->first();

        // Harga rata-rata pengambilan: 1.034.000 / 12 = 86.166,67 per botol
        $this->assertEqualsWithDelta(86_166.67, $item->hargaRataRata(), 0.01);

        // Botol isi 100 ml, jadi sekitar Rp 862/ml
        $perMl = $this->limoxin->hargaPerIsi($item->hargaRataRata());
        $this->assertEqualsWithDelta(861.67, $perMl, 0.01);

        // Dosis 20 ml ke ear tag 4250 → sekitar Rp 17.233
        $this->assertEqualsWithDelta(17_233.33, $perMl * 20, 0.01);
    }

    public function test_barang_tanpa_isi_tidak_punya_harga_per_satuan_terkecil(): void
    {
        $sarungTangan = Barang::create([
            'kode' => 'BHP-002',
            'nama' => 'Sarung Tangan Latex',
            'kategori_barang_id' => $this->limoxin->kategori_barang_id,
            'satuan' => 'pcs',
        ]);

        $this->assertNull($sarungTangan->hargaPerIsi(1_500));
    }

    public function test_pengeluaran_melebihi_stok_ditolak(): void
    {
        $this->terima('2026-02-01', 5, 85_000);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/tidak cukup/');

        $this->keluarkan('2026-02-02', 6);
    }

    /**
     * Inilah yang bikin bug "stok ganda" di sistem lama jadi mustahil:
     * baris kartu stok tidak bisa diubah maupun dihapus.
     */
    public function test_kartu_stok_tidak_bisa_diubah(): void
    {
        $this->terima('2026-02-01', 10, 85_000);

        $baris = PergerakanStok::first();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/tidak boleh diubah/');

        $baris->update(['qty' => 999]);
    }

    public function test_kartu_stok_tidak_bisa_dihapus(): void
    {
        $this->terima('2026-02-01', 10, 85_000);

        $baris = PergerakanStok::first();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/tidak boleh dihapus/');

        $baris->delete();
    }

    public function test_koreksi_membalik_tanpa_menghapus_riwayat(): void
    {
        $this->terima('2026-02-01', 10, 85_000);

        $this->stok->catatKoreksi($this->limoxin, -2, 'Salah input, seharusnya 8', $this->user);

        $this->assertSame(8.0, $this->limoxin->stok());

        // Baris aslinya tetap ada — riwayatnya utuh, bukan ditimpa.
        $this->assertSame(2, PergerakanStok::count());
        $this->assertSame(10.0, (float) PergerakanStok::first()->qty);
    }

    public function test_opname_satu_periode_tidak_boleh_dobel(): void
    {
        $this->terima('2026-02-01', 10, 85_000);
        $this->opname('2026-02-28', 10);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Opname::create([
            'nomor' => 'SCK-OVK-O-II-26-002',
            'tanggal' => '2026-02-28',
            'periode_bulan' => 2,
            'periode_tahun' => 2026,
            'dibuat_oleh' => $this->user->id,
        ]);
    }

    public function test_format_nomor_dokumen(): void
    {
        $mei = Carbon::parse('2026-05-04');

        $this->assertSame('SCK-OVK-M-V-26-001', $this->nomor->berikutnya('M', $mei));
        $this->assertSame('SCK-OVK-M-V-26-002', $this->nomor->berikutnya('M', $mei));

        // Jenis berbeda punya antrian sendiri.
        $this->assertSame('SCK-OVK-K-V-26-001', $this->nomor->berikutnya('K', $mei));

        // Ganti bulan: urutan jalan terus, bulan romawinya yang berubah.
        $this->assertSame('SCK-OVK-M-VI-26-003', $this->nomor->berikutnya('M', Carbon::parse('2026-06-01')));

        // Ganti tahun: balik ke 001.
        $this->assertSame('SCK-OVK-M-I-27-001', $this->nomor->berikutnya('M', Carbon::parse('2027-01-05')));
    }

    // ── Pembantu ────────────────────────────────────────────────────

    private function terima(string $tanggal, float $qty, float $harga): Penerimaan
    {
        $penerimaan = Penerimaan::create([
            'nomor' => $this->nomor->berikutnya('M', Carbon::parse($tanggal)),
            'tanggal' => $tanggal,
            'supplier_id' => $this->supplier->id,
            'dibuat_oleh' => $this->user->id,
        ]);

        return $this->stok->catatPenerimaan($penerimaan, [[
            'barang_id' => $this->limoxin->id,
            'qty' => $qty,
            'harga_satuan' => $harga,
        ]], $this->user);
    }

    private function keluarkan(string $tanggal, float $qty): Pengeluaran
    {
        $petugas = Petugas::firstOrCreate(['nama' => 'Gunawan'], ['peran' => 'dokter']);

        $pengeluaran = Pengeluaran::create([
            'nomor' => $this->nomor->berikutnya('K', Carbon::parse($tanggal)),
            'tanggal' => $tanggal,
            'tujuan' => 'dokter',
            'petugas_id' => $petugas->id,
            'dibuat_oleh' => $this->user->id,
        ]);

        return $this->stok->catatPengeluaran($pengeluaran, [[
            'barang_id' => $this->limoxin->id,
            'qty' => $qty,
        ]], $this->user);
    }

    private function opname(string $tanggal, float $fisik): Opname
    {
        $tgl = Carbon::parse($tanggal);

        $opname = Opname::create([
            'nomor' => $this->nomor->berikutnya('O', $tgl),
            'tanggal' => $tanggal,
            'periode_bulan' => $tgl->month,
            'periode_tahun' => $tgl->year,
            'dibuat_oleh' => $this->user->id,
        ]);

        OpnameItem::create([
            'opname_id' => $opname->id,
            'barang_id' => $this->limoxin->id,
            'stok_sistem' => $this->limoxin->stokPerTanggal($tanggal),
            'stok_fisik' => $fisik,
        ]);

        return $this->stok->finalkanOpname($opname, $this->user);
    }
}
