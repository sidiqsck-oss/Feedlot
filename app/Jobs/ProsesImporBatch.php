<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Services\Impor\ImporService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Memasukkan baris hasil pratinjau ke tabel tujuan, di latar belakang.
 *
 * Berkas satu shipment bisa ratusan baris, dan tiap baris melakukan beberapa
 * query. Lewat HTTP itu menabrak max_execution_time shared hosting (sering 30
 * detik) dan mati di tengah jalan — sebagian masuk, sebagian tidak, tanpa ada
 * yang tahu di mana putusnya.
 *
 * Di sini pekerjaannya diserahkan ke antrean, yang di server dijalankan oleh
 * cron tiap menit (lihat docs/deploy-cpanel.md).
 */
class ProsesImporBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    // Cron menjalankan queue:work dengan --max-time=55, jadi batas di sini
    // dibuat lebih pendek supaya pekerjaannya berhenti dengan rapi sebelum
    // pekerjanya sendiri dihentikan.
    public int $timeout = 50;

    public function __construct(public readonly int $batchId) {}

    public function handle(ImporService $impor): void
    {
        $batch = ImportBatch::find($this->batchId);

        if (! $batch || $batch->status !== 'pratinjau') {
            return;
        }

        $impor->proses($batch);
    }

    public function failed(Throwable $e): void
    {
        ImportBatch::where('id', $this->batchId)->update([
            'status' => 'gagal',
            'pesan' => 'Gagal diproses: '.$e->getMessage(),
        ]);
    }
}
