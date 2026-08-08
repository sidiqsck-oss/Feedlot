<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Claim ke importir.
 *
 * induksi_id boleh kosong karena kasus tersering justru mati sebelum induksi,
 * dan sapi itu tidak punya baris induksi sama sekali.
 */
class Claim extends Model
{
    protected $table = 'claim';

    protected $fillable = [
        'shipment_id', 'induksi_id', 'rfid', 'ear_tag', 'tanggal_kejadian',
        'jenis_claim', 'fase', 'diagnosa', 'berat', 'nilai_klaim',
        'status_klaim', 'keterangan', 'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_kejadian' => 'date',
            'berat' => 'decimal:2',
            'nilai_klaim' => 'decimal:2',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function induksi(): BelongsTo
    {
        return $this->belongsTo(Induksi::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /**
     * Umur sapi saat di-claim, dalam hari sejak tiba di feedlot.
     *
     * Patokannya tanggal tiba, bukan tanggal induksi — hanya itu yang berlaku
     * untuk semua kasus, termasuk yang mati sebelum sempat diinduksi.
     */
    public function umurHari(): ?int
    {
        $tiba = PembelianShipment::where('shipment_id', $this->shipment_id)
            ->whereNotNull('tanggal_tiba')
            ->orderBy('tanggal_tiba')
            ->value('tanggal_tiba');

        if (! $tiba) {
            return null;
        }

        return \Illuminate\Support\Carbon::parse($tiba)
            ->diffInDays($this->tanggal_kejadian, absolute: false);
    }

    public function scopeMati($query)
    {
        return $query->where('jenis_claim', 'mati');
    }

    public function scopeSebelumInduksi($query)
    {
        return $query->where('fase', 'sebelum_induksi');
    }
}
