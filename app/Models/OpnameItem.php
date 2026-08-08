<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpnameItem extends Model
{
    protected $table = 'opname_items';

    protected $fillable = [
        'opname_id', 'barang_id', 'stok_sistem', 'stok_fisik',
        'selisih', 'nilai_selisih', 'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'stok_sistem' => 'decimal:3',
            'stok_fisik' => 'decimal:3',
            'selisih' => 'decimal:3',
            'nilai_selisih' => 'decimal:2',
        ];
    }

    public function opname(): BelongsTo
    {
        return $this->belongsTo(Opname::class);
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class);
    }
}
