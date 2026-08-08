<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu unggahan berkas.
 *
 * Alurnya dua tahap: dibaca dulu jadi pratinjau, baru diproses setelah
 * dikonfirmasi. Selama masih "pratinjau", belum ada satu baris pun yang masuk
 * ke tabel tujuan.
 */
class ImportBatch extends Model
{
    protected $table = 'import_batch';

    protected $fillable = [
        'jenis', 'nama_berkas', 'hash_berkas', 'shipment_id', 'status',
        'jumlah_baris', 'jumlah_valid', 'jumlah_bermasalah',
        'jumlah_baru', 'jumlah_diperbarui', 'jumlah_dilewati',
        'pesan', 'ringkasan', 'diunggah_oleh', 'diproses_pada',
    ];

    protected function casts(): array
    {
        return [
            'ringkasan' => 'array',
            'diproses_pada' => 'datetime',
        ];
    }

    public function baris(): HasMany
    {
        return $this->hasMany(ImportBaris::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function pengunggah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diunggah_oleh');
    }

    public function siapDiproses(): bool
    {
        return $this->status === 'pratinjau' && $this->jumlah_valid > 0;
    }

    public function sudahSelesai(): bool
    {
        return in_array($this->status, ['selesai', 'gagal', 'dibatalkan'], true);
    }
}
