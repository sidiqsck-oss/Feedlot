<?php

namespace App\Services;

use App\Models\AlokasiLot;
use App\Models\Barang;
use App\Models\Opname;
use App\Models\Penerimaan;
use App\Models\PenerimaanItem;
use App\Models\Pengeluaran;
use App\Models\PengeluaranItem;
use App\Models\PergerakanStok;
use App\Models\StokLot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mesin stok: FIFO, kartu stok, dan opname.
 *
 * Semua yang mengubah stok lewat sini. Tidak ada satu pun tempat lain yang
 * boleh menulis ke pergerakan_stok atau menyentuh qty_sisa lot, supaya
 * aturan FIFO cuma ada di satu berkas dan bisa diuji sekaligus.
 *
 * Dua aturan yang dijaga ketat:
 *
 *  1. pergerakan_stok hanya ditambah — koreksi dilakukan dengan baris
 *     berlawanan, bukan dengan mengubah atau menghapus baris lama.
 *  2. Semua operasi dibungkus transaksi dan mengunci baris lot yang dipakai,
 *     supaya dua input bersamaan tidak sama-sama mengambil stok yang sama.
 */
class StokService
{
    /**
     * Catat penerimaan barang: bikin lot FIFO baru dan tulis kartu stok.
     *
     * @param  array<int, array{barang_id:int, qty:float, harga_satuan:float}>  $items
     */
    public function catatPenerimaan(Penerimaan $penerimaan, array $items, User $user): Penerimaan
    {
        if ($items === []) {
            throw new RuntimeException('Nota penerimaan harus berisi minimal satu barang.');
        }

        return DB::transaction(function () use ($penerimaan, $items, $user) {
            foreach ($items as $baris) {
                $qty = (float) $baris['qty'];
                $harga = (float) $baris['harga_satuan'];

                if ($qty <= 0) {
                    throw new RuntimeException('Jumlah barang masuk harus lebih dari nol.');
                }

                if ($harga < 0) {
                    throw new RuntimeException('Harga satuan tidak boleh negatif.');
                }

                $item = PenerimaanItem::create([
                    'penerimaan_id' => $penerimaan->id,
                    'purchase_order_item_id' => $baris['purchase_order_item_id'] ?? null,
                    'barang_id' => $baris['barang_id'],
                    'qty' => $qty,
                    'harga_satuan' => $harga,
                    'subtotal' => $qty * $harga,
                ]);

                $lot = StokLot::create([
                    'barang_id' => $item->barang_id,
                    'penerimaan_item_id' => $item->id,
                    'tanggal_masuk' => $penerimaan->tanggal,
                    'harga_satuan' => $harga,
                    'qty_masuk' => $qty,
                    'qty_sisa' => $qty,
                ]);

                PergerakanStok::create([
                    'barang_id' => $item->barang_id,
                    'tanggal' => $penerimaan->tanggal,
                    'tipe' => 'masuk',
                    'qty' => $qty,
                    'harga_satuan' => $harga,
                    'nilai' => $qty * $harga,
                    'stok_lot_id' => $lot->id,
                    'sumber_type' => Penerimaan::class,
                    'sumber_id' => $penerimaan->id,
                    'keterangan' => $penerimaan->nomor,
                    'dibuat_oleh' => $user->id,
                ]);

                // Kalau nota ini memenuhi PO, akumulasi qty_diterima-nya naik.
                if ($item->purchase_order_item_id) {
                    $poItem = $item->purchaseOrderItem()->lockForUpdate()->first();
                    $poItem->increment('qty_diterima', $qty);
                }
            }

            return $penerimaan->fresh('items');
        });
    }

    /**
     * Catat pengeluaran barang dengan alokasi FIFO.
     *
     * Setiap baris mengambil dari lot tertua yang masih bersisa. Satu baris
     * bisa memakan beberapa lot dengan harga berbeda — rinciannya disimpan di
     * alokasi_lot supaya angkanya bisa ditelusuri, bukan cuma hasil akhirnya.
     *
     * @param  array<int, array{barang_id:int, qty:float}>  $items
     */
    public function catatPengeluaran(Pengeluaran $pengeluaran, array $items, User $user): Pengeluaran
    {
        if ($items === []) {
            throw new RuntimeException('Nota pengeluaran harus berisi minimal satu barang.');
        }

        return DB::transaction(function () use ($pengeluaran, $items, $user) {
            foreach ($items as $baris) {
                $barang = Barang::findOrFail($baris['barang_id']);
                $qtyDiminta = (float) $baris['qty'];

                if ($qtyDiminta <= 0) {
                    throw new RuntimeException('Jumlah barang keluar harus lebih dari nol.');
                }

                $item = PengeluaranItem::create([
                    'pengeluaran_id' => $pengeluaran->id,
                    'barang_id' => $barang->id,
                    'qty' => $qtyDiminta,
                    'nilai_hpp' => 0,
                ]);

                $nilaiHpp = $this->alokasiFifo($item, $barang, $qtyDiminta, $pengeluaran, $user);

                $item->update(['nilai_hpp' => $nilaiHpp]);
            }

            return $pengeluaran->fresh('items');
        });
    }

