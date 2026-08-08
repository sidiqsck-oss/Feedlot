<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lot pembelian — dasar perhitungan FIFO.
 *
 * Setiap baris penerimaan bikin satu lot dengan harga belinya sendiri.
 * Pengeluaran mengambil dari lot dengan tanggal_masuk tertua yang masih
 * bersisa. qty_masuk tidak pernah berubah; yang berkurang cuma qty_sisa.
 */
class StokLot extends Model
{
    protected $table = 'stok_lot';

    protected $fillable = [
        'barang_id', 'penerimaan_item_id', 'tanggal_masuk', 'harga_satuan',
        'qty_masuk', 'qty_sisa', 'tanggal_kadaluarsa', 'nomor_batch',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_masuk' => 'date',
            'tanggal_kadaluarsa' => 'date',
            'harga_satuan' => 'decimal:2',
            'qty_masuk' => 'decimal:3',
            'qty_sisa' => 'decimal:3',
        ];
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class);
    }

    public function penerimaanItem(): BelongsTo
    {
        return $this->belongsTo(PenerimaanItem::class);
    }

    public function alokasi(): HasMany
    {
        return $this->hasMany(AlokasiLot::class);
    }

    /**
     * Lot yang masih bersisa, diurutkan sesuai antrian FIFO.
     * id ikut jadi pengurut supaya dua lot bertanggal sama tetap deterministik.
     */
    public function scopeTersedia($query, int $barangId)
    {
        return $query->where('barang_id', $barangId)
            ->where('qty_sisa', '>', 0)
            ->orderBy('tanggal_masuk')
            ->orderBy('id');
    }
}
