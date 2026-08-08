<?php

namespace App\Support;

/**
 * Format angka khusus CPL.
 *
 * Aturan yang dipegang di seluruh modul: nilai kosong ditampilkan sebagai
 * em-dash, TIDAK PERNAH sebagai 0. Angka nol dan data yang tidak ada adalah
 * dua hal berbeda, dan menyamakan keduanya persis yang membuat ADG RWT di
 * sistem lama tampil lebih rendah dari kenyataan.
 */
class FormatCpl
{
    public const KOSONG = '—';

    /** ADG selalu dua desimal: 2,13 */
    public static function adg(float|int|string|null $nilai): string
    {
        return $nilai === null ? self::KOSONG : number_format((float) $nilai, 2, ',', '.');
    }

    /** Bobot: 341,5 kg */
    public static function kg(float|int|string|null $nilai): string
    {
        return $nilai === null ? self::KOSONG : number_format((float) $nilai, 1, ',', '.').' kg';
    }

    /** Lama pemeliharaan: 181 hari */
    public static function hari(float|int|string|null $nilai): string
    {
        return $nilai === null ? self::KOSONG : round((float) $nilai).' hari';
    }

    public static function persen(float|int|string|null $nilai, int $desimal = 1): string
    {
        return $nilai === null ? self::KOSONG : number_format((float) $nilai, $desimal, ',', '.').'%';
    }

    /**
     * Perubahan terhadap periode sebelumnya.
     *
     * Mengembalikan null kalau tidak ada pembanding — kartu lalu menampilkan
     * angkanya saja, bukan "▲ 0%" yang menyesatkan.
     *
     * @return array{arah: string, teks: string}|null
     */
    public static function delta(float|int|null $kini, float|int|null $lalu): ?array
    {
        if ($kini === null || $lalu === null || (float) $lalu == 0.0) {
            return null;
        }

        $selisih = ((float) $kini - (float) $lalu) / abs((float) $lalu) * 100;

        if (abs($selisih) < 0.05) {
            return ['arah' => 'tetap', 'teks' => 'sama'];
        }

        return [
            'arah' => $selisih > 0 ? 'naik' : 'turun',
            'teks' => ($selisih > 0 ? '▲ ' : '▼ ').number_format(abs($selisih), 1, ',', '.').'%',
        ];
    }
}
