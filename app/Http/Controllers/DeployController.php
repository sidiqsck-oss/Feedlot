<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Menjalankan perintah rilis lewat HTTP.
 *
 * Hosting tujuan tidak punya akses SSH, jadi `php artisan migrate` tidak bisa
 * dijalankan dari terminal. Route ini penggantinya, dan sengaja dibuat sesempit
 * mungkin:
 *
 *   - mati total kalau DEPLOY_TOKEN kosong (bukan cuma menolak — 404)
 *   - token dibandingkan dengan hash_equals, bukan ==
 *   - hanya tiga perintah yang boleh, tidak menerima nama perintah dari luar
 *
 * Setelah rilis stabil, kosongkan lagi DEPLOY_TOKEN di .env supaya route ini
 * benar-benar tidak ada.
 */
class DeployController extends Controller
{
    public function migrate(Request $request): JsonResponse
    {
        $this->pastikanBerhak($request);

        // Migrasi tabel besar bisa lewat batas waktu PHP bawaan shared hosting.
        @set_time_limit(300);

        Artisan::call('migrate', ['--force' => true]);

        return response()->json([
            'perintah' => 'migrate',
            'keluaran' => trim(Artisan::output()),
        ]);
    }

    public function optimize(Request $request): JsonResponse
    {
        $this->pastikanBerhak($request);

        $keluaran = [];

        // config:cache dan route:cache wajib dijalankan ulang tiap rilis,
        // kalau tidak, .env yang baru tidak terbaca.
        foreach (['config:cache', 'route:cache', 'view:cache', 'event:cache'] as $perintah) {
            Artisan::call($perintah);
            $keluaran[$perintah] = trim(Artisan::output());
        }

        return response()->json(['keluaran' => $keluaran]);
    }

    public function status(Request $request): JsonResponse
    {
        $this->pastikanBerhak($request);

        Artisan::call('migrate:status');

        return response()->json([
            'versi_laravel' => app()->version(),
            'versi_php' => PHP_VERSION,
            'lingkungan' => app()->environment(),
            'migrasi' => trim(Artisan::output()),
        ]);
    }

    /**
     * Tanpa token yang benar, route ini berperilaku seolah tidak ada —
     * 404, bukan 403. Halaman yang menjawab "salah token" memberi tahu
     * penebak bahwa alamatnya sudah benar.
     */
    private function pastikanBerhak(Request $request): void
    {
        $tokenAsli = (string) config('app.deploy_token');

        if ($tokenAsli === '') {
            throw new NotFoundHttpException;
        }

        $dikirim = (string) ($request->header('X-Deploy-Token') ?? $request->query('token', ''));

        if ($dikirim === '' || ! hash_equals($tokenAsli, $dikirim)) {
            throw new NotFoundHttpException;
        }
    }
}
