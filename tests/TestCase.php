<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Test tidak boleh bergantung pada aset yang sudah dibangun.
         *
         * @vite() di layout mencari public/build/manifest.json, yang lahir
         * dari `npm run build` dan sengaja tidak ikut masuk repo. Di mesin
         * pengembang berkas itu sudah terlanjur ada, jadi semuanya tampak
         * hijau; di kloning bersih tidak ada, dan setiap test yang me-render
         * halaman langsung gagal dengan "Vite manifest not found".
         *
         * Yang diuji di sini isi dan perilaku halamannya, bukan nama berkas
         * aset ber-hash. withoutVite() memutus ketergantungan itu.
         */
        $this->withoutVite();
    }
}
