<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak perubahan PO: revisi qty, penutupan, pembatalan.
 *
 * PO adalah komitmen ke supplier, jadi perubahannya harus meninggalkan jejak —
 * bukan diam-diam ter-update lalu hilang seperti di sistem lama.
 */
class PurchaseOrderRiwayat extends Model
{
    protected $table = 'purchase_order_riwayat';

    protected $fillable = ['purchase_order_id', 'aksi', 'alasan', 'perubahan', 'oleh'];

    protected function casts(): array
    {
        return ['perubahan' => 'array'];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function pelaku(): BelongsTo
    {
        return $this->belongsTo(User::class, 'oleh');
    }
}