    /**
     * Ambil qty dari lot tertua ke termuda, tulis alokasi dan kartu stok.
     *
     * Mengembalikan total nilai HPP baris ini.
     */
    private function alokasiFifo(
        PengeluaranItem $item,
        Barang $barang,
        float $qtyDiminta,
        Pengeluaran $pengeluaran,
        User $user,
    ): float {
        // Kunci semua lot barang ini lebih dulu. Tanpa ini, dua pengeluaran
        // bersamaan bisa sama-sama melihat sisa yang sama lalu dua-duanya
        // lolos — stok jadi minus tanpa ada yang salah input.
        $lots = StokLot::tersedia($barang->id)->lockForUpdate()->get();

        $tersedia = (float) $lots->sum('qty_sisa');

        if ($tersedia < $qtyDiminta) {
            throw new RuntimeException(sprintf(
                'Stok %s tidak cukup. Diminta %s %s, tersedia %s %s.',
                $barang->nama,
                rtrim(rtrim(number_format($qtyDiminta, 3, ',', '.'), '0'), ','),
                $barang->satuan,
                rtrim(rtrim(number_format($tersedia, 3, ',', '.'), '0'), ','),
                $barang->satuan,
            ));
        }

        $sisaDiminta = $qtyDiminta;
        $nilaiHpp = 0.0;

        foreach ($lots as $lot) {
            if ($sisaDiminta <= 0) {
                break;
            }

            $ambil = min($sisaDiminta, (float) $lot->qty_sisa);

            if ($ambil <= 0) {
                continue;
            }

            $harga = (float) $lot->harga_satuan;
            $subtotal = $ambil * $harga;

            AlokasiLot::create([
                'pengeluaran_item_id' => $item->id,
                'stok_lot_id' => $lot->id,
                'qty' => $ambil,
                'harga_satuan' => $harga,
                'subtotal' => $subtotal,
            ]);

            $lot->decrement('qty_sisa', $ambil);

            PergerakanStok::create([
                'barang_id' => $barang->id,
                'tanggal' => $pengeluaran->tanggal,
                'tipe' => 'keluar',
                'qty' => -$ambil,
                'harga_satuan' => $harga,
                'nilai' => -$subtotal,
                'stok_lot_id' => $lot->id,
                'sumber_type' => Pengeluaran::class,
                'sumber_id' => $pengeluaran->id,
                'keterangan' => $pengeluaran->nomor,
                'dibuat_oleh' => $user->id,
            ]);

            $nilaiHpp += $subtotal;
            $sisaDiminta -= $ambil;
        }

        return $nilaiHpp;
    }

    /**
     * Finalkan opname: tulis selisih fisik vs sistem ke kartu stok.
     *
     * Selisih kurang mengurangi lot dari yang tertua (barang yang hilang
     * dianggap yang paling lama ada). Selisih lebih dicatat sebagai lot baru
     * dengan harga rata-rata persediaan saat itu, karena barang yang muncul
     * tanpa nota tidak punya harga beli.
     */
    public function finalkanOpname(Opname $opname, User $user): Opname
    {
        if ($opname->sudahFinal()) {
            throw new RuntimeException('Opname ini sudah difinalkan dan tidak bisa diulang.');
        }

        return DB::transaction(function () use ($opname, $user) {
            foreach ($opname->items()->with('barang')->get() as $item) {
                if ($item->stok_fisik === null) {
                    throw new RuntimeException(
                        "Stok fisik {$item->barang->nama} belum diisi. Lengkapi dulu sebelum difinalkan."
                    );
                }

                $selisih = (float) $item->stok_fisik - (float) $item->stok_sistem;

                if (abs($selisih) < 0.0005) {
                    $item->update(['selisih' => 0, 'nilai_selisih' => 0]);

                    continue;
                }

                $nilai = $selisih < 0
                    ? $this->kurangiLot($item->barang, abs($selisih), $opname, $user)
                    : $this->tambahLotOpname($item->barang, $selisih, $opname, $user);

                $item->update([
                    'selisih' => $selisih,
                    'nilai_selisih' => $nilai,
                ]);
            }

            $opname->update([
                'status' => 'final',
                'difinalkan_pada' => now(),
            ]);

            return $opname->fresh('items');
        });
    }

