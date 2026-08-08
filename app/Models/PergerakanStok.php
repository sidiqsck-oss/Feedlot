<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * Kartu stok — APPEND-ONLY.
 *
 * Baris di sini tidak pernah diubah dan tidak pernah dihapus. Salah input
 * dibalik dengan baris koreksi berlawanan, sehingga riwayatnya tetap utuh dan
 * saldo tetap bisa dihitung ulang dari nol kapan saja.
 *
 * Kenapa dijaga di level model, bukan trigger database: hak TRIGGER sering
 * tidak diberikan di shared hosting cPanel, jadi trigger belum tentu bisa
 * dipasang di server tujuan. Penjagaan di sini selalu ikut ke mana pun aplikasi
 * dipasang.
 */
class PergerakanStok extends Model
{
    protected $table = 'pergerakan_stok';

    protected $fillable = [
        'barang_id', 'tanggal', 'tipe', 'qty', 'harga_satuan', 'nilai',
        'stok_lot_id', 'sumber_type', 'sumber_id', 'keterangan', 'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'qty' => 'decimal:3',
            'harga_satuan' => 'decimal:2',
            'nilai' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException(
                'Kartu stok tidak boleh diubah. Untuk membetulkan angka, buat pergerakan koreksi.'
            );
        });

        static::deleting(function () {
            throw new RuntimeException(
                'Kartu stok tidak boleh dihapus. Untuk membatalkan, buat pergerakan koreksi.'
            );
        });
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StokLot::class, 'stok_lot_id');
    }

    public function sumber(): MorphTo
    {
        return $this->morphTo();
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function scopeMasuk($query)
    {
        return $query->where('tipe', 'masuk');
    }

    public function scopeKeluar($query)
    {
        return $query->where('tipe', 'keluar');
    }

    public function scopePeriode($query, string $dari, string $sampai)
    {
        return $query->whereBetween('tanggal', [$dari, $sampai]);
    }
}
