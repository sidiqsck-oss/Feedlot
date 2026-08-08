<?php

namespace App\Services\Ekspor;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor Excel berformat.
 *
 * Dipakai HANYA untuk laporan yang jumlah barisnya terbatas dan formatnya
 * memang penting — laporan bulanan yang dikirim ke atasan, misalnya.
 * Untuk mengeluarkan data mentah, pakai EksporCsv.
 *
 * PhpSpreadsheet menyusun seluruh isi berkas di memori, jadi ada batas keras
 * di sini. Melebihi batas itu lebih baik ditolak dengan pesan yang menyuruh
 * pakai CSV, daripada dibiarkan mati kehabisan memori di tengah jalan —
 * yang di shared hosting muncul sebagai halaman putih tanpa keterangan.
 */
class EksporExcel
{
    public const BATAS_BARIS = 5000;

    /**
     * @param  array<int, string>  $judulKolom
     * @param  Collection  $baris  sudah berupa array nilai per baris
     */
    public function unduh(
        string $namaBerkas,
        string $judul,
        array $judulKolom,
        Collection $baris,
        ?string $subjudul = null,
        array $formatKolom = [],
    ): StreamedResponse {
        if ($baris->count() > self::BATAS_BARIS) {
            throw new RuntimeException(sprintf(
                'Data terlalu banyak untuk Excel (%s baris, batas %s). Pakai unduhan CSV — hasilnya sama dan tidak ada batas barisnya.',
                number_format($baris->count(), 0, ',', '.'),
                number_format(self::BATAS_BARIS, 0, ',', '.'),
            ));
        }

        $buku = new Spreadsheet;
        $lembar = $buku->getActiveSheet();
        $lembar->setTitle(mb_substr($judul, 0, 31));

        $kolomTerakhir = $this->hurufKolom(count($judulKolom));
        $barisSekarang = 1;

        // ── Kepala laporan ────────────────────────────────────────
        $lembar->setCellValue('A1', $judul);
        $lembar->mergeCells("A1:{$kolomTerakhir}1");
        $lembar->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $barisSekarang = 2;

        if ($subjudul) {
            $lembar->setCellValue("A{$barisSekarang}", $subjudul);
            $lembar->mergeCells("A{$barisSekarang}:{$kolomTerakhir}{$barisSekarang}");
            $lembar->getStyle("A{$barisSekarang}")->getFont()->setSize(10)->getColor()->setRGB('6C7F75');
            $barisSekarang++;
        }

        $barisSekarang++;
        $barisJudul = $barisSekarang;

        // ── Judul kolom ───────────────────────────────────────────
        foreach ($judulKolom as $i => $teks) {
            $lembar->setCellValue($this->hurufKolom($i + 1).$barisJudul, $teks);
        }

        $lembar->getStyle("A{$barisJudul}:{$kolomTerakhir}{$barisJudul}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0E6B5A']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $lembar->getRowDimension($barisJudul)->setRowHeight(22);
        $lembar->freezePane("A".($barisJudul + 1));

        // ── Isi ───────────────────────────────────────────────────
        $barisSekarang = $barisJudul + 1;

        foreach ($baris as $nilai) {
            foreach (array_values($nilai) as $i => $isi) {
                $lembar->setCellValue($this->hurufKolom($i + 1).$barisSekarang, $isi);
            }

            $barisSekarang++;
        }

        $barisAkhir = $barisSekarang - 1;

        if ($barisAkhir >= $barisJudul + 1) {
            $lembar->getStyle("A{$barisJudul}:{$kolomTerakhir}{$barisAkhir}")
                ->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)
                ->getColor()->setRGB('D9E1DB');

            foreach ($formatKolom as $indeksKolom => $format) {
                $huruf = $this->hurufKolom($indeksKolom + 1);
                $lembar->getStyle("{$huruf}".($barisJudul + 1).":{$huruf}{$barisAkhir}")
                    ->getNumberFormat()->setFormatCode($format);
            }
        }

        foreach (range(1, count($judulKolom)) as $i) {
            $lembar->getColumnDimension($this->hurufKolom($i))->setAutoSize(true);
        }

        $namaBerkas = str_ends_with($namaBerkas, '.xlsx') ? $namaBerkas : "{$namaBerkas}.xlsx";

        return response()->streamDownload(function () use ($buku) {
            (new Xlsx($buku))->save('php://output');

            // Lepaskan memori segera; di shared hosting sisa memori itu mahal.
            $buku->disconnectWorksheets();
        }, $namaBerkas, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function hurufKolom(int $nomor): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($nomor);
    }
}
