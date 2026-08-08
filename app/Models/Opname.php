<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Stok opname bulanan.
 *
 * stok_sistem dibekukan saat opname dibuat, bukan dihitung ulang saat
 * difinalkan — kalau dihitung ulang, transaksi yang masuk di sela-sela
 * penghitungan fisik akan membuat selisihnya bohong.
 */
class Opname extends Model
{
    protected $table = 'opname';

    protected $fillable = [
        'nomor', 'tanggal', 'periode_bulan', 'periode_tahun',
        'status', 'catatan', 'difinalkan_pada', 'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'difinalkan_pada' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OpnameItem::class);
    }

    public function pergerakan(): MorphMany
    {
        return $this->morphMany(PergerakanStok::class, 'sumber');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function sudahFinal(): bool
    {
        return $this->status === 'final';
    }
}
