<?php

namespace App\Support;

/**
 * Definisi kolom tabel CPL.
 *
 * Susunan, warna, dan cara meringkasnya diambil dari laporan yang dipakai
 * sekarang — tim sudah hafal bentuknya, dan mengubahnya cuma bikin orang
 * harus belajar ulang tanpa alasan.
 *
 * Dikumpulkan di satu tempat karena dipakai tiga hal sekaligus: tabel di
 * dashboard, laporan CPL Detail, dan berkas unduhannya.
 *
 * Arti kolom 'ringkas':
 *   sum        → dijumlah di baris Total
 *   avg        → dirata-rata biasa
 *   tertimbang → dihitung ulang dari total di baris Total (lihat AgregatCpl)
 *   null       → tidak diringkas
 */
class KolomCpl
{
    /**
     * @return array<int, array{
     *     kunci: string, judul: string, warna: string,
     *     format: string, ringkas: ?string, agregat: ?string, opsional: ?string
     * }>
     */
    public static function semua(): array
    {
        return [
            ['kunci' => '_no', 'judul' => 'No.', 'warna' => 'tengah', 'format' => 'teks', 'ringkas' => null, 'agregat' => null, 'opsional' => null],
            ['kunci' => 'shipment', 'judul' => "Csgn\nNo.", 'warna' => 'tengah', 'format' => 'teks', 'ringkas' => null, 'agregat' => null, 'opsional' => null],
            ['kunci' => 'ear_tag', 'judul' => "Eartag\nNo.", 'warna' => 'tengah', 'format' => 'teks', 'ringkas' => null, 'agregat' => null, 'opsional' => null],
            ['kunci' => 'rfid', 'judul' => 'RFID', 'warna' => 'tengah', 'format' => 'teks', 'ringkas' => null, 'agregat' => null, 'opsional' => 'rfid'],

            ['kunci' => 'tanggal_muat', 'judul' => "Load\nDate", 'warna' => 'polos', 'format' => 'tanggal', 'ringkas' => null, 'agregat' => null, 'opsional' => 'load_date'],
            ['kunci' => 'berat_muat', 'judul' => "Load Wt\n(Kg)", 'warna' => 'peach', 'format' => 'desimal', 'ringkas' => 'sum', 'agregat' => 'berat_muat', 'opsional' => null],

            ['kunci' => 'tanggal_tiba', 'judul' => "Feedlot\nDate", 'warna' => 'polos', 'format' => 'tanggal', 'ringkas' => null, 'agregat' => null, 'opsional' => null],
            ['kunci' => 'berat_tiba', 'judul' => "Feedlot Wt\n(Kg)", 'warna' => 'peach', 'format' => 'desimal', 'ringkas' => 'sum', 'agregat' => 'berat_tiba', 'opsional' => null],

            ['kunci' => 'gain_loss_kg', 'judul' => "Gain/Loss\n(Kg)", 'warna' => 'peach', 'format' => 'desimal', 'ringkas' => 'sum', 'agregat' => 'gain_loss_kg', 'opsional' => 'gain_loss'],

            ['kunci' => 'tanggal_induksi', 'judul' => "Induct\nDate", 'warna' => 'polos', 'format' => 'tanggal', 'ringkas' => null, 'agregat' => null, 'opsional' => null],
            ['kunci' => 'berat_induksi', 'judul' => "Induct Wt\n(Kg)", 'warna' => 'peach', 'format' => 'desimal', 'ringkas' => 'sum', 'agregat' => 'berat_induksi', 'opsional' => null],

            ['kunci' => 'tanggal_jual', 'judul' => "Exit\nDate", 'warna' => 'kuning', 'format' => 'tanggal', 'ringkas' => null, 'agregat' => null, 'opsional' => null],
            ['kunci' => 'berat_jual', 'judul' => "Exit Wt\n(Kg)", 'warna' => 'kuning', 'format' => 'desimal', 'ringkas' => 'sum', 'agregat' => 'berat_jual', 'opsional' => null],

            ['kunci' => 'dof_discharge', 'judul' => "DOF\nDisch", 'warna' => 'biru', 'format' => 'bulat', 'ringkas' => 'sum', 'agregat' => 'dof_discharge', 'opsional' => null],
            ['kunci' => 'adg_discharge', 'judul' => "ADG\nDisch", 'warna' => 'abu', 'format' => 'desimal', 'ringkas' => 'avg', 'agregat' => 'adg_discharge', 'opsional' => null],

            ['kunci' => 'dof_induction', 'judul' => "DOF\nInduct", 'warna' => 'biru', 'format' => 'bulat', 'ringkas' => 'sum', 'agregat' => 'dof_induction', 'opsional' => null],
            ['kunci' => 'adg_induction', 'judul' => "ADG\nInduct", 'warna' => 'abu', 'format' => 'desimal', 'ringkas' => 'tertimbang', 'agregat' => 'adg_induction', 'opsional' => null],

            ['kunci' => 'tanggal_reweight', 'judul' => "RWT\nDate", 'warna' => 'polos', 'format' => 'tanggal', 'ringkas' => null, 'agregat' => null, 'opsional' => 'rwt_date'],
            ['kunci' => 'berat_reweight', 'judul' => "RWT Wt\n(Kg)", 'warna' => 'peach', 'format' => 'desimal', 'ringkas' => 'sum', 'agregat' => 'berat_reweight', 'opsional' => 'rwt_wt'],
            ['kunci' => 'dof_rwt', 'judul' => "DOF\nRWT", 'warna' => 'biru', 'format' => 'bulat', 'ringkas' => 'sum', 'agregat' => 'dof_rwt', 'opsional' => 'dof_rwt'],
            ['kunci' => 'adg_rwt', 'judul' => "ADG\nRWT", 'warna' => 'peach', 'format' => 'desimal', 'ringkas' => 'tertimbang', 'agregat' => 'adg_rwt', 'opsional' => 'adg_rwt'],

            ['kunci' => 'dof_jual', 'judul' => "DOF\nJUAL", 'warna' => 'jual', 'format' => 'bulat', 'ringkas' => 'sum', 'agregat' => 'dof_jual', 'opsional' => 'dof_jual'],
            ['kunci' => 'adg_jual', 'judul' => "ADG\nJUAL", 'warna' => 'jual', 'format' => 'desimal', 'ringkas' => 'avg', 'agregat' => 'adg_jual', 'opsional' => 'adg_jual'],
            ['kunci' => 'selisih', 'judul' => "SELISIH\nRWT-JUAL", 'warna' => 'selisih', 'format' => 'desimal', 'ringkas' => 'avg', 'agregat' => 'selisih_rwt_jual', 'opsional' => 'selisih'],

            ['kunci' => 'frame', 'judul' => 'Frame', 'warna' => 'tengah', 'format' => 'teks', 'ringkas' => null, 'agregat' => null, 'opsional' => null],
            ['kunci' => 'jenis', 'judul' => 'cctype', 'warna' => 'tengah', 'format' => 'teks', 'ringkas' => null, 'agregat' => null, 'opsional' => null],
            ['kunci' => 'property', 'judul' => 'Property', 'warna' => 'kiri', 'format' => 'teks', 'ringkas' => null, 'agregat' => null, 'opsional' => 'detail_asal'],
            ['kunci' => 'customer', 'judul' => 'Cust', 'warna' => 'kiri', 'format' => 'teks', 'ringkas' => null, 'agregat' => null, 'opsional' => null],
        ];
    }

