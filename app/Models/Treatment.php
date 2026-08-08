<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rekam medis per ekor, diimpor dari Google Sheets dokter hewan.
 *
 * TIDAK memotong stok. Stok sudah berkurang saat dokter mengambil barang dari
 * gudang. Gunanya di sini: rekam medis, dan menghitung biaya obat per ekor.
 */
class Treatment extends Model
{
    protected $table = 'treatment';

    protected $fillable = [
        'shipment_id', 'shipment_teks', 'ear_tag', 'tanggal', 'petugas_id',
        'penanggung_jawab_teks', 'pen_asal', 'diagnosa', 'berat_badan',
        'kondisi', 'hash_baris',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'berat_badan' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(TreatmentItem::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(Petugas::class);
    }

    /**
     * Sidik jari baris sumber, supaya impor aman diulang.
     *
     * Sistem lama harus menghapus file payload JSON setelah diproses supaya
     * tidak terproses ulang — dan ketika penghapusan itu gagal ter-commit,
     * datanya masuk dua kali. Di sini keunikan dijamin oleh database.
     */
    public static function hash(array $baris): string
    {
        return hash('sha256', json_encode($baris, JSON_UNESCAPED_UNICODE));
    }
}
