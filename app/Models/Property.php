<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Properti asal sapi.
 *
 * Disambungkan ke induksi lewat kode (PIC), bukan lewat foreign key, supaya
 * data induksi tetap bisa masuk walau daftar propertinya belum diimpor.
 */
class Property extends Model
{
    protected $table = 'properties';

    protected $fillable = ['kode', 'nama', 'region', 'lga', 'daerah', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function induksi(): HasMany
    {
        return $this->hasMany(Induksi::class, 'kode_prop', 'kode');
    }

    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }
}
