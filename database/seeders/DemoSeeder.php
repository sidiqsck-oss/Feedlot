<?php

namespace Database\Seeders;

use App\Models\Barang;
use App\Models\Penerimaan;
use App\Models\Pengeluaran;
use App\Models\Petugas;
use App\Models\Shipment;
use App\Models\Supplier;
use App\Models\User;
use App\Services\NomorDokumenService;
use App\Services\StokService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Data contoh untuk pengembangan lokal.
 *
 * Sengaja dipisah dari DatabaseSeeder supaya tidak pernah ikut terjalankan di
 * server. Jalankan manual: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrFail();
        $stok = app(StokService::class);
        $nomor = app(NomorDokumenService::class);

        $supplier = Supplier::firstOrCreate(
            ['kode' => 'SUP-01'],
            ['nama' => 'CV Anugerah Ternak', 'kontak' => 'Pak Hendra', 'telepon' => '0812-3456-7890'],
        );

        Supplier::firstOrCreate(['kode' => 'SUP-02'], ['nama' => 'PT Vetindo Jaya', 'kontak' => 'Bu Rina']);

        $b = fn (string $kode) => Barang::where('kode', $kode)->firstOrFail();

        // ── Barang masuk, dengan harga yang naik antar pembelian supaya
        //    perbedaan lot FIFO-nya benar-benar terlihat di layar.
        $notaMasuk = [
            ['2026-07-02', [['OVK-001', 10, 85_000], ['OVK-002', 8, 62_000], ['BHP-001', 20, 28_000]]],
            ['2026-07-18', [['OVK-001', 10, 92_000], ['OVK-003', 12, 45_000], ['BHP-002', 200, 1_500]]],
            ['2026-08-03', [['OVK-002', 6, 65_000], ['BHP-001', 15, 29_500]]],
        ];

        foreach ($notaMasuk as [$tanggal, $baris]) {
            $nota = Penerimaan::create([
                'nomor' => $nomor->berikutnya(NomorDokumenService::MASUK, Carbon::parse($tanggal)),
                'tanggal' => $tanggal,
                'supplier_id' => $supplier->id,
                'no_faktur_supplier' => 'INV/'.str_replace('-', '', $tanggal),
                'dibuat_oleh' => $user->id,
            ]);

            $stok->catatPenerimaan($nota, array_map(fn ($r) => [
                'barang_id' => $b($r[0])->id,
                'qty' => $r[1],
                'harga_satuan' => $r[2],
            ], $baris), $user);
        }

        // ── Barang keluar
        $gunawan = Petugas::where('nama', 'Gunawan')->first();
        $junaidi = Petugas::where('nama', 'Junaidi')->first();
        $shipment = Shipment::orderByDesc('nomor')->first();

        $notaKeluar = [
            ['2026-07-20', 'dokter', $gunawan, null, [['OVK-001', 12], ['OVK-002', 4]]],
            ['2026-07-28', 'induksi', $junaidi, $shipment, [['BHP-001', 8], ['BHP-002', 60]]],
            ['2026-08-05', 'dokter', $gunawan, null, [['OVK-003', 5], ['OVK-001', 3]]],
        ];

        foreach ($notaKeluar as [$tanggal, $tujuan, $petugas, $ship, $baris]) {
            $nota = Pengeluaran::create([
                'nomor' => $nomor->berikutnya(NomorDokumenService::KELUAR, Carbon::parse($tanggal)),
                'tanggal' => $tanggal,
                'tujuan' => $tujuan,
                'petugas_id' => $petugas?->id,
                'shipment_id' => $ship?->id,
                'dibuat_oleh' => $user->id,
            ]);

            $stok->catatPengeluaran($nota, array_map(fn ($r) => [
                'barang_id' => $b($r[0])->id,
                'qty' => $r[1],
            ], $baris), $user);
        }

        $this->command?->info(sprintf(
            'Demo siap: %d nota masuk, %d nota keluar. Limoxin sisa %s botol.',
            Penerimaan::count(),
            Pengeluaran::count(),
            $b('OVK-001')->stok(),
        ));
    }
}
