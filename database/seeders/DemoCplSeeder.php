<?php

namespace Database\Seeders;

use App\Models\Claim;
use App\Models\Induksi;
use App\Models\PembelianShipment;
use App\Models\Penjualan;
use App\Models\Property;
use App\Models\Reweight;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Data contoh CPL untuk pengembangan lokal.
 *
 * Sengaja dibuat tidak rapi supaya perilaku yang penting benar-benar terlihat:
 * sebagian sapi tidak punya data reweight, sebagian masih di kandang, dan ada
 * yang mati sebelum sempat diinduksi.
 *
 * Jalankan: php artisan db:seed --class=DemoCplSeeder
 */
class DemoCplSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrFail();

        $properties = collect([
            ['kode' => 'QABC123', 'nama' => 'Brighton Downs', 'daerah' => 'NT'],
            ['kode' => 'QDEF456', 'nama' => 'Carlton Hill', 'daerah' => 'WA'],
            ['kode' => 'QGHI789', 'nama' => 'Moolooloo Station', 'daerah' => 'NT'],
        ])->map(fn ($p) => Property::firstOrCreate(['kode' => $p['kode']], $p));

        $jenisSapi = ['Steer', 'Bull', 'Heifer'];
        $customer = ['PT Berkah Daging', 'CV Karya Ternak', 'PT Nusantara Protein'];

        // Enam shipment, makin lama makin baru
        foreach (range(86, 91) as $i => $nomor) {
            $shipment = Shipment::firstOrCreate(
                ['kode' => "SCK{$nomor}"],
                ['nomor' => $nomor, 'aktif' => true],
            );

            $tibaPada = Carbon::parse('2026-01-10')->addDays($i * 38);
            $ekorPerJenis = [28, 12, 8];

            foreach ($jenisSapi as $j => $jenis) {
                $jumlah = $ekorPerJenis[$j];

                PembelianShipment::firstOrCreate(
                    ['shipment_id' => $shipment->id, 'jenis' => $jenis],
                    [
                        'tanggal_muat' => $tibaPada->copy()->subDays(6),
                        'berat_muat' => 300 + $j * 20,
                        'tanggal_tiba' => $tibaPada,
                        'berat_tiba' => 288 + $j * 20,
                        // Dua ekor lebih banyak dari yang diinduksi: itulah
                        // yang mati sebelum induksi.
                        'jumlah_ekor' => $jumlah + ($j === 0 ? 2 : 0),
                    ],
                );

                $this->sapiSatuJenis($shipment, $jenis, $jumlah, $tibaPada, $properties, $customer, $nomor, $j, $user);
            }

            $this->claimSebelumInduksi($shipment, $tibaPada, $user);
        }

        $this->command?->info(sprintf(
            'Demo CPL siap: %d induksi, %d reweight, %d penjualan, %d claim.',
            Induksi::count(), Reweight::count(), Penjualan::count(), Claim::count(),
        ));
    }

    private function sapiSatuJenis(
        Shipment $shipment,
        string $jenis,
        int $jumlah,
        Carbon $tiba,
        $properties,
        array $customer,
        int $nomor,
        int $j,
        User $user,
    ): void {
        $induksiPada = $tiba->copy()->addDays(5);

        // Shipment paling baru belum dijual — jadi ada populasi aktifnya.
        $sudahDijual = $nomor <= 90;

        for ($k = 1; $k <= $jumlah; $k++) {
            $rfid = sprintf('982%06d%03d%d', $nomor * 1000, $k, $j);

            // Jenis ikut masuk ke nomor anting. Tanpa itu, Steer dan Bull di
            // shipment yang sama menghasilkan nomor kembar — dan pasangan
            // shipment + ear tag memang dijaga unik, jadi langsung ditolak.
            $earTag = (string) ($nomor * 1000 + $j * 100 + $k);

            if (Induksi::where('shipment_id', $shipment->id)->where('rfid', $rfid)->exists()) {
                continue;
            }

            $beratInduksi = 270 + $j * 25 + ($k % 9) * 4;

            $induksi = Induksi::create([
                'shipment_id' => $shipment->id,
                'rfid' => $rfid,
                'ear_tag' => $earTag,
                'tanggal_induksi' => $induksiPada,
                'berat_induksi' => $beratInduksi,
                'pen' => (string) (600 + ($k % 5)),
                'gigi' => 'I0',
                'frame' => ['S', 'M', 'L'][$k % 3],
                'kode_prop' => $properties[$k % 3]->kode,
                'asal' => $properties[$k % 3]->daerah,
                'jenis' => $jenis,
            ]);

            // Sekitar satu dari sepuluh ekor tidak di-reweight. Inilah yang
            // dulu bikin ADG RWT salah hitung kalau ikut dianggap nol.
            $adaReweight = $k % 10 !== 0;
            $beratReweight = null;
            $reweightPada = $induksiPada->copy()->addDays(95);

            if ($adaReweight) {
                $beratReweight = $beratInduksi + 95 * (1.7 + ($k % 7) * 0.12);

                Reweight::create([
                    'induksi_id' => $induksi->id,
                    'tanggal_reweight' => $reweightPada,
                    'berat_reweight' => round($beratReweight, 1),
                    'pen_awal' => $induksi->pen,
                    'pen_akhir' => 'HP'.(1 + $k % 3),
                ]);
            }

            if (! $sudahDijual) {
                continue;
            }

            $jualPada = $induksiPada->copy()->addDays(150);
            $beratJual = $beratInduksi + 150 * (1.75 + ($k % 6) * 0.1);

            Penjualan::create([
                'induksi_id' => $induksi->id,
                'tanggal' => $jualPada,
                'berat' => round($beratJual, 1),
                'customer' => $customer[$k % 3],
                'no_invoice' => sprintf('%04d/INV-SCK/%s/26', $nomor * 10 + ($k % 3), $this->romawi($jualPada->month)),
                'harga_per_kg' => 52_000 + ($k % 4) * 500,
                'total' => round($beratJual * (52_000 + ($k % 4) * 500)),
                'status_sapi' => $k % 25 === 0 ? 'Salvage' : 'Sehat',
            ]);

            // Sesekali ada yang mati setelah induksi.
            if ($k % 23 === 0) {
                Claim::create([
                    'shipment_id' => $shipment->id,
                    'induksi_id' => $induksi->id,
                    'rfid' => $rfid,
                    'ear_tag' => $earTag,
                    'tanggal_kejadian' => $induksiPada->copy()->addDays(40),
                    'jenis_claim' => 'salvage',
                    'fase' => 'sesudah_induksi',
                    'diagnosa' => 'Pincang kronis',
                    'berat' => $beratInduksi + 30,
                    'status_klaim' => 'disetujui',
                    'keterangan' => 'Dijual salvage setelah tidak membaik',
                    'dibuat_oleh' => $user->id,
                ]);
            }
        }
    }

    /** Kasus tersering: mati sebelum sempat diinduksi, jadi tanpa induksi_id. */
    private function claimSebelumInduksi(Shipment $shipment, Carbon $tiba, User $user): void
    {
        if (Claim::where('shipment_id', $shipment->id)->where('fase', 'sebelum_induksi')->exists()) {
            return;
        }

        $diagnosa = ['Pneumonia', 'Dehidrasi berat', 'Bloat'];

        foreach (range(1, 2) as $n) {
            Claim::create([
                'shipment_id' => $shipment->id,
                'induksi_id' => null,
                'ear_tag' => 'X'.$shipment->nomor.$n,
                'tanggal_kejadian' => $tiba->copy()->addDays($n * 2),
                'jenis_claim' => 'mati',
                'fase' => 'sebelum_induksi',
                'diagnosa' => $diagnosa[($shipment->nomor + $n) % 3],
                'status_klaim' => 'diajukan',
                'keterangan' => 'Mati di pen sebelum induksi',
                'dibuat_oleh' => $user->id,
            ]);
        }
    }

    private function romawi(int $bulan): string
    {
        return [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$bulan];
    }
}
