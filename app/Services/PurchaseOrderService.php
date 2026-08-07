<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderRiwayat;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Siklus hidup Purchase Order.
 *
 * Kenyataan di lapangan: barang tidak selalu datang penuh. Bisa kosong di
 * supplier, bisa kurang dari yang diminta, bisa juga PO-nya perlu ditambah
 * atau dikurangi setelah dibuat. Semua itu ditangani di sini, dan tiap
 * perubahan meninggalkan jejak di purchase_order_riwayat.
 */
class PurchaseOrderService
{
    /**
     * Revisi isi PO: tambah barang, ubah qty, atau hapus barang.
     *
     * @param  array<int, array{barang_id:int, qty:float, harga_satuan:float}>  $items
     */
    public function revisi(PurchaseOrder $po, array $items, string $alasan, User $user): PurchaseOrder
    {
        if (! $po->bolehDirevisi()) {
            throw new RuntimeException(
                "PO {$po->nomor} berstatus {$po->status} dan tidak bisa direvisi lagi."
            );
        }

        if ($items === []) {
            throw new RuntimeException('PO harus berisi minimal satu barang. Kalau mau dikosongkan, batalkan saja PO-nya.');
        }

        return DB::transaction(function () use ($po, $items, $alasan, $user) {
            $sebelum = $po->items()->get()
                ->mapWithKeys(fn ($i) => [$i->barang_id => [
                    'qty' => (float) $i->qty,
                    'harga_satuan' => (float) $i->harga_satuan,
                ]])
                ->all();

            $barangBaru = [];

            foreach ($items as $baris) {
                $barangId = (int) $baris['barang_id'];
                $qty = (float) $baris['qty'];
                $barangBaru[] = $barangId;

                if ($qty <= 0) {
                    throw new RuntimeException('Jumlah pada PO harus lebih dari nol.');
                }

                $item = $po->items()->where('barang_id', $barangId)->lockForUpdate()->first();

                // Qty tidak boleh diturunkan di bawah yang sudah terlanjur
                // diterima — angka penerimaannya sudah jadi stok, dan PO yang
                // menyatakan lebih sedikit dari yang sudah datang itu bohong.
                if ($item && $qty < (float) $item->qty_diterima) {
                    throw new RuntimeException(sprintf(
                        'Jumlah %s tidak bisa diturunkan ke %s: sudah diterima %s.',
                        $item->barang->nama,
                        rtrim(rtrim(number_format($qty, 3, ',', '.'), '0'), ','),
                        rtrim(rtrim(number_format((float) $item->qty_diterima, 3, ',', '.'), '0'), ','),
                    ));
                }

                PurchaseOrderItem::updateOrCreate(
                    ['purchase_order_id' => $po->id, 'barang_id' => $barangId],
                    ['qty' => $qty, 'harga_satuan' => (float) $baris['harga_satuan']],
                );
            }

            // Barang yang dihapus dari PO — hanya boleh kalau belum ada yang datang.
            $dihapus = $po->items()->whereNotIn('barang_id', $barangBaru)->get();

            foreach ($dihapus as $item) {
                if ((float) $item->qty_diterima > 0) {
                    throw new RuntimeException(
                        "{$item->barang->nama} tidak bisa dihapus dari PO: barangnya sudah sebagian diterima."
                    );
                }

                $item->delete();
            }

            $sesudah = $po->items()->get()
                ->mapWithKeys(fn ($i) => [$i->barang_id => [
                    'qty' => (float) $i->qty,
                    'harga_satuan' => (float) $i->harga_satuan,
                ]])
                ->all();

            PurchaseOrderRiwayat::create([
                'purchase_order_id' => $po->id,
                'aksi' => 'revisi',
                'alasan' => $alasan,
                'perubahan' => ['sebelum' => $sebelum, 'sesudah' => $sesudah],
                'oleh' => $user->id,
            ]);

            $this->segarkanStatus($po);

            return $po->fresh('items');
        });
    }

    /**
     * Tutup PO meski barang kurang atau kosong.
     *
     * Dipakai ketika supplier tidak bisa memenuhi sisanya dan diputuskan
     * sudahi saja. Dibedakan dari "selesai" supaya laporan bisa menunjukkan
     * berapa banyak PO yang tidak terpenuhi, dan berapa nilainya.
     */
    public function tutup(PurchaseOrder $po, string $alasan, User $user): PurchaseOrder
    {
        if (! $po->bolehDitutup()) {
            throw new RuntimeException("PO {$po->nomor} sudah berstatus {$po->status}.");
        }

        if (trim($alasan) === '') {
            throw new RuntimeException('Penutupan PO wajib disertai alasan.');
        }

        return DB::transaction(function () use ($po, $alasan, $user) {
            $kurang = $po->items()
                ->get()
                ->filter(fn ($i) => $i->sisa() > 0)
                ->map(fn ($i) => [
                    'barang' => $i->barang->nama,
                    'diminta' => (float) $i->qty,
                    'diterima' => (float) $i->qty_diterima,
                    'kurang' => $i->sisa(),
                ])
                ->values()
                ->all();

            $po->update([
                'status' => 'ditutup',
                'alasan_penutupan' => $alasan,
                'ditutup_pada' => now(),
                'ditutup_oleh' => $user->id,
            ]);

            PurchaseOrderRiwayat::create([
                'purchase_order_id' => $po->id,
                'aksi' => 'ditutup',
                'alasan' => $alasan,
                'perubahan' => ['kekurangan' => $kurang],
                'oleh' => $user->id,
            ]);

            return $po->fresh();
        });
    }

    /**
     * Batalkan PO. Hanya boleh selama belum ada barang masuk sama sekali —
     * kalau sudah ada, jalurnya "tutup", bukan "batal".
     */
    public function batalkan(PurchaseOrder $po, string $alasan, User $user): PurchaseOrder
    {
        if ($po->sudahBerakhir()) {
            throw new RuntimeException("PO {$po->nomor} sudah berstatus {$po->status}.");
        }

        if (! $po->bolehDibatalkan()) {
            throw new RuntimeException(
                "PO {$po->nomor} sudah ada barang yang masuk, jadi tidak bisa dibatalkan. ".
                'Gunakan tutup PO supaya penerimaan yang sudah tercatat tetap sah.'
            );
        }

        if (trim($alasan) === '') {
            throw new RuntimeException('Pembatalan PO wajib disertai alasan.');
        }

        return DB::transaction(function () use ($po, $alasan, $user) {
            $po->update(['status' => 'batal']);

            PurchaseOrderRiwayat::create([
                'purchase_order_id' => $po->id,
                'aksi' => 'dibatalkan',
                'alasan' => $alasan,
                'oleh' => $user->id,
            ]);

            return $po->fresh();
        });
    }

    /**
     * Hitung ulang status dari kenyataan penerimaannya.
     * Dipanggil setiap kali ada barang masuk atau PO direvisi.
     */
    public function segarkanStatus(PurchaseOrder $po): PurchaseOrder
    {
        if (in_array($po->status, ['ditutup', 'batal', 'draft'], true)) {
            return $po;
        }

        $adaYangDiterima = $po->items()->where('qty_diterima', '>', 0)->exists();

        $status = match (true) {
            $po->terpenuhiPenuh() => 'selesai',
            $adaYangDiterima => 'sebagian',
            default => 'terbuka',
        };

        if ($status !== $po->status) {
            $po->update(['status' => $status]);
        }

        return $po;
    }
}
