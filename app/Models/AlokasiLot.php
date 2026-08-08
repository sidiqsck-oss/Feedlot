<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rincian lot mana saja yang terpakai oleh satu baris pengeluaran.
 *
 * Tanpa tabel ini, FIFO cuma kelihatan hasil akhirnya dan tidak bisa
 * ditelusuri kalau ada angka yang dipertanyakan.
 */
class AlokasiLot extends Model
{
    protected $table = 'alokasi_lot';

    protected $fillable = ['pengeluaran_item_id', 'stok_lot_id', 'qty', 'harga_satuan', 'subtotal'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'harga_satuan' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function pengeluaranItem(): BelongsTo
    {
        return $this->belongsTo(PengeluaranItem::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StokLot::class, 'stok_lot_id');
    }
}
