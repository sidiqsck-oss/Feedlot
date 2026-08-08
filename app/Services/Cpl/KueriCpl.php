<?php

namespace App\Services\Cpl;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Pembangun query CPL — satu baris per ekor sapi.
 *
 * Dipakai bersama oleh dashboard, laporan, dan unduhan. Kalau masing-masing
 * punya query sendiri, cepat atau lambat ketiganya akan menyimpang tanpa ada
 * yang sadar — persis yang terjadi di sistem lama, di mana ADG RWT yang sama
 * tampil 1,624 di laporan Excel dan 1,943 di dashboard.
 *
 * Sengaja TIDAK memakai view database: hak CREATE VIEW belum tentu diberikan
 * di shared hosting, alasan yang sama seperti kartu stok tidak memakai trigger.
 *
 * Semua turunan menghasilkan NULL kalau bahannya tidak lengkap — bukan 0.
 * Nol adalah angka yang sah dan akan ikut merata-ratakan; kosong tidak.
 */
class KueriCpl
{
    /**
     * Query dasar: induksi sebagai tulang punggung, sisanya menempel.
     *
     * Reweight dan penjualan diambil yang TERAKHIR per ekor, sesuai perilaku
     * drop_duplicates(keep='last') di sistem lama.
     */
    public function dasar(): Builder
    {
        return DB::table('induksi')
            ->join('shipments', 'shipments.id', '=', 'induksi.shipment_id')

            // Pembelian dicocokkan lewat shipment + jenis, bukan per ekor.
            // Angkanya berlaku untuk seluruh rombongan.
            ->leftJoin('pembelian_shipment', function (JoinClause $j) {
                $j->on('pembelian_shipment.shipment_id', '=', 'induksi.shipment_id')
                    ->on('pembelian_shipment.jenis', '=', 'induksi.jenis');
            })

            ->leftJoin('properties', 'properties.kode', '=', 'induksi.kode_prop')

            ->leftJoin('reweight', function (JoinClause $j) {
                $j->on('reweight.id', '=', DB::raw(
                    '(select r2.id from reweight r2
                        where r2.induksi_id = induksi.id
                        order by r2.tanggal_reweight desc, r2.id desc
                        limit 1)'
                ));
            })

