<?php

namespace Tests\Feature;

use App\Models\Penjualan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Dua jaminan tentang cara aplikasi menyala.
 *
 * Keduanya pernah rusak: penegakan foreign key sempat diatur lewat PRAGMA di
 * AppServiceProvider::boot(), yang bukan cuma mubazir — Laravel sudah
 * melakukannya sendiri — tapi juga memaksa koneksi database terbuka tiap kali
 * aplikasi boot. Akibatnya `composer install` gagal di mesin yang databasenya
 * belum ada, dan rilis berhenti di situ.
 */
class BootAplikasiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Foreign key tetap ditegakkan tanpa PRAGMA manual.
     *
     * Kalau tidak, baris anak yang menunjuk induk yang tidak ada akan diterima
     * diam-diam di SQLite — lalu baru meledak nanti di MySQL server.
     */
    public function test_foreign_key_tetap_ditegakkan_di_sqlite(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());

        $this->assertSame(
            1,
            (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys,
            'SQLite menerima relasi rusak kalau foreign_keys mati.',
        );

        $this->expectException(QueryException::class);

        Penjualan::create([
            'induksi_id' => 999999,   // tidak ada
            'tanggal' => '2026-06-01',
            'berat' => 500,
        ]);
    }

    /**
     * Boot tidak boleh menyentuh database.
     *
     * `php artisan package:discover` dijalankan composer setelah memasang
     * dependensi, saat database sering belum ada sama sekali. Kalau boot
     * membuka koneksi, perintah itu gagal dan rilis ikut berhenti.
     */
    public function test_perintah_artisan_jalan_tanpa_database(): void
    {
        // Harus proses terpisah. $this->artisan() memakai aplikasi yang sudah
        // terlanjur boot dengan database test, jadi tidak akan pernah
        // menangkap masalah yang justru terjadi saat boot.
        $proses = Process::fromShellCommandline(
            'php artisan package:discover --ansi 2>&1',
            base_path(),
            [
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => '/jalur/yang/tidak/ada/database.sqlite',
                'DB_URL' => '',
                'APP_ENV' => 'testing',
            ],
            timeout: 60,
        );

        $proses->run();

        $this->assertSame(
            0,
            $proses->getExitCode(),
            "package:discover gagal tanpa database — rilis akan berhenti di composer install:\n".$proses->getOutput(),
        );
    }
}
