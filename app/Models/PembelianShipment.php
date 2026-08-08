<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pembelian per shipment + jenis.
 *
 * Angkanya berlaku untuk seluruh rombongan, bukan per ekor: berat_muat dan
 * berat_tiba adalah rata-rata per ekor di rombongan itu, lalu disalin ke tiap
 * sapi saat menyusun baris CPL.
 */
class PembelianShipment extends Model
{
    protected $table = 'pembelian_shipment';

    protected $fillable = [
        'shipment_id', 'jenis', 'tanggal_muat', 'berat_muat',
        'tanggal_tiba', 'berat_tiba', 'jumlah_ekor', 'import_batch_id',
        'importir', 'harga_usd', 'daerah',
        'salvage_jumlah', 'salvage_persen',
        'mati_jumlah', 'mati_persen',
        'bunting_jumlah', 'bunting_persen',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_muat' => 'date',
            'tanggal_tiba' => 'date',
            'berat_muat' => 'decimal:2',
            'berat_tiba' => 'decimal:2',
            'harga_usd' => 'decimal:2',
            'salvage_persen' => 'decimal:3',
            'mati_persen' => 'decimal:3',
            'bunting_persen' => 'decimal:3',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /** Susut selama pengangkutan: berat tiba dikurangi berat muat. */
    public function susutAngkutan(): ?float
    {
        if ($this->berat_muat === null || $this->berat_tiba === null) {
            return null;
        }

        return (float) $this->berat_tiba - (float) $this->berat_muat;
    }
}
