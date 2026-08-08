<?php

namespace App\Services\Impor;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Membuat berkas templat impor yang bisa diunduh.
 *
 * Judul kolomnya dihasilkan dari definisi yang sama yang dipakai pemeriksa
 * unggahan (TemplatImpor), jadi templat dan pemeriksa tidak mungkin menyimpang.
 * Kalau keduanya ditulis terpisah, cepat atau lambat akan ada templat yang
 * kolomnya sudah berubah tapi pemeriksanya belum — dan operator yang kena.
 *
 * Berisi dua lembar: lembar isian, dan lembar petunjuk yang menerangkan tiap
 * kolom. Petunjuknya ditaruh di dalam berkas, bukan cuma di layar, karena
 * berkas ini akan berpindah tangan lewat WhatsApp dan email.
 */
class PembuatTemplat
{
    public function unduh(string $jenis): StreamedResponse
    {
        $definisi = TemplatImpor::definisi($jenis);
        $kolom = $definisi['kolom'];

        $buku = new Spreadsheet;

        $this->lembarIsian($buku, $definisi, $kolom);
        $this->lembarPetunjuk($buku, $definisi, $kolom);

        $buku->setActiveSheetIndex(0);

        $nama = 'templat-'.$jenis.'.xlsx';

        return response()->streamDownload(function () use ($buku) {
            (new Xlsx($buku))->save('php://output');
            $buku->disconnectWorksheets();
        }, $nama, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function lembarIsian(Spreadsheet $buku, array $definisi, array $kolom): void
    {
        $lembar = $buku->getActiveSheet();
        $lembar->setTitle($definisi['lembar'] ?? 'DATA');

        $nomor = 1;

        foreach ($kolom as $aturan) {
            $huruf = $this->huruf($nomor);

            $lembar->setCellValue($huruf.'1', $aturan['judul']);

            // Satu baris contoh supaya bentuk isiannya kelihatan. Baris ini
            // akan terbaca sebagai data saat diunggah, jadi diberi tanda jelas
            // di kolom pertama agar operator tahu harus menghapusnya.
            $lembar->setCellValue($huruf.'2', $aturan['contoh']);

            $lembar->getComment($huruf.'1')->getText()->createTextRun(
                $aturan['keterangan'].($aturan['wajib'] ? "\n\nWAJIB DIISI." : "\n\nBoleh dikosongkan.")
            );

            // Semua kolom dibuat teks. Tanpa ini Excel memotong nol di depan
            // pada RFID dan ear tag ("04250" jadi "4250"), dan angka panjang
            // berubah jadi notasi ilmiah.
            $lembar->getStyle($huruf)->getNumberFormat()->setFormatCode('@');

            $lembar->getColumnDimension($huruf)->setWidth(max(12, mb_strlen($aturan['judul']) + 4));

            $lembar->getStyle($huruf.'1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $aturan['wajib'] ? '0E6B5A' : '6C7F75'],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            $nomor++;
        }

        $lembar->getStyle('A2:'.$this->huruf($nomor - 1).'2')
            ->getFont()->setItalic(true)->getColor()->setRGB('A63528');

        $lembar->getRowDimension(1)->setRowHeight(24);
        $lembar->freezePane('A2');
    }

    private function lembarPetunjuk(Spreadsheet $buku, array $definisi, array $kolom): void
    {
        $lembar = $buku->createSheet();
        $lembar->setTitle('PETUNJUK');

        $lembar->setCellValue('A1', 'Templat '.$definisi['nama']);
        $lembar->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $petunjuk = [
            '',
            'Cara memakai:',
            '1. Hapus baris contoh yang bertulisan merah di lembar '.($definisi['lembar'] ?? 'DATA').'.',
            '2. Isi datanya mulai baris ke-2. Jangan mengubah judul di baris ke-1.',
            '3. Simpan, lalu unggah lewat menu Impor Data dan pilih shipment yang sesuai.',
            '4. Periksa halaman pratinjau sebelum menekan Proses. Belum ada data yang masuk sampai itu ditekan.',
            '',
            'Catatan:',
            '- Kolom berjudul hijau wajib diisi, yang abu-abu boleh dikosongkan.',
            '- Satu berkas untuk satu shipment.',
            '- Berkas yang sama tidak bisa diunggah dua kali.',
            '- Baris yang bermasalah akan dilaporkan lengkap dengan nomor barisnya,',
            '  dan baris lain tetap bisa diproses.',
            '',
            'Daftar kolom:',
        ];

        $baris = 2;

        foreach ($petunjuk as $teks) {
            $lembar->setCellValue('A'.$baris++, $teks);
        }

        $barisJudulTabel = $baris;

        $lembar->setCellValue('A'.$baris, 'Kolom');
        $lembar->setCellValue('B'.$baris, 'Wajib');
        $lembar->setCellValue('C'.$baris, 'Contoh');
        $lembar->setCellValue('D'.$baris, 'Keterangan');

        $lembar->getStyle("A{$baris}:D{$baris}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0E6B5A']],
        ]);

        $baris++;

        foreach ($kolom as $aturan) {
            $lembar->setCellValue('A'.$baris, $aturan['judul']);
            $lembar->setCellValue('B'.$baris, $aturan['wajib'] ? 'Wajib' : 'Opsional');
            $lembar->setCellValue('C'.$baris, $aturan['contoh']);
            $lembar->setCellValue('D'.$baris, $aturan['keterangan']);
            $baris++;
        }

        $lembar->getStyle('D'.$barisJudulTabel.':D'.($baris - 1))
            ->getAlignment()->setWrapText(true);

        foreach (['A' => 20, 'B' => 12, 'C' => 22, 'D' => 70] as $huruf => $lebar) {
            $lembar->getColumnDimension($huruf)->setWidth($lebar);
        }
    }

    private function huruf(int $nomor): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($nomor);
    }
}
