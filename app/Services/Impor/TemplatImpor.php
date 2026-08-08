<?php

namespace App\Services\Impor;

use InvalidArgumentException;

/**
 * Definisi templat impor.
 *
 * Satu tempat yang menentukan kolom apa saja yang diharapkan tiap jenis berkas,
 * mana yang wajib, dan nama-nama lain yang mungkin dipakai untuk kolom yang
 * sama. Dipakai bersama oleh tiga hal sekaligus — pembuat berkas templat,
 * pemeriksa berkas unggahan, dan halaman bantuan — supaya ketiganya tidak
 * mungkin menyimpang satu sama lain.
 *
 * Nama kolom di berkas asli tidak konsisten ("TGL INDUKSI", "Tgl Induksi",
 * "tanggal induksi"), jadi tiap kolom punya daftar padanan. Pencocokan
 * dilakukan setelah nama dikecilkan dan spasinya dirapikan.
 */
class TemplatImpor
{
    public const INDUKSI = 'induksi';
    public const REWEIGHT = 'reweight';

    /**
     * @return array{
     *     nama: string,
     *     lembar: ?string,
     *     kata_kunci_judul: ?string,
     *     kolom: array<string, array{judul: string, wajib: bool, padanan: array<int, string>, contoh: string, keterangan: string}>
     * }
     */
    public static function definisi(string $jenis): array
    {
        return match ($jenis) {
            self::INDUKSI => [
                'nama' => 'Data Induksi',
                'lembar' => 'INDUKSI',
                'kata_kunci_judul' => 'rfid',
                'kolom' => [
                    'rfid' => [
                        'judul' => 'RFID',
                        'wajib' => true,
                        'padanan' => ['rfid', 'no rfid', 'nomor rfid'],
                        'contoh' => '982000123456789',
                        'keterangan' => 'Nomor cip di telinga. Identitas utama sapi, tidak boleh kosong atau kembar dalam satu shipment.',
                    ],
                    'ear_tag' => [
                        'judul' => 'EAR TAG',
                        'wajib' => true,
                        'padanan' => ['ear tag', 'eartag', 'no ear tag', 'nomor ear tag'],
                        'contoh' => '4250',
                        'keterangan' => 'Nomor anting. Dipakai menyambungkan rekam medis dokter, yang tidak mencatat RFID.',
                    ],
                    'tanggal_induksi' => [
                        'judul' => 'TGL INDUKSI',
                        'wajib' => false,
                        'padanan' => ['tgl induksi', 'tanggal induksi', 'tgl indct'],
                        'contoh' => '2026-02-15',
                        'keterangan' => 'Tanggal sapi masuk feedlot.',
                    ],
                    'berat_induksi' => [
                        'judul' => 'BRT INDCT',
                        'wajib' => false,
                        'padanan' => ['brt indct', 'berat induksi', 'brt induksi', 'bb induksi'],
                        'contoh' => '277',
                        'keterangan' => 'Bobot saat induksi, dalam kilogram.',
                    ],
                    'pen' => [
                        'judul' => 'PEN',
                        'wajib' => false,
                        'padanan' => ['pen', 'kandang'],
                        'contoh' => '610',
                        'keterangan' => 'Nomor kandang penempatan.',
                    ],
                    'gigi' => [
                        'judul' => 'GIGI',
                        'wajib' => false,
                        'padanan' => ['gigi', 'gigi tetap'],
                        'contoh' => 'I0',
                        'keterangan' => 'Perkiraan umur dari jumlah gigi tetap.',
                    ],
                    'frame' => [
                        'judul' => 'FRAME',
                        'wajib' => false,
                        'padanan' => ['frame', 'frame score'],
                        'contoh' => 'M',
                        'keterangan' => 'Ukuran rangka.',
                    ],
                    'kode_prop' => [
                        'judul' => 'KODE PROP',
                        'wajib' => false,
                        'padanan' => ['kode prop', 'kode property', 'pic'],
                        'contoh' => 'QABC123',
                        'keterangan' => 'Kode PIC properti asal.',
                    ],
                    'data_prop' => [
                        'judul' => 'DATA PROP',
                        'wajib' => false,
                        'padanan' => ['data prop', 'property', 'nama property'],
                        'contoh' => 'Brighton Downs',
                        'keterangan' => 'Nama properti asal.',
                    ],
                    'asal' => [
                        'judul' => 'Asal',
                        'wajib' => false,
                        'padanan' => ['asal', 'origin'],
                        'contoh' => 'NT',
                        'keterangan' => 'Daerah asal.',
                    ],
                    'jenis' => [
                        'judul' => 'JENIS',
                        'wajib' => false,
                        'padanan' => ['jenis', 'jenis sapi', 'breed'],
                        'contoh' => 'BX',
                        'keterangan' => 'Jenis atau bangsa sapi.',
                    ],
                ],
            ],

            self::REWEIGHT => [
                'nama' => 'Data Reweight',
                'lembar' => 'RWT',
                'kata_kunci_judul' => 'rfid',
                'kolom' => [
                    'rfid' => [
                        'judul' => 'RFID',
                        'wajib' => true,
                        'padanan' => ['rfid', 'no rfid', 'nomor rfid'],
                        'contoh' => '982000123456789',
                        'keterangan' => 'Harus sudah ada di data induksi shipment yang sama.',
                    ],
                    'tanggal_reweight' => [
                        'judul' => 'TGL REWEIGHT',
                        'wajib' => true,
                        'padanan' => ['tgl reweight', 'tanggal reweight', 'tgl rwt'],
                        'contoh' => '2026-05-20',
                        'keterangan' => 'Tanggal penimbangan ulang. Satu ekor tidak boleh ditimbang dua kali di tanggal sama.',
                    ],
                    'berat_reweight' => [
                        'judul' => 'BRT RWT',
                        'wajib' => false,
                        'padanan' => ['brt rwt', 'berat reweight', 'bb reweight'],
                        'contoh' => '341',
                        'keterangan' => 'Bobot saat ditimbang ulang, dalam kilogram.',
                    ],
                    'pen_awal' => [
                        'judul' => 'PEN INDUKSI',
                        'wajib' => false,
                        'padanan' => ['pen induksi', 'pen awal'],
                        'contoh' => '610',
                        'keterangan' => 'Kandang sebelum dipindah.',
                    ],
                    'pen_akhir' => [
                        'judul' => 'PEN AKHIR',
                        'wajib' => false,
                        'padanan' => ['pen akhir', 'pen rwt', 'pen baru'],
                        'contoh' => 'HP2',
                        'keterangan' => 'Kandang setelah dipindah.',
                    ],
                ],
            ],

            default => throw new InvalidArgumentException("Jenis impor '{$jenis}' tidak dikenal."),
        };
    }

