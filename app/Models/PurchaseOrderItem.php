<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $table = 'purchase_order_items';

    protected $fillable = [
        'purchase_order_id', 'barang_id', 'qty', 'harga_satuan', 'qty_diterima',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'harga_satuan' => 'decimal:2',
            'qty_diterima' => 'decimal:3',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class);
    }

    public function sisa(): float
    {
        return max(0, (float) $this->qty - (float) $this->qty_diterima);
    }
}
