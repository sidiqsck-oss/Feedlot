<?php

namespace App\Services\Cpl;

use Illuminate\Support\Collection;

/**
 * Meringkas sekumpulan baris CPL jadi satu set angka.
 *
 * DUA ATURAN YANG MENENTUKAN SEGALANYA:
 *
 * 1. ATURAN POPULASI
 *    Setiap agregat hanya memakai ekor yang SEMUA bahannya lengkap. Untuk ADG
 *    RWT, sapi tanpa data reweight keluar dari ketiga penjumlahan sekaligus —
 *    berat reweight, berat induksi, maupun DOF. Bukan cuma dari penyebutnya.
 *
 *    Laporan lama melanggar ini: pembilangnya menjumlah berat induksi SELURUH
 *    1.046 ekor lalu dikurangi berat reweight 960 ekor yang punya datanya.
 *    Dua populasi berbeda dikurangkan, hasilnya 1,624 padahal 2,126.
 *
 * 2. CAMPURAN TERTIMBANG DAN SEDERHANA
 *    Mengikuti laporan lama apa adanya, karena campuran itu memang disengaja:
 *
 *      tertimbang        : ADG Induction, ADG RWT, Gain/Loss %, Gain %
 *      rata-rata biasa   : ADG Discharge, ADG JUAL, SELISIH RWT-JUAL
 *
 * Tiap angka selalu membawa `n` — berapa ekor yang jadi dasarnya — supaya
 * ketahuan kalau datanya tipis.
 */
class AgregatCpl
{
    public function __construct(private readonly Collection $baris) {}

    public static function dari(Collection $baris): self
    {
        return new self($baris);
    }