    public static function semuaJenis(): array
    {
        return [self::INDUKSI, self::REWEIGHT];
    }

    /**
     * Cocokkan judul kolom dari berkas ke nama kolom baku.
     *
     * @param  array<string, mixed>  $barisMentah  kunci = nama kolom apa adanya (sudah dikecilkan)
     * @return array<string, mixed>  kunci = nama kolom baku
     */
    public static function petakan(string $jenis, array $barisMentah): array
    {
        $kolom = self::definisi($jenis)['kolom'];
        $hasil = [];

        foreach ($kolom as $baku => $aturan) {
            $hasil[$baku] = null;

            foreach ($aturan['padanan'] as $padanan) {
                if (array_key_exists($padanan, $barisMentah)) {
                    $hasil[$baku] = $barisMentah[$padanan];
                    break;
                }
            }
        }

        return $hasil;
    }

    /**
     * Kolom wajib yang tidak ditemukan di berkas.
     *
     * Diperiksa sekali di awal terhadap judulnya, bukan per baris — berkas
     * yang kolomnya memang tidak ada tidak perlu dibaca sampai habis dulu.
     *
     * @param  array<int, string>  $judulBerkas
     * @return array<int, string>
     */
    public static function kolomWajibHilang(string $jenis, array $judulBerkas): array
    {
        $kolom = self::definisi($jenis)['kolom'];
        $hilang = [];

        foreach ($kolom as $aturan) {
            if (! $aturan['wajib']) {
                continue;
            }

            $ketemu = array_intersect($aturan['padanan'], $judulBerkas) !== [];

            if (! $ketemu) {
                $hilang[] = $aturan['judul'];
            }
        }

        return $hilang;
    }
}
