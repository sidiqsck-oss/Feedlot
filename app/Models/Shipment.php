<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    protected $table = 'shipments';

    protected $fillable = ['kode', 'nomor', 'tanggal_masuk', 'aktif', 'keterangan'];

    protected function casts(): array
    {
        return [
            'tanggal_masuk' => 'date',
            'aktif' => 'boolean',
        ];
    }

    public function pengeluaran(): HasMany
    {
        return $this->hasMany(Pengeluaran::class);
    }

    public function treatment(): HasMany
    {
        return $this->hasMany(Treatment::class);
    }

    /**
     * '90' / '90.0' / 'sck 90' -> 'SCK90'. Non-angka dibiarkan apa adanya.
     *
     * Dipindahkan dari normalisasi_shipment() di streamlit_app.py. Di form
     * kertas, kolom SHIPMENT sudah tercetak "SCK" dan yang ditulis tangan cuma
     * angkanya — jadi input "90" harus jadi "SCK90".
     *
     * Gabungan seperti '90+91' atau '90,91' dikembalikan sebagai 'SCK90+SCK91'.
     * Nilai non-angka ('calf', '-') dikembalikan apa adanya.
     */
    public static function normalisasiKode(?string $teks): string
    {
        $teks = trim((string) $teks);

        if ($teks === '') {
            return '';
        }

        $bersih = str_replace([' ', 'SCK'], '', strtoupper($teks));
        $bagian = array_filter(
            array_map('trim', preg_split('/[+,]/', $bersih)),
            fn ($b) => $b !== ''
        );

        if ($bagian === []) {
            return $teks;
        }

        $keluar = [];

        foreach ($bagian as $b) {
            if (! is_numeric($b)) {
                return $teks;
            }

            $keluar[] = 'SCK'.(int) (float) $b;
        }

        return implode('+', $keluar);
    }

    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }
}
