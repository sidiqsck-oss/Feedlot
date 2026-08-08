<?php

namespace App\Services\Impor;

use Generator;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as TanggalExcel;
use RuntimeException;

/**
 * Pembaca berkas tabel: .xlsx, .xls, dan .csv.
 *
 * Dibaca baris demi baris lewat generator, bukan dimuat sekaligus. Berkas
 * induksi satu shipment bisa ratusan baris dengan belasan kolom, dan di shared
 * hosting yang memory_limit-nya 128–256 MB, memuat seluruh berkas sekaligus
 * adalah cara paling cepat untuk mati tanpa pesan.
 *
 * Untuk .xlsx dipakai setReadDataOnly: rumus, gaya, dan format sel dibuang
 * karena yang dibutuhkan cuma nilainya. Itu memangkas pemakaian memori
 * beberapa kali lipat.
 */
class PembacaTabel
{
    /** Baris teratas yang dipindai saat mencari baris judul kolom. */
    private const BATAS_CARI_JUDUL = 20;

    /**
     * Baca berkas jadi deretan baris asosiatif.
     *
     * @param  string|null  $lembar  nama sheet; null berarti sheet pertama
     * @param  string|null  $kataKunciJudul  kata yang pasti ada di baris judul,
     *                                       dipakai melewati baris kop/logo di atasnya
     * @return Generator<int, array{nomor: int, data: array<string, mixed>}>
     */
    public function baca(string $jalur, ?string $lembar = null, ?string $kataKunciJudul = null): Generator
    {
        if (! is_readable($jalur)) {
            throw new RuntimeException('Berkas tidak bisa dibaca.');
        }

        $pembaca = IOFactory::createReaderForFile($jalur);
        $pembaca->setReadDataOnly(true);

        if ($lembar !== null && method_exists($pembaca, 'setLoadSheetsOnly')) {
            $pembaca->setLoadSheetsOnly($lembar);
        }

        $buku = $pembaca->load($jalur);

        $kertas = $lembar !== null
            ? ($buku->getSheetByName($lembar) ?? $buku->getSheet(0))
            : $buku->getSheet(0);

        $barisTerakhir = $kertas->getHighestDataRow();
        $kolomTerakhir = Coordinate::columnIndexFromString($kertas->getHighestDataColumn());

        $barisJudul = $this->cariBarisJudul($kertas, $kolomTerakhir, $barisTerakhir, $kataKunciJudul);
        $judul = $this->bacaJudul($kertas, $barisJudul, $kolomTerakhir);

        if ($judul === []) {
            throw new RuntimeException('Baris judul kolom tidak ditemukan di berkas ini.');
        }

        for ($baris = $barisJudul + 1; $baris <= $barisTerakhir; $baris++) {
            $data = [];
            $adaIsi = false;

            foreach ($judul as $indeksKolom => $namaKolom) {
                $sel = $kertas->getCell([$indeksKolom, $baris]);
                $nilai = $sel->getValue();

                // Excel menyimpan tanggal sebagai angka hari sejak 1900.
                // Tanpa pemeriksaan ini, "TGL INDUKSI" masuk sebagai 45678.
                if (is_numeric($nilai) && TanggalExcel::isDateTime($sel)) {
                    $nilai = TanggalExcel::excelToDateTimeObject($nilai)->format('Y-m-d');
                }

                if (is_string($nilai)) {
                    $nilai = trim($nilai);
                }

                if ($nilai !== null && $nilai !== '') {
                    $adaIsi = true;
                }

                $data[$namaKolom] = $nilai;
            }

            // Baris kosong di tengah berkas itu lumrah (pemisah antar blok),
            // jadi dilewati diam-diam — bukan dilaporkan sebagai kesalahan.
            if (! $adaIsi) {
                continue;
            }

            yield ['nomor' => $baris, 'data' => $data];
        }

        $buku->disconnectWorksheets();
        unset($buku);
    }

    /** Baca beberapa baris pertama saja, untuk pratinjau cepat sebelum diproses. */
    public function intip(string $jalur, int $jumlah = 5, ?string $lembar = null, ?string $kataKunciJudul = null): array
    {
        $hasil = [];

        foreach ($this->baca($jalur, $lembar, $kataKunciJudul) as $baris) {
            $hasil[] = $baris;

            if (count($hasil) >= $jumlah) {
                break;
            }
        }

        return $hasil;
    }

    public function daftarLembar(string $jalur): array
    {
        $pembaca = IOFactory::createReaderForFile($jalur);

        return method_exists($pembaca, 'listWorksheetNames')
            ? $pembaca->listWorksheetNames($jalur)
            : [];
    }

    /**
     * Berkas dari lapangan sering punya judul laporan atau logo di baris atas,
     * sehingga judul kolom tidak selalu di baris 1. Baris judul dicari lewat
     * kata kunci yang pasti ada di sana — pola yang sama dipakai sistem lama
     * (read_robust di 5_export_dashboard_data.py).
     */
    private function cariBarisJudul($kertas, int $kolomTerakhir, int $barisTerakhir, ?string $kataKunci): int
    {
        if (! $kataKunci) {
            return 1;
        }

        $kataKunci = mb_strtolower($kataKunci);
        $batas = min(self::BATAS_CARI_JUDUL, $barisTerakhir);

        for ($baris = 1; $baris <= $batas; $baris++) {
            for ($kolom = 1; $kolom <= $kolomTerakhir; $kolom++) {
                $nilai = $kertas->getCell([$kolom, $baris])->getValue();

                if (is_string($nilai) && str_contains(mb_strtolower(trim($nilai)), $kataKunci)) {
                    return $baris;
                }
            }
        }

        return 1;
    }

    /** @return array<int, string> indeks kolom => nama kolom yang sudah dirapikan */
    private function bacaJudul($kertas, int $barisJudul, int $kolomTerakhir): array
    {
        $judul = [];

        for ($kolom = 1; $kolom <= $kolomTerakhir; $kolom++) {
            $nilai = $kertas->getCell([$kolom, $barisJudul])->getValue();

            if ($nilai === null || trim((string) $nilai) === '') {
                continue;
            }

            $judul[$kolom] = $this->rapikanNamaKolom((string) $nilai);
        }

        return $judul;
    }

    /**
     * "TGL INDUKSI" / "Tgl  Induksi" / "tgl_induksi" semuanya jadi "tgl induksi".
     * Nama kolom di berkas asli tidak konsisten besar-kecilnya maupun spasinya,
     * jadi dinormalkan sekali di sini daripada ditebak berulang kali nanti.
     */
    public function rapikanNamaKolom(string $nama): string
    {
        $nama = str_replace(['_', '.', '-'], ' ', $nama);
        $nama = preg_replace('/\s+/', ' ', trim($nama));

        return mb_strtolower($nama);
    }
}