    /** Selisih kurang: ambil dari lot tertua, seperti pengeluaran biasa. */
    private function kurangiLot(Barang $barang, float $qty, Opname $opname, User $user): float
    {
        $lots = StokLot::tersedia($barang->id)->lockForUpdate()->get();
        $sisa = $qty;
        $nilai = 0.0;

        foreach ($lots as $lot) {
            if ($sisa <= 0) {
                break;
            }

            $ambil = min($sisa, (float) $lot->qty_sisa);

            if ($ambil <= 0) {
                continue;
            }

            $harga = (float) $lot->harga_satuan;
            $lot->decrement('qty_sisa', $ambil);

            PergerakanStok::create([
                'barang_id' => $barang->id,
                'tanggal' => $opname->tanggal,
                'tipe' => 'opname',
                'qty' => -$ambil,
                'harga_satuan' => $harga,
                'nilai' => -($ambil * $harga),
                'stok_lot_id' => $lot->id,
                'sumber_type' => Opname::class,
                'sumber_id' => $opname->id,
                'keterangan' => "Selisih kurang opname {$opname->nomor}",
                'dibuat_oleh' => $user->id,
            ]);

            $nilai -= $ambil * $harga;
            $sisa -= $ambil;
        }

        // Fisik lebih banyak dari yang bisa dikurangi dari lot berarti kartu
        // stok dan lot sudah tidak sinkron. Lebih baik berhenti dan minta
        // diperiksa daripada diam-diam menulis angka yang salah.
        if ($sisa > 0.0005) {
            throw new RuntimeException(
                "Selisih opname {$barang->nama} melebihi sisa lot yang ada. ".
                'Kartu stok dan lot tidak sinkron — perlu diperiksa manual.'
            );
        }

        return $nilai;
    }

    /** Selisih lebih: lot baru dengan harga rata-rata persediaan saat ini. */
    private function tambahLotOpname(Barang $barang, float $qty, Opname $opname, User $user): float
    {
        $harga = $this->hargaRataRata($barang);

        $lot = StokLot::create([
            'barang_id' => $barang->id,
            'penerimaan_item_id' => null,
            'tanggal_masuk' => $opname->tanggal,
            'harga_satuan' => $harga,
            'qty_masuk' => $qty,
            'qty_sisa' => $qty,
        ]);

        PergerakanStok::create([
            'barang_id' => $barang->id,
            'tanggal' => $opname->tanggal,
            'tipe' => 'opname',
            'qty' => $qty,
            'harga_satuan' => $harga,
            'nilai' => $qty * $harga,
            'stok_lot_id' => $lot->id,
            'sumber_type' => Opname::class,
            'sumber_id' => $opname->id,
            'keterangan' => "Selisih lebih opname {$opname->nomor}",
            'dibuat_oleh' => $user->id,
        ]);

        return $qty * $harga;
    }

    /**
     * Harga rata-rata tertimbang dari lot yang masih bersisa.
     * Kalau stok kosong, pakai harga beli terakhir yang pernah tercatat.
     */
    public function hargaRataRata(Barang $barang): float
    {
        $lots = StokLot::where('barang_id', $barang->id)->where('qty_sisa', '>', 0)->get();
        $qty = (float) $lots->sum('qty_sisa');

        if ($qty > 0) {
            $nilai = $lots->sum(fn ($l) => (float) $l->qty_sisa * (float) $l->harga_satuan);

            return $nilai / $qty;
        }

        return (float) StokLot::where('barang_id', $barang->id)
            ->orderByDesc('tanggal_masuk')
            ->orderByDesc('id')
            ->value('harga_satuan') ?? 0.0;
    }

    /**
     * Koreksi manual. Satu-satunya cara membetulkan angka yang salah —
     * baris lama tetap ada, yang bertambah adalah baris penyeimbang.
     */
    public function catatKoreksi(
        Barang $barang,
        float $qty,
        string $alasan,
        User $user,
        ?string $tanggal = null,
    ): PergerakanStok {
        if (abs($qty) < 0.0005) {
            throw new RuntimeException('Jumlah koreksi tidak boleh nol.');
        }

        if (trim($alasan) === '') {
            throw new RuntimeException('Koreksi stok wajib disertai alasan.');
        }

        $harga = $this->hargaRataRata($barang);

        return PergerakanStok::create([
            'barang_id' => $barang->id,
            'tanggal' => $tanggal ?? now()->toDateString(),
            'tipe' => 'koreksi',
            'qty' => $qty,
            'harga_satuan' => $harga,
            'nilai' => $qty * $harga,
            'keterangan' => $alasan,
            'dibuat_oleh' => $user->id,
        ]);
    }
}
