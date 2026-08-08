<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Penerimaan extends Model
{
    protected $table = 'penerimaan';

    protected $fillable = [
        'nomor', 'tanggal', 'supplier_id', 'purchase_order_id',
        'no_faktur_supplier', 'catatan', 'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PenerimaanItem::class);
    }

    public function pergerakan(): MorphMany
    {
        return $this->morphMany(PergerakanStok::class, 'sumber');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function total(): float
    {
        return (float) $this->items()->sum('subtotal');
    }
}
