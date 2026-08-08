<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreatmentItem extends Model
{
    protected $table = 'treatment_items';

    protected $fillable = [
        'treatment_id', 'barang_id', 'nama_obat_asli',
        'kategori', 'dosis', 'satuan_dosis',
    ];

    protected function casts(): array
    {
        return ['dosis' => 'decimal:3'];
    }

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class);
    }

    /** Baris yang nama obatnya belum cocok ke master mana pun. */
    public function scopeBelumDipetakan($query)
    {
        return $query->whereNull('barang_id');
    }
}
