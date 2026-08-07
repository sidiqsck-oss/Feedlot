<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KategoriBarang extends Model
{
    protected $table = 'kategori_barang';

    protected $fillable = ['nama', 'keterangan', 'urutan'];

    public function barang(): HasMany
    {
        return $this->hasMany(Barang::class, 'kategori_barang_id');
    }
}
