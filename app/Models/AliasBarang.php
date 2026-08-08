<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AliasBarang extends Model
{
    protected $table = 'alias_barang';

    protected $fillable = ['barang_id', 'alias'];

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class);
    }
}
