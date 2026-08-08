<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Route rilis adalah satu-satunya jalan menjalankan perintah artisan di server
 * yang tidak punya SSH, jadi penjagaannya diuji secara terpisah.
 *
 * Perintah artisan-nya sengaja dipalsukan di test yang menembus penjagaan.
 * Menjalankan config:cache sungguhan akan menulis konfigurasi lingkungan
 * testing ke bootstrap/cache/, dan itu ikut terbawa ke lingkungan lokal —
 * efek samping yang keluar dari batas test.
 */
class RouteRilisTest extends TestCase
{
    use RefreshDatabase;

    public function test_mati_total_kalau_token_kosong(): void
    {
        config(['app.deploy_token' => '']);

        $this->postJson('/__deploy/migrate')->assertNotFound();
        $this->postJson('/__deploy/optimize')->assertNotFound();
        $this->getJson('/__deploy/status')->assertNotFound();
    }

    public function test_token_salah_dibalas_404_bukan_403(): void
    {
        config(['app.deploy_token' => 'token-yang-benar']);

        // 403 memberi tahu penebak bahwa alamatnya sudah benar dan yang
        // kurang cuma tokennya. 404 tidak memberi petunjuk apa pun.
        $this->withHeader('X-Deploy-Token', 'token-yang-salah')
            ->getJson('/__deploy/status')
            ->assertNotFound();
    }

    public function test_token_kosong_di_permintaan_juga_ditolak(): void
    {
        config(['app.deploy_token' => 'token-yang-benar']);

        $this->getJson('/__deploy/status')->assertNotFound();
        $this->getJson('/__deploy/status?token=')->assertNotFound();
    }

    public function test_token_benar_lewat_header_diterima(): void
    {
        config(['app.deploy_token' => 'token-yang-benar']);

        $this->withHeader('X-Deploy-Token', 'token-yang-benar')
            ->getJson('/__deploy/status')
            ->assertOk()
            ->assertJsonStructure(['versi_laravel', 'versi_php', 'lingkungan', 'migrasi']);
    }

    public function test_token_benar_lewat_query_diterima(): void
    {
        config(['app.deploy_token' => 'token-yang-benar']);

        $this->getJson('/__deploy/status?token=token-yang-benar')->assertOk();
    }

    public function test_tidak_perlu_token_csrf(): void
    {
        config(['app.deploy_token' => 'token-yang-benar']);

        Artisan::shouldReceive('call')->andReturn(0);
        Artisan::shouldReceive('output')->andReturn('');

        // Dipanggil oleh GitHub Actions, bukan browser — tidak punya sesi
        // maupun token CSRF. Kalau middleware CSRF ikut berlaku, ini 419.
        $this->withHeader('X-Deploy-Token', 'token-yang-benar')
            ->post('/__deploy/optimize')
            ->assertOk();
    }
}
