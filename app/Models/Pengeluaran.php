<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Nota barang keluar.
 *
 * Menggantikan kolom "Keterangan" teks bebas di sistem lama, yang bikin
 * pengeluaran ke dokter, induksi, dan reweight tidak bisa dipisahkan di
 * laporan. Sekarang tujuannya terstruktur, pengambilnya tercatat, dan untuk
 * induksi/reweight bisa menunjuk shipment.
 */
class Pengeluaran extends Model
{
    protected $table = 'pengeluaran';

    protected $fillable = [
        'nomor', 'tanggal', 'tujuan', 'petugas_id', 'shipment_id',
        'catatan', 'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PengeluaranItem::class);
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(Petugas::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function pergerakan(): MorphMany
    {
        return $this->morphMany(PergerakanStok::class, 'sumber');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function totalHpp(): float
    {
        return (float) $this->items()->sum('nilai_hpp');
    }
}
