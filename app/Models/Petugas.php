<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Orang yang mengambil barang dari gudang: dokter dan operator.
 *
 * Nama yang sama juga muncul sebagai "Penanggung Jawab" di sheet rekam medis
 * dokter, jadi master ini dipakai di dua tempat sekaligus.
 */
class Petugas extends Model
{
    protected $table = 'petugas';

    protected $fillable = ['nama', 'peran', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function pengeluaran(): HasMany
    {
        return $this->hasMany(Pengeluaran::class);
    }

    public function treatment(): HasMany
    {
        return $this->hasMany(Treatment::class);
    }

    /**
     * Petugas yang sudah pernah dipakai di transaksi tidak boleh dihapus —
     * menghapusnya bikin nota lama kehilangan nama pengambilnya. Nonaktifkan
     * saja: dia hilang dari pilihan tapi nota lama tetap utuh.
     */
    public function bolehDihapus(): bool
    {
        return ! $this->pengeluaran()->exists() && ! $this->treatment()->exists();
    }

    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }
}
