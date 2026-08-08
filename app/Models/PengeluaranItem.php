<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PengeluaranItem extends Model
{
    protected $table = 'pengeluaran_items';

    protected $fillable = ['pengeluaran_id', 'barang_id', 'qty', 'nilai_hpp'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'nilai_hpp' => 'decimal:2',
        ];
    }

    public function pengeluaran(): BelongsTo
    {
        return $this->belongsTo(Pengeluaran::class);
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class);
    }

    public function alokasi(): HasMany
    {
        return $this->hasMany(AlokasiLot::class);
    }

    /** Harga rata-rata tertimbang dari lot yang terpakai di baris ini. */
    public function hargaRataRata(): float
    {
        $qty = (float) $this->qty;

        return $qty > 0 ? (float) $this->nilai_hpp / $qty : 0.0;
    }
}
