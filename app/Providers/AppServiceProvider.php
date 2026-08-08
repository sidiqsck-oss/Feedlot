<?php

namespace App\Providers;

use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Tanpa ini, translatedFormat() tetap mengeluarkan "July" walau
        // APP_LOCALE sudah id — locale Carbon terpisah dari locale aplikasi.
        Carbon::setLocale(config('app.locale', 'id'));

        /*
         * Penegakan foreign key SQLite TIDAK diatur di sini.
         *
         * Laravel sudah menyalakannya sendiri tiap koneksi lewat
         * 'foreign_key_constraints' di config/database.php. Menjalankan
         * PRAGMA di boot() bukan cuma mubazir — perintahnya memaksa koneksi
         * database terbuka setiap aplikasi boot, termasuk saat
         * `composer install` menjalankan package:discover di mesin yang
         * databasenya belum ada sama sekali. Di situ rilis jadi gagal.
         *
         * boot() tidak boleh menyentuh database.
         */
    }
}
