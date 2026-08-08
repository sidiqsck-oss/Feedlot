<?php

namespace App\Services\Ekspor;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Cetak dokumen ke PDF.
 *
 * Dipakai untuk dokumen yang memang dicetak dan diarsipkan — nota masuk, nota
 * keluar, berita acara opname. BUKAN untuk mengeluarkan data mentah; untuk itu
 * pakai EksporCsv, yang tidak punya batas jumlah baris.
 *
 * Dompdf tidak mendukung flexbox maupun grid, jadi seluruh tata letak di
 * berkas cetak memakai tabel. Itu terlihat kuno di kode, tapi memang begitu
 * cara kerja mesin cetaknya.
 */
class EksporPdf
{
    public function unduh(string $namaBerkas, string $tampilan, array $data, string $ukuran = 'a4'): Response
    {
        return $this->buat($tampilan, $data, $ukuran)
            ->download($this->rapikanNama($namaBerkas));
    }

    /** Dibuka di tab browser, bukan diunduh — lebih enak untuk langsung dicetak. */
    public function tampilkan(string $namaBerkas, string $tampilan, array $data, string $ukuran = 'a4'): Response
    {
        return $this->buat($tampilan, $data, $ukuran)
            ->stream($this->rapikanNama($namaBerkas));
    }

    private function buat(string $tampilan, array $data, string $ukuran)
    {
        return Pdf::loadView($tampilan, $data)
            ->setPaper($ukuran, 'portrait')
            ->setOptions([
                // Berkas cetak hanya memuat HTML dan CSS buatan sendiri.
                // Mematikan PHP dan akses berkas jarak jauh menutup jalur
                // eksekusi kode lewat isi dokumen.
                'isPhpEnabled' => false,
                'isRemoteEnabled' => false,
                'defaultFont' => 'DejaVu Sans',
                'dpi' => 96,
            ]);
    }

    private function rapikanNama(string $nama): string
    {
        $nama = str_replace(['/', '\\'], '-', $nama);

        return str_ends_with($nama, '.pdf') ? $nama : "{$nama}.pdf";
    }
}
