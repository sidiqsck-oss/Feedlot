<?php

namespace App\Services;

use App\Models\NomorDokumen;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Generator nomor dokumen OVK.
 *
 * Format: SCK-OVK-{JENIS}-{BULAN ROMAWI}-{YY}-{URUT}
 * Contoh: SCK-OVK-M-V-26-001  (barang masuk, Mei 2026, urut ke-1)
 *
 * Urutan jalan terus sepanjang tahun dan balik ke 1 tiap ganti tahun, dihitung
 * terpisah per jenis dokumen. Jadi nota masuk ke-47 di bulan Mei bernomor
 * ...-V-26-047, dan nota masuk berikutnya di bulan Juni jadi ...-VI-26-048.
 */
class NomorDokumenService
{
    public const MASUK = 'M';
    public const KELUAR = 'K';
    public const OPNAME = 'O';
    public const PO = 'P';

    private const JENIS_SAH = [self::MASUK, self::KELUAR, self::OPNAME, self::PO];

    private const ROMAWI = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];

    /**
     * Ambil nomor berikutnya dan naikkan penghitungnya.
     *
     * Wajib dipanggil di dalam transaksi bersama penyimpanan dokumennya, supaya
     * nomor yang sudah diambil tidak hangus kalau penyimpanannya gagal.
     */
    public function berikutnya(string $jenis, ?Carbon $tanggal = null): string
    {
        if (! in_array($jenis, self::JENIS_SAH, true)) {
            throw new InvalidArgumentException("Jenis dokumen '{$jenis}' tidak dikenal.");
        }

        $tanggal ??= Carbon::now();
        $tahun = (int) $tanggal->year;

        return DB::transaction(function () use ($jenis, $tahun, $tanggal) {
            // lockForUpdate mencegah dua proses mengambil nomor yang sama.
            // firstOrCreate dulu supaya barisnya pasti ada sebelum dikunci.
            NomorDokumen::firstOrCreate(
                ['jenis' => $jenis, 'tahun' => $tahun],
                ['urutan_terakhir' => 0],
            );

            $counter = NomorDokumen::where('jenis', $jenis)
                ->where('tahun', $tahun)
                ->lockForUpdate()
                ->first();

            $urutan = (int) $counter->urutan_terakhir + 1;
            $counter->update(['urutan_terakhir' => $urutan]);

            return $this->format($jenis, $tanggal, $urutan);
        });
    }

    public function format(string $jenis, Carbon $tanggal, int $urutan): string
    {
        return sprintf(
            'SCK-OVK-%s-%s-%s-%03d',
            $jenis,
            self::ROMAWI[(int) $tanggal->month],
            $tanggal->format('y'),
            $urutan,
        );
    }

    public static function bulanRomawi(int $bulan): string
    {
        return self::ROMAWI[$bulan] ?? '';
    }
}
