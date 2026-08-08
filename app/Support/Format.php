<?php

namespace App\Support;

/**
 * Format angka gaya Indonesia, dipakai konsisten di semua tampilan.
 *
 * Dikumpulkan di satu tempat karena di sistem lama pemformatan tersebar dan
 * beda-beda antar halaman, sehingga angka yang sama bisa tampil berbeda di
 * dua laporan.
 */
class Format
{
    /** 1034000 → "Rp 1.034.000" */
    public static function rupiah(float|int|string|null $nilai, bool $denganDesimal = false): string
    {
        $nilai = (float) ($nilai ?? 0);

        return 'Rp '.number_format($nilai, $denganDesimal ? 2 : 0, ',', '.');
    }

    /**
     * 12.000 → "12" · 2.500 → "2,5"
     *
     * Qty disimpan decimal(12,3) supaya dosis pecahan tetap bisa dicatat, tapi
     * menampilkan "12,000 botol" cuma bikin ramai. Nol di belakang dibuang.
     */
    public static function qty(float|int|string|null $nilai): string
    {
        $nilai = (float) ($nilai ?? 0);
        $teks = number_format($nilai, 3, ',', '.');

        if (str_contains($teks, ',')) {
            $teks = rtrim(rtrim($teks, '0'), ',');
        }

        return $teks === '' ? '0' : $teks;
    }

    /** Qty beserta satuannya: "12 botol" */
    public static function qtySatuan(float|int|string|null $nilai, ?string $satuan): string
    {
        return trim(self::qty($nilai).' '.($satuan ?? ''));
    }

    /** Angka bertanda untuk kartu stok: "+10" / "−12" */
    public static function bertanda(float|int|string|null $nilai): string
    {
        $nilai = (float) ($nilai ?? 0);
        $tanda = $nilai < 0 ? '−' : '+';

        return $tanda.self::qty(abs($nilai));
    }

    /** 5 → "V" */
    public static function bulanRomawi(int $bulan): string
    {
        return [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
        ][$bulan] ?? '';
    }

    /** 5 → "Mei" */
    public static function namaBulan(int $bulan): string
    {
        return [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ][$bulan] ?? '';
    }
}