    /**
     * Kolom yang bisa disembunyikan, beserta labelnya.
     * Dibawa dari daftar "Personalisasi Tampilan" di Streamlit.
     *
     * @return array<string, string>
     */
    public static function opsional(): array
    {
        return [
            'rfid' => 'RFID',
            'load_date' => 'Load Date',
            'gain_loss' => 'Gain/Loss',
            'detail_asal' => 'Detail Asal',
            'rwt_date' => 'RWT Date',
            'rwt_wt' => 'RWT Wt (Kg)',
            'dof_rwt' => 'DOF RWT',
            'adg_rwt' => 'ADG RWT',
            'dof_jual' => 'DOF JUAL',
            'adg_jual' => 'ADG JUAL',
            'selisih' => 'SELISIH RWT-JUAL',
        ];
    }

    /**
     * Kolom yang benar-benar ditampilkan.
     *
     * @param  array<int, string>  $disembunyikan  kunci dari opsional()
     */
    public static function tampil(array $disembunyikan = []): array
    {
        return array_values(array_filter(
            self::semua(),
            fn ($k) => $k['opsional'] === null || ! in_array($k['opsional'], $disembunyikan, true),
        ));
    }

    /** Semua kolom opsional tersembunyi — bawaan yang sama seperti di Streamlit. */
    public static function bawaanDisembunyikan(): array
    {
        return array_keys(self::opsional());
    }
}