            ->leftJoin('penjualan', function (JoinClause $j) {
                $j->on('penjualan.id', '=', DB::raw(
                    '(select p2.id from penjualan p2
                        where p2.induksi_id = induksi.id
                        order by p2.tanggal desc, p2.id desc
                        limit 1)'
                ));
            })

            ->leftJoin('claim', function (JoinClause $j) {
                $j->on('claim.id', '=', DB::raw(
                    '(select c2.id from claim c2
                        where c2.induksi_id = induksi.id
                        order by c2.tanggal_kejadian desc, c2.id desc
                        limit 1)'
                ));
            });
    }

    /** Query dengan seluruh kolom CPL, termasuk turunannya. */
    public function lengkap(): Builder
    {
        return $this->dasar()->select([
            'induksi.id',
            'induksi.rfid',
            'induksi.ear_tag',
            'induksi.pen',
            'induksi.gigi',
            'induksi.frame',
            'induksi.asal',
            'induksi.kode_prop',
            'induksi.jenis',
            'induksi.tanggal_induksi',
            'induksi.berat_induksi',

            'shipments.id as shipment_id',
            'shipments.kode as shipment',

            'properties.nama as property',

            'pembelian_shipment.tanggal_muat',
            'pembelian_shipment.berat_muat',
            'pembelian_shipment.tanggal_tiba',
            'pembelian_shipment.berat_tiba',

            'reweight.tanggal_reweight',
            'reweight.berat_reweight',
            'reweight.pen_awal',
            'reweight.pen_akhir',

            'penjualan.tanggal as tanggal_jual',
            'penjualan.berat as berat_jual',
            'penjualan.customer',
            'penjualan.no_invoice',
            'penjualan.status_sapi',
            'penjualan.harga_per_kg',
            'penjualan.total as nilai_jual',

            'claim.id as claim_id',
            'claim.jenis_claim',
            'claim.fase as claim_fase',
            'claim.diagnosa',

            DB::raw($this->ekspresiStatus().' as status'),

            // ── Selisih bobot ────────────────────────────────────
            DB::raw($this->aman('induksi.berat_induksi - pembelian_shipment.berat_muat',
                ['induksi.berat_induksi', 'pembelian_shipment.berat_muat']).' as gain_loss_kg'),

            DB::raw($this->aman('penjualan.berat - induksi.berat_induksi',
                ['penjualan.berat', 'induksi.berat_induksi']).' as gain_kg'),

            // ── DOF ──────────────────────────────────────────────
            DB::raw($this->selisihHari('pembelian_shipment.tanggal_muat', 'penjualan.tanggal').' as dof_discharge'),
            DB::raw($this->selisihHari('induksi.tanggal_induksi', 'penjualan.tanggal').' as dof_induction'),
            DB::raw($this->selisihHari('induksi.tanggal_induksi', 'reweight.tanggal_reweight').' as dof_rwt'),
            DB::raw($this->selisihHari('reweight.tanggal_reweight', 'penjualan.tanggal').' as dof_jual'),

            // ── ADG ──────────────────────────────────────────────
            DB::raw($this->adg(
                'penjualan.berat - pembelian_shipment.berat_muat',
                $this->selisihHari('pembelian_shipment.tanggal_muat', 'penjualan.tanggal'),
                ['penjualan.berat', 'pembelian_shipment.berat_muat'],
            ).' as adg_discharge'),

            DB::raw($this->adg(
                'penjualan.berat - induksi.berat_induksi',
                $this->selisihHari('induksi.tanggal_induksi', 'penjualan.tanggal'),
                ['penjualan.berat', 'induksi.berat_induksi'],
            ).' as adg_induction'),

            DB::raw($this->adg(
                'reweight.berat_reweight - induksi.berat_induksi',
                $this->selisihHari('induksi.tanggal_induksi', 'reweight.tanggal_reweight'),
                ['reweight.berat_reweight', 'induksi.berat_induksi'],
            ).' as adg_rwt'),

            DB::raw($this->adg(
                'penjualan.berat - reweight.berat_reweight',
                $this->selisihHari('reweight.tanggal_reweight', 'penjualan.tanggal'),
                ['penjualan.berat', 'reweight.berat_reweight'],
            ).' as adg_jual'),
        ]);
    }

    /** Hanya sapi yang sudah terjual — dasar laporan CPL dan dashboard. */
    public function terjual(): Builder
    {
        return $this->lengkap()->whereNotNull('penjualan.id');
    }

    /** Sapi yang masih di kandang: sudah induksi, belum terjual, belum claim. */
    public function aktif(): Builder
    {
        return $this->lengkap()->whereNull('penjualan.id')->whereNull('claim.id');
    }

    /**
     * Terapkan penyaring. Dipakai sama persis oleh dashboard dan laporan,
     * sehingga keduanya tidak mungkin melihat kumpulan baris yang berbeda.
     */
    public function saring(Builder $q, array $saring): Builder
    {
        return $q
            ->when($saring['tanggal'] ?? null, fn ($q, $v) => $q->whereDate('penjualan.tanggal', $v))
            ->when($saring['dari'] ?? null, fn ($q, $v) => $q->whereDate('penjualan.tanggal', '>=', $v))
            ->when($saring['sampai'] ?? null, fn ($q, $v) => $q->whereDate('penjualan.tanggal', '<=', $v))
            ->when($saring['shipment'] ?? null, fn ($q, $v) => $q->where('shipments.kode', $v))
            ->when($saring['jenis'] ?? null, fn ($q, $v) => $q->whereIn('induksi.jenis', (array) $v))
            ->when($saring['property'] ?? null, fn ($q, $v) => $q->where('induksi.kode_prop', $v))
            ->when($saring['customer'] ?? null, fn ($q, $v) => $q->where('penjualan.customer', $v))
            ->when($saring['invoice'] ?? null, fn ($q, $v) => $q->where('penjualan.no_invoice', $v))
            ->when($saring['status'] ?? null, fn ($q, $v) => $q->whereIn('penjualan.status_sapi', (array) $v));
    }

    // ── Pembantu ekspresi SQL ───────────────────────────────────────

    /**
     * Selisih hari antar dua tanggal, NULL kalau salah satunya kosong.
     * MySQL dan SQLite memakai fungsi berbeda untuk ini.
     */
    private function selisihHari(string $dari, string $sampai): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "(julianday({$sampai}) - julianday({$dari}))"
            : "DATEDIFF({$sampai}, {$dari})";
    }

    /**
     * Bungkus ekspresi supaya menghasilkan NULL kalau ada bahan yang kosong.
     *
     * SQL sebenarnya sudah begitu, tapi ditulis eksplisit agar niatnya terbaca
     * dan tidak ada yang tergoda menambahkan COALESCE(..., 0) di kemudian hari.
     * Nol akan ikut merata-ratakan; kosong tidak.
     */
    private function aman(string $ekspresi, array $bahan): string
    {
        $syarat = implode(' AND ', array_map(fn ($k) => "{$k} IS NOT NULL", $bahan));

        return "(CASE WHEN {$syarat} THEN ({$ekspresi}) ELSE NULL END)";
    }

    /** ADG = selisih bobot ÷ jumlah hari, NULL kalau harinya nol atau kosong. */
    private function adg(string $selisihBobot, string $hari, array $bahan): string
    {
        $syarat = implode(' AND ', array_map(fn ($k) => "{$k} IS NOT NULL", $bahan));

        return "(CASE WHEN {$syarat} AND {$hari} IS NOT NULL AND {$hari} > 0
                 THEN ({$selisihBobot}) * 1.0 / ({$hari}) ELSE NULL END)";
    }

    /**
     * Status diturunkan dari kenyataan, bukan diketik — jadi tidak mungkin ada
     * sapi berstatus "terjual" yang tidak punya baris penjualan.
     */
    private function ekspresiStatus(): string
    {
        return "(CASE
                    WHEN claim.id IS NOT NULL THEN 'claim'
                    WHEN penjualan.id IS NOT NULL THEN 'terjual'
                    ELSE 'aktif'
                 END)";
    }
}
