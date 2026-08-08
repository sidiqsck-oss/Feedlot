<?php

namespace App\Services\Ekspor;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor CSV dengan streaming.
 *
 * Ini format bawaan untuk semua ekspor data, dan sengaja dibedakan dari Excel.
 * PhpSpreadsheet menyusun seluruh isi berkas di memori sebelum menulis, jadi
 * 8.000+ baris × 40 kolom bisa menghabiskan ratusan MB — di shared hosting
 * yang memory_limit-nya 128–256 MB, itu berhenti di tengah jalan tanpa pesan
 * yang jelas.
 *
 * Di sini tiap baris langsung dikirim ke browser lalu dibuang, sehingga
 * pemakaian memori tidak tumbuh seiring jumlah baris.
 */
class EksporCsv
{
    /**
     * @param  array<int, string>  $judulKolom
     * @param  Builder|Collection  $sumber
     * @param  callable  $petaBaris  ubah satu record jadi array nilai kolom
     */
    public function unduh(
        string $namaBerkas,
        array $judulKolom,
        Builder|Collection $sumber,
        callable $petaBaris,
        int $ukuranPotongan = 500,
    ): StreamedResponse {
        $namaBerkas = str_ends_with($namaBerkas, '.csv') ? $namaBerkas : "{$namaBerkas}.csv";

        return response()->streamDownload(function () use ($judulKolom, $sumber, $petaBaris, $ukuranPotongan) {
            $keluaran = fopen('php://output', 'wb');

            // BOM UTF-8. Tanpa ini, Excel di Windows membaca "Rp" dan huruf
            // beraksen sebagai karakter rusak.
            fwrite($keluaran, "\xEF\xBB\xBF");

            fputcsv($keluaran, $judulKolom, ';');

            if ($sumber instanceof Collection) {
                foreach ($sumber as $record) {
                    fputcsv($keluaran, $petaBaris($record), ';');
                }
            } else {
                // chunkById, bukan chunk: offset besar bikin database membaca
                // ulang baris yang sudah dilewati di tiap potongan.
                $sumber->chunkById($ukuranPotongan, function ($potongan) use ($keluaran, $petaBaris) {
                    foreach ($potongan as $record) {
                        fputcsv($keluaran, $petaBaris($record), ';');
                    }

                    flush();
                });
            }

            fclose($keluaran);
        }, $namaBerkas, [
            // Titik koma dipakai sebagai pemisah karena Excel dengan pengaturan
            // regional Indonesia memakai koma sebagai desimal — dengan pemisah
            // koma, satu angka pecahan akan terbelah jadi dua kolom.
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
