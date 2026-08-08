<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\PergerakanStok;
use App\Models\TreatmentItem;
use Illuminate\Support\Collection;

/**
 * Biaya obat per ekor.
 *
 * Menyambungkan dosis yang ditulis dokter (ml) ke nilai FIFO barang yang
 * benar-benar keluar dari gudang. Inilah angka yang selama ini tidak pernah
 * bisa dilihat: berapa rupiah obat yang masuk ke satu ekor sapi.
 *
 * TIDAK memotong stok. Stok sudah berkurang saat dokter mengambil barangnya;
 * di sini cuma dinilai ulang untuk keperluan biaya.
 *
 * Patokan harganya harga rata-rata PENGAMBILAN, bukan harga stok yang tersisa
 * sekarang. Botol yang sudah disuntikkan bulan lalu harganya harga waktu itu,
 * bukan harga sisa stok hari ini.
 */
class BiayaObatService
{
    public function __construct(private readonly StokService $stok) {}

    /** @var array<string, float> ingatan sementara supaya satu barang tidak dihitung berulang */
    private array $ingatan = [];

    /**
     * Harga per satuan stok (mis. per botol) menurut rata-rata pengambilan
     * sampai tanggal tertentu.
     *
     * Kalau belum pernah ada pengambilan, jatuh ke harga rata-rata lot yang
     * masih ada — itu satu-satunya angka yang tersedia.
     */
    public function hargaSatuan(Barang $barang, string $tanggal): float
    {
        $kunci = "{$barang->id}|{$tanggal}";

        if (isset($this->ingatan[$kunci])) {
            return $this->ingatan[$kunci];
        }

        $keluar = PergerakanStok::where('barang_id', $barang->id)
            ->where('tipe', 'keluar')
            ->whereDate('tanggal', '<=', $tanggal)
            ->selectRaw('SUM(qty) as qty, SUM(nilai) as nilai')
            ->first();

        // qty dan nilai pengeluaran tersimpan bertanda negatif.
        $qty = abs((float) ($keluar->qty ?? 0));
        $nilai = abs((float) ($keluar->nilai ?? 0));

        return $this->ingatan[$kunci] = $qty > 0
            ? $nilai / $qty
            : $this->stok->hargaRataRata($barang);
    }

    /**
     * Harga satu satuan dosis — mis. rupiah per ml.
     *
     * NULL kalau dosisnya memang tidak bisa dinilai: obatnya belum dipetakan
     * ke master, isi per botolnya belum diisi, atau satuan dosis dokter tidak
     * sepadan dengan isi botolnya. Dibiarkan kosong, bukan dianggap nol —
     * biaya yang salah lebih berbahaya daripada biaya yang belum ada.
     */
    public function hargaPerDosis(TreatmentItem $item, string $tanggal): ?float
    {
        $barang = $item->barang;

        if (! $barang) {
            return null;
        }

        $harga = $this->hargaSatuan($barang, $tanggal);
        $satuanDosis = $this->normalkan($item->satuan_dosis);

        // Dosis ditulis dalam satuan stoknya sendiri — sarung tangan 2 pcs,
        // jarum 1 pcs. Tidak perlu dipecah lagi.
        if ($satuanDosis === $this->normalkan($barang->satuan) || $satuanDosis === '') {
            return $harga;
        }

        if (! $barang->isi_nilai || $satuanDosis !== $this->normalkan($barang->isi_satuan)) {
            return null;
        }

        return $harga / (float) $barang->isi_nilai;
    }

    public function biayaItem(TreatmentItem $item, string $tanggal): ?float
    {
        if ($item->dosis === null) {
            return null;
        }

        $perDosis = $this->hargaPerDosis($item, $tanggal);

        return $perDosis === null ? null : (float) $item->dosis * $perDosis;
    }

    /**
     * Alasan satu baris tidak bisa dinilai, dalam bahasa yang bisa
     * ditindaklanjuti — halaman biaya menampilkannya apa adanya supaya
     * jelas apa yang harus dibetulkan.
     */
    public function alasanKosong(TreatmentItem $item): ?string
    {
        if (! $item->barang_id) {
            return "Nama obat \"{$item->nama_obat_asli}\" belum dipetakan ke master barang.";
        }

        if ($item->dosis === null) {
            return "Dosis \"{$item->nama_obat_asli}\" tidak terisi.";
        }

        $barang = $item->barang;
        $satuanDosis = $this->normalkan($item->satuan_dosis);

        if ($satuanDosis === $this->normalkan($barang->satuan) || $satuanDosis === '') {
            return null;
        }

        if (! $barang->isi_nilai) {
            return "Isi per {$barang->satuan} untuk {$barang->nama} belum diisi di master barang.";
        }

        if ($satuanDosis !== $this->normalkan($barang->isi_satuan)) {
            return sprintf(
                'Satuan dosis "%s" tidak cocok dengan isi %s (%s %s).',
                $item->satuan_dosis, $barang->nama, $barang->isi_nilai, $barang->isi_satuan,
            );
        }

        return null;
    }

    /**
     * Rekap per ekor.
     *
     * @param  Collection<int, \App\Models\Treatment>  $treatment  sudah memuat items.barang
     * @return Collection<int, array<string, mixed>>
     */
    public function perEkor(Collection $treatment): Collection
    {
        return $treatment
            // Kunci gabungan, bukan ear tag saja: ear tag berulang antar
            // shipment, dan menggabungkannya akan menumpuk biaya dua ekor
            // berbeda jadi satu.
            ->groupBy(fn ($t) => ($t->shipment?->kode ?: $t->shipment_teks ?: '?').'|'.$t->ear_tag)
            ->map(function (Collection $grup, string $kunci) {
                [$shipment, $earTag] = explode('|', $kunci, 2);

                $biaya = 0.0;
                $adaNilai = false;
                $masalah = [];

                foreach ($grup as $t) {
                    foreach ($t->items as $item) {
                        $nilai = $this->biayaItem($item, $t->tanggal->toDateString());

                        if ($nilai === null) {
                            if ($alasan = $this->alasanKosong($item)) {
                                $masalah[$alasan] = true;
                            }

                            continue;
                        }

                        $biaya += $nilai;
                        $adaNilai = true;
                    }
                }

                return [
                    'shipment' => $shipment,
                    'ear_tag' => $earTag,
                    'jumlah_treatment' => $grup->count(),
                    'tanggal_awal' => $grup->min('tanggal'),
                    'tanggal_akhir' => $grup->max('tanggal'),
                    'diagnosa' => $grup->pluck('diagnosa')->filter()->unique()->implode(', '),
                    'jumlah_item' => $grup->sum(fn ($t) => $t->items->count()),
                    // NULL, bukan 0, kalau tidak ada satu pun item yang bisa
                    // dinilai — supaya "belum bisa dihitung" tidak terbaca
                    // sebagai "gratis".
                    'biaya' => $adaNilai ? $biaya : null,
                    'masalah' => array_keys($masalah),
                ];
            })
            ->sortByDesc(fn ($b) => $b['biaya'] ?? -1)
            ->values();
    }

    private function normalkan(?string $satuan): string
    {
        return strtolower(trim((string) $satuan));
    }
}
