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
    public const PROPERTY = 'property';
    public const PEMBELIAN = 'pembelian';
    public const PENJUALAN = 'penjualan';

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
                'per_shipment' => true,
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
                'per_shipment' => true,
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

            // ── Property (PIC NT.xlsx) ──────────────────────────
            self::PROPERTY => [
                'nama' => 'Data Property',
                'lembar' => null,
                'kata_kunci_judul' => 'pic',
                'per_shipment' => false,
                'kolom' => [
                    'kode' => [
                        'judul' => 'PIC',
                        'wajib' => true,
                        'padanan' => ['pic', 'kode pic', 'kode prop'],
                        'contoh' => 'QABC123',
                        'keterangan' => 'Kode PIC properti. Inilah yang menyambungkan properti ke data induksi.',
                    ],
                    'nama' => [
                        'judul' => 'Property Name / Holding',
                        'wajib' => true,
                        'padanan' => ['property name / holding', 'property name', 'holding', 'nama property'],
                        'contoh' => 'Brighton Downs',
                        'keterangan' => 'Nama properti asal sapi.',
                    ],
                    'region' => [
                        'judul' => 'Region',
                        'wajib' => false,
                        'padanan' => ['region', 'wilayah'],
                        'contoh' => 'Northern Territory',
                        'keterangan' => 'Wilayah besar tempat properti berada.',
                    ],
                    'lga' => [
                        'judul' => 'Local Government Area (LGA)',
                        'wajib' => false,
                        'padanan' => ['local government area (lga)', 'local government area', 'lga'],
                        'contoh' => 'Barkly',
                        'keterangan' => 'Dipakai sebagai "Daerah" di berkas pembelian.',
                    ],
                ],
            ],

            // ── Pembelian per shipment (Cattle Performance Log.xlsx) ──
            self::PEMBELIAN => [
                'nama' => 'Pembelian per Shipment',
                'lembar' => 'Base Berat INV&Feedlot',
                'kata_kunci_judul' => 'ship',
                'per_shipment' => false,
                'kolom' => [
                    'shipment' => [
                        'judul' => 'Ship',
                        'wajib' => true,
                        'padanan' => ['ship', 'shipment', 'csgn no.'],
                        'contoh' => '90',
                        'keterangan' => 'Cukup angkanya — 90 otomatis jadi SCK90.',
                    ],
                    'jenis' => [
                        'judul' => 'Jenis',
                        'wajib' => true,
                        'padanan' => ['jenis', 'cctype'],
                        'contoh' => 'Steer',
                        'keterangan' => 'Bersama Ship, pasangan ini yang menyambungkan pembelian ke tiap ekor.',
                    ],
                    'total_jenis' => [
                        'judul' => 'Total Jenis',
                        'wajib' => false,
                        'padanan' => ['total jenis', 'total', 'jumlah'],
                        'contoh' => '120',
                        'keterangan' => 'Jumlah ekor yang tiba. Ini titik awal corong shipment di dashboard.',
                    ],
                    'tanggal_muat' => [
                        'judul' => 'Tanggal',
                        'wajib' => false,
                        'padanan' => ['tanggal', 'load date', 'tanggal sandar'],
                        'contoh' => '2026-01-05',
                        'keterangan' => 'Tanggal kapal sandar di pelabuhan.',
                    ],
                    'berat_muat' => [
                        'judul' => 'Shipping',
                        'wajib' => false,
                        'padanan' => ['shipping', 'load wt (kg)', 'berat shipping'],
                        'contoh' => '333.4',
                        'keterangan' => 'Berat rata-rata per ekor saat sandar, dalam kilogram.',
                    ],
                    'tanggal_tiba' => [
                        'judul' => 'Tanggal2',
                        'wajib' => false,
                        'padanan' => ['tanggal2', 'feedlot date', 'tanggal feedlot'],
                        'contoh' => '2026-01-08',
                        'keterangan' => 'Tanggal sampai di feedlot.',
                    ],
                    'berat_tiba' => [
                        'judul' => 'Feedlot (Kg)',
                        'wajib' => false,
                        'padanan' => ['feedlot (kg)', 'feedlot', 'feedlot wt (kg)'],
                        'contoh' => '326.0',
                        'keterangan' => 'Berat rata-rata per ekor saat tiba di feedlot.',
                    ],
                    'importir' => [
                        'judul' => 'Importir',
                        'wajib' => false,
                        'padanan' => ['importir', 'importer'],
                        'contoh' => 'PT Importir Sejahtera',
                        'keterangan' => 'Pihak yang mengimpor rombongan ini.',
                    ],
                    'salvage_jumlah' => [
                        'judul' => 'Salvage (Jumlah)',
                        'wajib' => false,
                        'padanan' => ['salvage (jumlah)', 'salvage jumlah', 'salvage'],
                        'contoh' => '3',
                        'keterangan' => 'Rekapan salvage tingkat rombongan, terpisah dari catatan claim per ekor.',
                    ],
                    'salvage_persen' => [
                        'judul' => 'Salvage %',
                        'wajib' => false,
                        'padanan' => ['salvage %', 'salvage persen'],
                        'contoh' => '2.5',
                        'keterangan' => '',
                    ],
                    'mati_jumlah' => [
                        'judul' => 'Mati',
                        'wajib' => false,
                        'padanan' => ['mati', 'mati jumlah', 'mortality'],
                        'contoh' => '2',
                        'keterangan' => 'Rekapan kematian tingkat rombongan.',
                    ],
                    'mati_persen' => [
                        'judul' => 'Mati %',
                        'wajib' => false,
                        'padanan' => ['mati %', 'mati persen'],
                        'contoh' => '1.7',
                        'keterangan' => '',
                    ],
                    'bunting_jumlah' => [
                        'judul' => 'Bunting Jumlah',
                        'wajib' => false,
                        'padanan' => ['bunting jumlah', 'bunting'],
                        'contoh' => '1',
                        'keterangan' => '',
                    ],
                    'bunting_persen' => [
                        'judul' => 'Bunting %',
                        'wajib' => false,
                        'padanan' => ['bunting %', 'bunting persen'],
                        'contoh' => '0.8',
                        'keterangan' => '',
                    ],
                    'harga_usd' => [
                        'judul' => 'Harga (USD)',
                        'wajib' => false,
                        'padanan' => ['harga (usd)', 'harga usd', 'price (usd)'],
                        'contoh' => '3.15',
                        'keterangan' => 'Harga beli dalam dolar.',
                    ],
                    'daerah' => [
                        'judul' => 'Daerah',
                        'wajib' => false,
                        'padanan' => ['daerah', 'lga', 'local government area'],
                        'contoh' => 'Barkly',
                        'keterangan' => 'Diambil dari kolom LGA di PIC NT.',
                    ],
                ],
            ],

            // ── Penjualan (SJ INV SCK.xlsm, sheet Transaksi) ────
            self::PENJUALAN => [
                'nama' => 'Data Penjualan',
                'lembar' => 'Transaksi',
                'kata_kunci_judul' => 'rfid',
                'per_shipment' => false,
                'kolom' => [
                    'rfid' => [
                        'judul' => 'Nomor RFID',
                        'wajib' => true,
                        'padanan' => ['nomor rfid', 'rfid', 'no rfid'],
                        'contoh' => '982000123456789',
                        'keterangan' => 'Harus sudah ada di data induksi. Inilah penyambung ke sapinya.',
                    ],
                    'shipment' => [
                        'judul' => 'Ship',
                        'wajib' => true,
                        'padanan' => ['ship', 'shipment'],
                        'contoh' => '90',
                        'keterangan' => 'Bersama RFID menentukan sapi yang dimaksud.',
                    ],
                    'tanggal' => [
                        'judul' => 'Tanggal',
                        'wajib' => true,
                        'padanan' => ['tanggal', 'exit date', 'tgl jual'],
                        'contoh' => '2026-06-01',
                        'keterangan' => 'Tanggal penjualan, dipakai sebagai Exit Date di laporan CPL.',
                    ],
                    'berat' => [
                        'judul' => 'Jumlah Berat',
                        'wajib' => false,
                        'padanan' => ['jumlah berat', 'exit wt (kg)', 'berat'],
                        'contoh' => '520.5',
                        'keterangan' => 'Bobot saat dijual, dalam kilogram.',
                    ],
                    'satuan' => [
                        'judul' => 'Satuan',
                        'wajib' => false,
                        'padanan' => ['satuan', 'unit'],
                        'contoh' => 'Kg',
                        'keterangan' => '',
                    ],
                    'customer' => [
                        'judul' => 'Cust',
                        'wajib' => false,
                        'padanan' => ['cust', 'customer', 'pelanggan'],
                        'contoh' => 'PT Berkah Daging',
                        'keterangan' => 'Laporan CPL dipecah per customer.',
                    ],
                    'kode_customer' => [
                        'judul' => 'Kode Cust',
                        'wajib' => false,
                        'padanan' => ['kode cust', 'kode customer'],
                        'contoh' => 'C-014',
                        'keterangan' => '',
                    ],
                    'no_invoice' => [
                        'judul' => 'No Invoice',
                        'wajib' => false,
                        'padanan' => ['no invoice', 'invoice', 'inv'],
                        'contoh' => '0091/INV-SCK/V/26',
                        'keterangan' => 'Dipakai sebagai penyaring dan pengelompokan laporan.',
                    ],
                    'no_surat_jalan' => [
                        'judul' => 'No Surat Jalan',
                        'wajib' => false,
                        'padanan' => ['no surat jalan', 'surat jalan', 'no sj'],
                        'contoh' => 'SJ-0091/26',
                        'keterangan' => '',
                    ],
                    'nama_barang' => [
                        'judul' => 'Nama Barang',
                        'wajib' => false,
                        'padanan' => ['nama barang', 'barang'],
                        'contoh' => 'Sapi Bakalan Steer',
                        'keterangan' => '',
                    ],
                    'harga_per_kg' => [
                        'judul' => 'Harga',
                        'wajib' => false,
                        'padanan' => ['harga', 'harga per kg', 'price'],
                        'contoh' => '52000',
                        'keterangan' => 'Harga per kilogram.',
                    ],
                    'realisasi' => [
                        'judul' => 'Realisasi',
                        'wajib' => false,
                        'padanan' => ['realisasi'],
                        'contoh' => '27060000',
                        'keterangan' => '',
                    ],
                    'total' => [
                        'judul' => 'Total',
                        'wajib' => false,
                        'padanan' => ['total'],
                        'contoh' => '27066000',
                        'keterangan' => '',
                    ],
                    'potongan' => [
                        'judul' => 'Potongan',
                        'wajib' => false,
                        'padanan' => ['potongan', 'diskon'],
                        'contoh' => '0',
                        'keterangan' => '',
                    ],
                    'status_sapi' => [
                        'judul' => 'Status Sapi',
                        'wajib' => false,
                        'padanan' => ['status sapi', 'status'],
                        'contoh' => 'Sehat',
                        'keterangan' => 'Sehat atau Salvage.',
                    ],
                    'jenis' => [
                        'judul' => 'Jenis',
                        'wajib' => false,
                        'padanan' => ['jenis', 'cctype'],
                        'contoh' => 'Steer',
                        'keterangan' => '',
                    ],
                ],
            ],

            default => throw new InvalidArgumentException("Jenis impor '{$jenis}' tidak dikenal."),
        };
    }

    public static function semuaJenis(): array
    {
        return [
            self::PROPERTY,
            self::PEMBELIAN,
            self::INDUKSI,
            self::REWEIGHT,
            self::PENJUALAN,
        ];
    }

    /**
     * Jenis yang berkasnya milik satu shipment tertentu.
     *
     * Property dan pembelian mencakup banyak shipment sekaligus, jadi tidak
     * perlu — dan tidak boleh — diminta memilih shipment saat mengunggah.
     * Penjualan membawa kolom Ship-nya sendiri.
     */
    public static function perShipment(string $jenis): bool
    {
        return self::definisi($jenis)['per_shipment'] ?? true;
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
