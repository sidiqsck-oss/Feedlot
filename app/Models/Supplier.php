<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $table = 'suppliers';

    protected $fillable = ['kode', 'nama', 'kontak', 'telepon', 'alamat', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function penerimaan(): HasMany
    {
        return $this->hasMany(Penerimaan::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }
}
