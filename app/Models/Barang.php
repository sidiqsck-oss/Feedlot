<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Barang: obat, alkes, dan bahan habis pakai.
 *
 * Satuan disimpan apa adanya per barang (botol, tablet, pcs, box, liter).
 * Tidak ada konversi global — alkohol dihitung per liter, sarung tangan per
 * pcs. isi_nilai/isi_satuan hanya diisi untuk barang yang perlu dinilai sampai
 * satuan terkecil, misal 1 botol Limoxin = 100 ml.
 */
class Barang extends Model
{
    use HasFactory;

    protected $table = 'barang';

    protected $fillable = [
        'kode', 'nama', 'kategori_barang_id', 'satuan',
        'isi_nilai', 'isi_satuan', 'stok_minimum', 'aktif', 'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'isi_nilai' => 'decimal:3',
            'stok_minimum' => 'decimal:3',
            'aktif' => 'boolean',
        ];
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriBarang::class, 'kategori_barang_id');
    }

    public function alias(): HasMany
    {
        return $this->hasMany(AliasBarang::class);
    }

    public function lot(): HasMany
    {
        return $this->hasMany(StokLot::class);
    }

    public function pergerakan(): HasMany
    {
        return $this->hasMany(PergerakanStok::class);
    }

    /**
     * Stok saat ini, dihitung dari kartu stok — bukan dibaca dari kolom.
     *
     * Ini inti perubahan dari sistem lama: tidak ada angka stok yang disimpan
     * dan di-update, jadi tidak ada yang bisa ketambah dua kali.
     */
    public function stok(): float
    {
        return (float) $this->pergerakan()->sum('qty');
    }

    /**
     * Stok per tanggal tertentu. Dipakai opname untuk membekukan stok_sistem.
     */
    public function stokPerTanggal(string $tanggal): float
    {
        return (float) $this->pergerakan()->whereDate('tanggal', '<=', $tanggal)->sum('qty');
    }

    /**
     * Nilai persediaan = sisa tiap lot dikali harga belinya sendiri.
     *
     * Sengaja dihitung dari lot, bukan dari sum(nilai) di kartu stok. Keduanya
     * harus sama; kalau berbeda berarti ada alokasi FIFO yang bocor, dan itu
     * memang sesuatu yang ingin ketahuan.
     */
    public function nilaiPersediaan(): float
    {
        return (float) $this->lot()
            ->where('qty_sisa', '>', 0)
            ->selectRaw('COALESCE(SUM(qty_sisa * harga_satuan), 0) as total')
            ->value('total');
    }

    /**
     * Harga per satuan terkecil, mis. rupiah per ml.
     * Null kalau barangnya memang tidak punya isi (sarung tangan, pcs).
     */
    public function hargaPerIsi(float $hargaSatuan): ?float
    {
        if (! $this->isi_nilai || (float) $this->isi_nilai <= 0) {
            return null;
        }

        return $hargaSatuan / (float) $this->isi_nilai;
    }

    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }
}
