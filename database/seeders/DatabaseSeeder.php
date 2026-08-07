<?php

namespace Database\Seeders;

use App\Models\AliasBarang;
use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\Petugas;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->users();
        $this->kategori();
        $this->petugas();
        $this->shipments();
        $this->contohBarang();
    }

    /**
     * Pengganti satu password global di sistem lama.
     * Password awal wajib diganti setelah rilis.
     */
    private function users(): void
    {
        User::firstOrCreate(
            ['email' => 'sidiq.sck@gmail.com'],
            [
                'name' => 'Sidiq',
                'password' => Hash::make('ubah-password-ini'),
                'peran' => 'admin',
                'aktif' => true,
            ],
        );
    }

    private function kategori(): void
    {
        $daftar = [
            ['nama' => 'Obat Cair', 'keterangan' => 'Antibiotik, antiinflamasi, vitamin injeksi', 'urutan' => 1],
            ['nama' => 'Obat Tablet', 'keterangan' => 'Obat bentuk tablet atau bolus', 'urutan' => 2],
            ['nama' => 'Alat Kesehatan', 'keterangan' => 'Pisau bedah, tabung sample darah, jarum sample', 'urutan' => 3],
            ['nama' => 'Bahan Habis Pakai', 'keterangan' => 'Alkohol, sarung tangan, jarum suntik', 'urutan' => 4],
        ];

        foreach ($daftar as $row) {
            KategoriBarang::firstOrCreate(['nama' => $row['nama']], $row);
        }
    }

    /**
     * Orang yang mengambil barang dari gudang.
     * Nama Gunawan juga muncul sebagai Penanggung Jawab di sheet rekam medis.
     */
    private function petugas(): void
    {
        $daftar = [
            ['nama' => 'Gunawan', 'peran' => 'dokter'],
            ['nama' => 'Junaidi', 'peran' => 'operator'],
        ];

        foreach ($daftar as $row) {
            Petugas::firstOrCreate(['nama' => $row['nama']], $row + ['aktif' => true]);
        }
    }

    /** Shipment yang muncul di data rekam medis sistem lama. */
    private function shipments(): void
    {
        foreach (range(83, 91) as $nomor) {
            Shipment::firstOrCreate(
                ['kode' => "SCK{$nomor}"],
                ['nomor' => $nomor, 'aktif' => true],
            );
        }
    }

    /**
     * Contoh barang beserta aliasnya, diambil dari nama obat yang benar-benar
     * muncul di sheet rekam medis dokter. Alias di sistem lama di-hardcode
     * 5 baris di streamlit_app.py (ALIAS_OBAT) — di sini jadi data.
     */
    private function contohBarang(): void
    {
        $obatCair = KategoriBarang::where('nama', 'Obat Cair')->first();
        $habisPakai = KategoriBarang::where('nama', 'Bahan Habis Pakai')->first();
        $alkes = KategoriBarang::where('nama', 'Alat Kesehatan')->first();

        $daftar = [
            [
                'kode' => 'OVK-001',
                'nama' => 'Limoxin-200 LA',
                'kategori_barang_id' => $obatCair->id,
                'satuan' => 'botol',
                'isi_nilai' => 100,
                'isi_satuan' => 'ml',
                'stok_minimum' => 2,
                'alias' => ['limoxin 200', 'limoxin-200 la', 'limoxin'],
            ],
            [
                'kode' => 'OVK-002',
                'nama' => 'Glucortin-20',
                'kategori_barang_id' => $obatCair->id,
                'satuan' => 'botol',
                'isi_nilai' => 50,
                'isi_satuan' => 'ml',
                'stok_minimum' => 2,
                'alias' => ['glucortin', 'glucortin 20'],
            ],
            [
                'kode' => 'OVK-003',
                'nama' => 'Vitamin B Kompleks',
                'kategori_barang_id' => $obatCair->id,
                'satuan' => 'botol',
                'isi_nilai' => 100,
                'isi_satuan' => 'ml',
                'stok_minimum' => 2,
                'alias' => ['vit b kompleks', 'vit b complex', 'vitamin b complex', 'b-plex'],
            ],
            [
                'kode' => 'BHP-001',
                'nama' => 'Alkohol 70%',
                'kategori_barang_id' => $habisPakai->id,
                'satuan' => 'liter',
                'isi_nilai' => null,
                'isi_satuan' => null,
                'stok_minimum' => 5,
                'alias' => [],
            ],
            [
                'kode' => 'BHP-002',
                'nama' => 'Sarung Tangan Latex',
                'kategori_barang_id' => $habisPakai->id,
                'satuan' => 'pcs',
                'isi_nilai' => null,
                'isi_satuan' => null,
                'stok_minimum' => 50,
                'alias' => [],
            ],
            [
                'kode' => 'ALK-001',
                'nama' => 'Tabung Sample Darah',
                'kategori_barang_id' => $alkes->id,
                'satuan' => 'pcs',
                'isi_nilai' => null,
                'isi_satuan' => null,
                'stok_minimum' => 20,
                'alias' => [],
            ],
        ];

        foreach ($daftar as $row) {
            $alias = $row['alias'];
            unset($row['alias']);

            $barang = Barang::firstOrCreate(['kode' => $row['kode']], $row + ['aktif' => true]);

            foreach ($alias as $teks) {
                AliasBarang::firstOrCreate(['alias' => $teks], ['barang_id' => $barang->id]);
            }
        }
    }
}
