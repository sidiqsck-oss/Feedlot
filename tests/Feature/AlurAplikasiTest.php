<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\Opname;
use App\Models\Petugas;
use App\Models\Shipment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menjalankan alur yang sama seperti di rancangan, tapi lewat halaman —
 * bukan langsung memanggil service.
 *
 * Test service membuktikan hitungannya benar; test ini membuktikan hitungan itu
 * benar-benar tersambung ke tombol yang dipakai orang.
 */
class AlurAplikasiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Barang $limoxin;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Sidiq',
            'email' => 'sidiq@example.test',
            'password' => 'rahasia',
            'peran' => 'admin',
            'aktif' => true,
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

        Petugas::create(['nama' => 'Gunawan', 'peran' => 'dokter']);
        Shipment::create(['kode' => 'SCK90', 'nomor' => 90]);
    }

    public function test_halaman_wajib_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('barang.index'))->assertRedirect(route('login'));
        $this->get(route('penerimaan.create'))->assertRedirect(route('login'));
    }

    public function test_login_dan_buka_semua_halaman_utama(): void
    {
        $this->post(route('login.proses'), [
            'email' => 'sidiq@example.test',
            'password' => 'rahasia',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->user);

        foreach ([
            'dashboard', 'barang.index', 'barang.create', 'supplier.index',
            'petugas.index', 'shipment.index', 'penerimaan.index',
            'penerimaan.create', 'pengeluaran.index', 'pengeluaran.create',
            'purchase-order.index', 'purchase-order.create', 'opname.index',
            'opname.create', 'laporan.stok', 'laporan.mutasi', 'laporan.kartu',
        ] as $nama) {
            $this->get(route($nama))->assertOk();
        }
    }

    public function test_akun_nonaktif_ditolak(): void
    {
        $this->user->update(['aktif' => false]);

        $this->post(route('login.proses'), [
            'email' => 'sidiq@example.test',
            'password' => 'rahasia',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * Alur bagian 11 rancangan, dijalankan lewat halaman.
     * Saldo akhir harus 7 botol / Rp 644.000.
     */
    public function test_alur_lengkap_lewat_halaman(): void
    {
        $this->actingAs($this->user);

        // 1 Feb — beli 10 botol @ 85.000
        $this->post(route('penerimaan.store'), [
            'tanggal' => '2026-02-01',
            'supplier_id' => $this->supplier->id,
            'items' => [['barang_id' => $this->limoxin->id, 'qty' => 10, 'harga_satuan' => 85000]],
        ])->assertRedirect();

        // 10 Feb — beli 10 botol @ 92.000
        $this->post(route('penerimaan.store'), [
            'tanggal' => '2026-02-10',
            'supplier_id' => $this->supplier->id,
            'items' => [['barang_id' => $this->limoxin->id, 'qty' => 10, 'harga_satuan' => 92000]],
        ])->assertRedirect();

        $this->assertSame(20.0, $this->limoxin->stok());

        // 15 Feb — dokter ambil 12 botol
        $this->post(route('pengeluaran.store'), [
            'tanggal' => '2026-02-15',
            'tujuan' => 'dokter',
            'petugas_id' => Petugas::first()->id,
            'items' => [['barang_id' => $this->limoxin->id, 'qty' => 12]],
        ])->assertRedirect();

        $this->assertSame(8.0, $this->limoxin->stok());
        $this->assertSame(736_000.0, $this->limoxin->nilaiPersediaan());

        // 28 Feb — opname, fisik 7
        $this->post(route('opname.store'), [
            'tanggal' => '2026-02-28',
            'periode_bulan' => 2,
            'periode_tahun' => 2026,
        ])->assertRedirect();

        $opname = Opname::first();
        $item = $opname->items()->first();

        $this->put(route('opname.update', $opname), [
            'fisik' => [$item->id => 7],
            'keterangan' => [$item->id => 'Selisih 1 botol'],
        ])->assertRedirect();

        $this->post(route('opname.finalkan', $opname))->assertRedirect();

        $this->assertSame(7.0, $this->limoxin->stok());
        $this->assertSame(644_000.0, $this->limoxin->nilaiPersediaan());
        $this->assertSame('final', $opname->fresh()->status);
    }

    public function test_pengeluaran_melebihi_stok_ditolak_dengan_pesan_jelas(): void
    {
        $this->actingAs($this->user);

        $this->post(route('penerimaan.store'), [
            'tanggal' => '2026-02-01',
            'supplier_id' => $this->supplier->id,
            'items' => [['barang_id' => $this->limoxin->id, 'qty' => 5, 'harga_satuan' => 85000]],
        ]);

        $this->post(route('pengeluaran.store'), [
            'tanggal' => '2026-02-02',
            'tujuan' => 'dokter',
            'items' => [['barang_id' => $this->limoxin->id, 'qty' => 9]],
        ])->assertSessionHas('gagal', fn ($pesan) => str_contains($pesan, 'tidak cukup'));

        // Nota gagal tidak boleh meninggalkan jejak setengah jadi.
        $this->assertSame(5.0, $this->limoxin->stok());
        $this->assertDatabaseCount('pengeluaran', 0);
    }

    public function test_induksi_wajib_menyebut_shipment(): void
    {
        $this->actingAs($this->user);

        $this->post(route('pengeluaran.store'), [
            'tanggal' => '2026-02-02',
            'tujuan' => 'induksi',
            'items' => [['barang_id' => $this->limoxin->id, 'qty' => 1]],
        ])->assertSessionHasErrors('shipment_id');
    }

    public function test_barang_yang_sama_dua_baris_ditolak(): void
    {
        $this->actingAs($this->user);

        $this->post(route('penerimaan.store'), [
            'tanggal' => '2026-02-01',
            'supplier_id' => $this->supplier->id,
            'items' => [
                ['barang_id' => $this->limoxin->id, 'qty' => 5, 'harga_satuan' => 85000],
                ['barang_id' => $this->limoxin->id, 'qty' => 3, 'harga_satuan' => 85000],
            ],
        ])->assertSessionHas('gagal');

        $this->assertDatabaseCount('penerimaan', 0);
    }

    public function test_opname_periode_sama_diarahkan_ke_yang_sudah_ada(): void
    {
        $this->actingAs($this->user);

        $this->post(route('opname.store'), [
            'tanggal' => '2026-02-28', 'periode_bulan' => 2, 'periode_tahun' => 2026,
        ]);

        $this->post(route('opname.store'), [
            'tanggal' => '2026-02-28', 'periode_bulan' => 2, 'periode_tahun' => 2026,
        ])->assertRedirect(route('opname.show', Opname::first()))
          ->assertSessionHas('gagal');

        $this->assertDatabaseCount('opname', 1);
    }

    public function test_barang_berisi_riwayat_dinonaktifkan_bukan_dihapus(): void
    {
        $this->actingAs($this->user);

        $this->post(route('penerimaan.store'), [
            'tanggal' => '2026-02-01',
            'supplier_id' => $this->supplier->id,
            'items' => [['barang_id' => $this->limoxin->id, 'qty' => 5, 'harga_satuan' => 85000]],
        ]);

        $this->delete(route('barang.destroy', $this->limoxin))->assertRedirect();

        $this->assertDatabaseHas('barang', ['id' => $this->limoxin->id, 'aktif' => false]);
    }

    public function test_petugas_bisa_ditambah_dan_dihapus(): void
    {
        $this->actingAs($this->user);

        $this->post(route('petugas.store'), ['nama' => 'Junaidi', 'peran' => 'operator', 'aktif' => 1])
            ->assertRedirect(route('petugas.index'));

        $junaidi = Petugas::where('nama', 'Junaidi')->firstOrFail();

        // Belum pernah dipakai, jadi boleh benar-benar dihapus.
        $this->delete(route('petugas.destroy', $junaidi))->assertRedirect();

        $this->assertDatabaseMissing('petugas', ['nama' => 'Junaidi']);
    }
}
