<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Route rilis adalah satu-satunya jalan menjalankan perintah artisan di server
 * yang tidak punya SSH, jadi penjagaannya diuji secara terpisah.
 */
class RouteRilisTest extends TestCase
{
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

    public function test_token_benar_lewat_header_diterima(): void
    {
        config(['app.deploy_token' => 'token-yang-benar']);

        $this->withHeader('X-Deploy-Token', 'token-yang-benar')
            ->getJson('/__deploy/status')
            ->assertOk()
            ->assertJsonStructure(['versi_laravel', 'versi_php', 'lingkungan', 'migrasi']);
    }

    public function test_tidak_perlu_token_csrf(): void
    {
        config(['app.deploy_token' => 'token-yang-benar']);

        // Dipanggil oleh GitHub Actions, bukan browser — tidak punya sesi.
        $this->withHeader('X-Deploy-Token', 'token-yang-benar')
            ->post('/__deploy/optimize')
            ->assertOk();
    }
}
