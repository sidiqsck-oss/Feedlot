<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu ekor sapi saat masuk feedlot.
 *
 * Identitasnya shipment + RFID. Pasangan shipment + ear tag juga dijaga unik,
 * karena itu satu-satunya jalan menyambungkan rekam medis dokter — sheet dokter
 * tidak mencatat RFID sama sekali.
 */
class Induksi extends Model
{
    protected $table = 'induksi';

    protected $fillable = [
        'shipment_id', 'rfid', 'ear_tag', 'tanggal_induksi', 'berat_induksi',
        'pen', 'gigi', 'frame', 'kode_prop', 'data_prop', 'asal', 'jenis',
        'import_batch_id',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_induksi' => 'date',
            'berat_induksi' => 'decimal:2',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function reweight(): HasMany
    {
        return $this->hasMany(Reweight::class);
    }

    /** Penimbangan ulang terakhir, dipakai menghitung pertambahan bobot. */
    public function reweightTerakhir(): ?Reweight
    {
        return $this->reweight()->orderByDesc('tanggal_reweight')->first();
    }

    public function pertambahanBobot(): ?float
    {
        $akhir = $this->reweightTerakhir();

        if (! $akhir || $this->berat_induksi === null || $akhir->berat_reweight === null) {
            return null;
        }

        return (float) $akhir->berat_reweight - (float) $this->berat_induksi;
    }
}
