<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Penjualan extends Model
{
    protected $table = 'penjualan';

    protected $fillable = [
        'induksi_id', 'tanggal', 'berat', 'customer', 'no_invoice',
        'harga_per_kg', 'total', 'status_sapi', 'import_batch_id',
        'no_surat_jalan', 'kode_customer', 'nama_barang', 'satuan',
        'realisasi', 'potongan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'berat' => 'decimal:2',
            'harga_per_kg' => 'decimal:2',
            'total' => 'decimal:2',
            'realisasi' => 'decimal:2',
            'potongan' => 'decimal:2',
        ];
    }

    public function induksi(): BelongsTo
    {
        return $this->belongsTo(Induksi::class);
    }
}
