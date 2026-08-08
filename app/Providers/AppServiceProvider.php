<?php

namespace App\Providers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

        // SQLite tidak menegakkan foreign key kecuali dinyalakan per koneksi.
        // Dipakai saat pengembangan lokal dan di test, supaya relasi yang
        // salah ketahuan di sini — bukan nanti di MySQL server.
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }
}
