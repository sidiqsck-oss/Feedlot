<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Purchase Order.
 *
 * Barang tidak selalu datang penuh: bisa kosong di supplier, bisa kurang dari
 * yang diminta. Karena itu PO punya dua jalur akhir yang berbeda —
 * "selesai" (terpenuhi penuh, otomatis) dan "ditutup" (disudahi meski kurang,
 * manual dan wajib beralasan). Keduanya sengaja dibedakan supaya laporan bisa
 * menunjukkan berapa banyak PO yang tidak terpenuhi.
 */
class PurchaseOrder extends Model
{
    protected $table = 'purchase_orders';

    protected $fillable = [
        'nomor', 'tanggal', 'supplier_id', 'status', 'catatan',
        'alasan_penutupan', 'ditutup_pada', 'ditutup_oleh', 'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'ditutup_pada' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function penerimaan(): HasMany
    {
        return $this->hasMany(Penerimaan::class);
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(PurchaseOrderRiwayat::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /** PO yang sudah berakhir, apa pun caranya. */
    public function sudahBerakhir(): bool
    {
        return in_array($this->status, ['selesai', 'ditutup', 'batal'], true);
    }

    /**
     * Batal hanya boleh selama belum ada barang masuk sama sekali. Kalau sudah
     * ada, jalurnya "tutup" — membatalkan PO yang barangnya sudah sebagian
     * diterima akan membuat penerimaan itu yatim.
     */
    public function bolehDibatalkan(): bool
    {
        return ! $this->sudahBerakhir() && ! $this->penerimaan()->exists();
    }

    public function bolehDitutup(): bool
    {
        return ! $this->sudahBerakhir();
    }

    public function bolehDirevisi(): bool
    {
        return ! $this->sudahBerakhir();
    }

    public function totalNilai(): float
    {
        return (float) $this->items()->selectRaw('COALESCE(SUM(qty * harga_satuan), 0) as t')->value('t');
    }

    public function terpenuhiPenuh(): bool
    {
        return ! $this->items()->whereColumn('qty_diterima', '<', 'qty')->exists();
    }
}