    public function jumlahEkor(): int
    {
        return $this->baris->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function semua(): array
    {
        return [
            'ekor' => $this->jumlahEkor(),

            // ── Bobot: dijumlah dan dirata-rata biasa ────────────
            'berat_muat' => $this->rataRata('berat_muat'),
            'berat_induksi' => $this->rataRata('berat_induksi'),
            'berat_reweight' => $this->rataRata('berat_reweight'),
            'berat_jual' => $this->rataRata('berat_jual'),
            'total_berat_jual' => $this->jumlah('berat_jual'),
            'gain_kg' => $this->rataRata('gain_kg'),
            'total_gain_kg' => $this->jumlah('gain_kg'),
            'gain_loss_kg' => $this->rataRata('gain_loss_kg'),

            // ── DOF: dirata-rata biasa ───────────────────────────
            'dof_discharge' => $this->rataRata('dof_discharge'),
            'dof_induction' => $this->rataRata('dof_induction'),
            'dof_rwt' => $this->rataRata('dof_rwt'),
            'dof_jual' => $this->rataRata('dof_jual'),

            // ── ADG tertimbang ──────────────────────────────────
            'adg_induction' => $this->adgTertimbang('berat_jual', 'berat_induksi', 'dof_induction'),
            'adg_rwt' => $this->adgTertimbang('berat_reweight', 'berat_induksi', 'dof_rwt'),

            // ── ADG rata-rata biasa ─────────────────────────────
            'adg_discharge' => $this->rataRata('adg_discharge'),
            'adg_jual' => $this->rataRata('adg_jual'),
            'selisih_rwt_jual' => $this->selisihRwtJual(),

            // ── Persentase tertimbang ───────────────────────────
            'gain_persen' => $this->persenTertimbang('gain_kg', 'berat_induksi'),
            'gain_loss_persen' => $this->persenTertimbang('gain_loss_kg', 'berat_muat'),

            // ── Melambat pasca reweight ─────────────────────────
            'melambat' => $this->melambat(),
        ];
    }

    /**
     * ADG tertimbang: (Σ bobot akhir − Σ bobot awal) ÷ Σ hari.
     *
     * Ketiga penjumlahan berjalan di atas populasi yang SAMA — hanya ekor yang
     * ketiga nilainya ada. Inilah yang membetulkan kesalahan di laporan lama.
     *
     * @return array{nilai: ?float, n: int}
     */
    public function adgTertimbang(string $akhir, string $awal, string $hari): array
    {
        $layak = $this->baris->filter(
            fn ($b) => $this->ada($b, $akhir) && $this->ada($b, $awal) && $this->positif($b, $hari)
        );

        if ($layak->isEmpty()) {
            return ['nilai' => null, 'n' => 0];
        }

        $totalHari = $layak->sum(fn ($b) => (float) $b->{$hari});

        if ($totalHari <= 0) {
            return ['nilai' => null, 'n' => $layak->count()];
        }

        $selisih = $layak->sum(fn ($b) => (float) $b->{$akhir})
                 - $layak->sum(fn ($b) => (float) $b->{$awal});

        return ['nilai' => $selisih / $totalHari, 'n' => $layak->count()];
    }

    /** Persentase tertimbang: Σ pembilang ÷ Σ penyebut × 100. */
    public function persenTertimbang(string $pembilang, string $penyebut): array
    {
        $layak = $this->baris->filter(
            fn ($b) => $this->ada($b, $pembilang) && $this->ada($b, $penyebut)
        );

        $bawah = $layak->sum(fn ($b) => (float) $b->{$penyebut});

        if ($layak->isEmpty() || $bawah == 0.0) {
            return ['nilai' => null, 'n' => $layak->count()];
        }

        $atas = $layak->sum(fn ($b) => (float) $b->{$pembilang});

        return ['nilai' => $atas / $bawah * 100, 'n' => $layak->count()];
    }

    /**
     * Rata-rata biasa, mengabaikan yang kosong.
     *
     * Yang kosong TIDAK diperlakukan sebagai nol — itu persis kesalahan yang
     * membuat ADG RWT di dashboard lama tampil 1,943 alih-alih 2,117.
     */
    public function rataRata(string $kolom): array
    {
        $nilai = $this->nilaiAda($kolom);

        return [
            'nilai' => $nilai->isEmpty() ? null : $nilai->sum() / $nilai->count(),
            'n' => $nilai->count(),
        ];
    }

    public function jumlah(string $kolom): array
    {
        $nilai = $this->nilaiAda($kolom);

        return [
            'nilai' => $nilai->isEmpty() ? null : $nilai->sum(),
            'n' => $nilai->count(),
        ];
    }

    /**
     * SELISIH RWT-JUAL: rata-rata biasa dari selisih per ekor.
     *
     * Hanya ekor yang punya kedua ADG-nya yang ikut — merata-ratakan selisih
     * dari ekor yang salah satu ADG-nya kosong tidak berarti apa-apa.
     */
    public function selisihRwtJual(): array
    {
        $layak = $this->baris->filter(
            fn ($b) => $this->ada($b, 'adg_jual') && $this->ada($b, 'adg_rwt')
        );

        if ($layak->isEmpty()) {
            return ['nilai' => null, 'n' => 0];
        }

        $total = $layak->sum(fn ($b) => (float) $b->adg_jual - (float) $b->adg_rwt);

        return ['nilai' => $total / $layak->count(), 'n' => $layak->count()];
    }

    /**
     * Berapa ekor yang ADG-nya menurun setelah reweight.
     *
     * Dibawa dari kartu "Melambat pasca RWT" di dashboard HTML.
     */
    public function melambat(): array
    {
        $punyaKeduanya = $this->baris->filter(
            fn ($b) => $this->ada($b, 'adg_jual') && $this->ada($b, 'adg_rwt')
        );

        $turun = $punyaKeduanya->filter(fn ($b) => (float) $b->adg_jual < (float) $b->adg_rwt);

        return [
            'jumlah' => $turun->count(),
            'n' => $punyaKeduanya->count(),
            'persen' => $punyaKeduanya->isEmpty()
                ? null
                : $turun->count() / $punyaKeduanya->count() * 100,
        ];
    }

    /**
     * Pecah per kolom lalu ringkas masing-masing.
     * Dipakai tabel pembanding di dashboard: per shipment, property, jenis, customer.
     *
     * @return Collection<string, array>
     */
    public function kelompok(string $kolom): Collection
    {
        return $this->baris
            ->groupBy(fn ($b) => $b->{$kolom} ?? '(tanpa '.$kolom.')')
            ->map(fn ($grup) => self::dari($grup)->semua())
            ->sortKeys();
    }

    // ── Pembantu ────────────────────────────────────────────────────

    private function nilaiAda(string $kolom): Collection
    {
        return $this->baris
            ->filter(fn ($b) => $this->ada($b, $kolom))
            ->map(fn ($b) => (float) $b->{$kolom})
            ->values();
    }

    private function ada(object $baris, string $kolom): bool
    {
        return ($baris->{$kolom} ?? null) !== null && $baris->{$kolom} !== '';
    }

    private function positif(object $baris, string $kolom): bool
    {
        return $this->ada($baris, $kolom) && (float) $baris->{$kolom} > 0;
    }
}
