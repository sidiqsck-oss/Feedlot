<?php

namespace App\Services\Impor;

use App\Models\ImportBaris;
use App\Models\ImportBatch;
use App\Models\Induksi;
use App\Models\PembelianShipment;
use App\Models\Penjualan;
use App\Models\Property;
use App\Models\Reweight;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Impor berkas induksi dan reweight.
 *
 * Dua tahap, dan pemisahannya disengaja:
 *
 *   siapkan() — baca berkas, periksa tiap baris, simpan hasilnya sebagai
 *               pratinjau. BELUM ada satu baris pun yang masuk ke tabel tujuan.
 *
 *   proses()  — setelah dikonfirmasi, baru baris yang valid dimasukkan.
 *
 * Berkas dari lapangan hampir tidak pernah bersih di percobaan pertama, dan
 * membatalkan ratusan baris yang terlanjur masuk jauh lebih mahal daripada
 * melihatnya dulu.
 */
class ImporService
{
    public function __construct(private readonly PembacaTabel $pembaca) {}

    /**
     * Baca berkas jadi pratinjau.
     *
     * @throws RuntimeException kalau berkasnya sudah pernah diunggah atau
     *                          kolom wajibnya tidak ada
     */
    public function siapkan(
        UploadedFile $berkas,
        string $jenis,
        ?Shipment $shipment,
        User $user,
    ): ImportBatch {
        // Property, pembelian, dan penjualan mencakup banyak shipment
        // sekaligus; hanya induksi dan reweight yang berkasnya milik satu
        // rombongan tertentu.
        if (TemplatImpor::perShipment($jenis) && ! $shipment) {
            throw new RuntimeException('Jenis berkas ini harus dipasangkan ke satu shipment.');
        }

        $definisi = TemplatImpor::definisi($jenis);
        $hash = hash_file('sha256', $berkas->getRealPath());

        // Berkas yang sama persis pernah diunggah dan berhasil diproses.
        // Ditahan di sini, sebelum sebaris pun dibaca.
        $kembar = ImportBatch::where('hash_berkas', $hash)
            ->where('jenis', $jenis)
            ->whereIn('status', ['selesai', 'pratinjau', 'diproses'])
            ->first();

        if ($kembar) {
            throw new RuntimeException(sprintf(
                'Berkas ini sudah pernah diunggah pada %s dengan status "%s". Kalau memang mau diulang, batalkan dulu unggahan sebelumnya.',
                $kembar->created_at->translatedFormat('d F Y H:i'),
                $kembar->status,
            ));
        }

        $jalur = $berkas->getRealPath();

        // Periksa kolom wajib dari baris judul dulu. Berkas yang kolomnya
        // memang tidak ada tidak perlu dibaca sampai habis.
        $intip = $this->pembaca->intip($jalur, 1, $definisi['lembar'], $definisi['kata_kunci_judul']);

        if ($intip === []) {
            throw new RuntimeException('Berkas tidak berisi data apa pun di bawah baris judul.');
        }

        $hilang = TemplatImpor::kolomWajibHilang($jenis, array_keys($intip[0]['data']));

        if ($hilang !== []) {
            throw new RuntimeException(
                'Kolom wajib tidak ditemukan: '.implode(', ', $hilang).
                '. Pastikan berkasnya memakai templat yang benar.'
            );
        }

        $batch = ImportBatch::create([
            'jenis' => $jenis,
            'nama_berkas' => $berkas->getClientOriginalName(),
            'hash_berkas' => $hash,
            'shipment_id' => $shipment?->id,
            'status' => 'dibaca',
            'diunggah_oleh' => $user->id,
        ]);

        try {
            $this->bacaKeBaris($batch, $jalur, $jenis, $definisi, $shipment);
        } catch (Throwable $e) {
            $batch->update(['status' => 'gagal', 'pesan' => $e->getMessage()]);

            throw new RuntimeException('Gagal membaca berkas: '.$e->getMessage(), previous: $e);
        }

        return $batch->fresh();
    }

    private function bacaKeBaris(
        ImportBatch $batch,
        string $jalur,
        string $jenis,
        array $definisi,
        ?Shipment $shipment,
    ): void {
        // Kunci yang sudah terlihat DI DALAM berkas ini. Dobel di dalam satu
        // berkas dan dobel terhadap database adalah dua masalah berbeda, dan
        // pesannya juga harus berbeda supaya operator tahu harus benahi apa.
        $rfidTerlihat = [];
        $earTagTerlihat = [];

        $jumlah = ['baris' => 0, 'valid' => 0, 'bermasalah' => 0];
        $tumpukan = [];

        foreach ($this->pembaca->baca($jalur, $definisi['lembar'], $definisi['kata_kunci_judul']) as $baris) {
            $jumlah['baris']++;

            $data = TemplatImpor::petakan($jenis, $baris['data']);

            $masalah = match ($jenis) {
                TemplatImpor::INDUKSI => $this->periksaInduksi($data, $shipment, $baris['nomor'], $rfidTerlihat, $earTagTerlihat),
                TemplatImpor::REWEIGHT => $this->periksaReweight($data, $shipment),
                TemplatImpor::PROPERTY => $this->periksaProperty($data),
                TemplatImpor::PEMBELIAN => $this->periksaPembelian($data),
                TemplatImpor::PENJUALAN => $this->periksaPenjualan($data),
            };

            $status = $masalah === [] ? 'valid' : 'bermasalah';
            $jumlah[$status === 'valid' ? 'valid' : 'bermasalah']++;

            $tumpukan[] = [
                'import_batch_id' => $batch->id,
                'nomor_baris' => $baris['nomor'],
                'data_mentah' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'status' => $status,
                'masalah' => $masalah === [] ? null : json_encode($masalah, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Disisipkan bertahap, bukan satu per satu maupun sekaligus di
            // akhir: satu per satu terlalu banyak bolak-balik ke database,
            // sekaligus menahan seluruh berkas di memori.
            if (count($tumpukan) >= 200) {
                ImportBaris::insert($tumpukan);
                $tumpukan = [];
            }
        }

        if ($tumpukan !== []) {
            ImportBaris::insert($tumpukan);
        }

        $batch->update([
            'status' => 'pratinjau',
            'jumlah_baris' => $jumlah['baris'],
            'jumlah_valid' => $jumlah['valid'],
            'jumlah_bermasalah' => $jumlah['bermasalah'],
        ]);
    }

    /**
     * @param  int  $nomorBaris  nomor baris di berkas asli — dipakai menunjuk
     *                           baris kembarnya, supaya operator bisa langsung
     *                           membuka Excel dan melompat ke sana
     * @return array<int, string>
     */
    private function periksaInduksi(
        array $data,
        Shipment $shipment,
        int $nomorBaris,
        array &$rfidTerlihat,
        array &$earTagTerlihat,
    ): array {
        $masalah = [];

        $rfid = trim((string) ($data['rfid'] ?? ''));
        $earTag = trim((string) ($data['ear_tag'] ?? ''));

        if ($rfid === '') {
            $masalah[] = 'RFID kosong';
        } elseif (isset($rfidTerlihat[$rfid])) {
            $masalah[] = "RFID kembar dengan baris {$rfidTerlihat[$rfid]} di berkas ini";
        } elseif (Induksi::where('shipment_id', $shipment->id)->where('rfid', $rfid)->exists()) {
            $masalah[] = 'RFID ini sudah ada di data induksi shipment '.$shipment->kode;
        }

        if ($earTag === '') {
            $masalah[] = 'Ear tag kosong';
        } elseif (isset($earTagTerlihat[$earTag])) {
            $masalah[] = "Ear tag kembar dengan baris {$earTagTerlihat[$earTag]} di berkas ini";
        } elseif (Induksi::where('shipment_id', $shipment->id)->where('ear_tag', $earTag)->exists()) {
            $masalah[] = 'Ear tag ini sudah dipakai di shipment '.$shipment->kode;
        }

        if ($data['tanggal_induksi'] && ! $this->tanggalSah($data['tanggal_induksi'])) {
            $masalah[] = 'Tanggal induksi tidak terbaca: '.$data['tanggal_induksi'];
        }

        if ($data['berat_induksi'] !== null && $data['berat_induksi'] !== '' && ! is_numeric($data['berat_induksi'])) {
            $masalah[] = 'Berat induksi bukan angka: '.$data['berat_induksi'];
        }

        // Yang dicatat nomor baris ASLI di berkas, bukan urutan pembacaan.
        // Pesan "kembar dengan baris 7" hanya berguna kalau angkanya cocok
        // dengan yang dilihat operator saat membuka Excel.
        if ($rfid !== '' && ! isset($rfidTerlihat[$rfid])) {
            $rfidTerlihat[$rfid] = $nomorBaris;
        }

        if ($earTag !== '' && ! isset($earTagTerlihat[$earTag])) {
            $earTagTerlihat[$earTag] = $nomorBaris;
        }

        return $masalah;
    }

    /** @return array<int, string> */
    private function periksaReweight(array $data, Shipment $shipment): array
    {
        $masalah = [];

        $rfid = trim((string) ($data['rfid'] ?? ''));
        $tanggal = $data['tanggal_reweight'] ?? null;

        if ($rfid === '') {
            $masalah[] = 'RFID kosong';
        } else {
            $induksi = Induksi::where('shipment_id', $shipment->id)->where('rfid', $rfid)->first();

            if (! $induksi) {
                // Reweight untuk sapi yang belum pernah diinduksi tidak masuk
                // akal. Biasanya berarti berkas induksinya belum diunggah, atau
                // shipment-nya salah pilih.
                $masalah[] = 'RFID tidak ada di data induksi shipment '.$shipment->kode.'. Unggah berkas induksinya dulu.';
            } elseif ($tanggal && $this->tanggalSah($tanggal)
                && Reweight::where('induksi_id', $induksi->id)
                    ->whereDate('tanggal_reweight', Carbon::parse($tanggal)->toDateString())
                    ->exists()
            ) {
                $masalah[] = 'Sapi ini sudah punya data reweight di tanggal yang sama';
            }
        }

        if (! $tanggal) {
            $masalah[] = 'Tanggal reweight kosong';
        } elseif (! $this->tanggalSah($tanggal)) {
            $masalah[] = 'Tanggal reweight tidak terbaca: '.$tanggal;
        }

        if ($data['berat_reweight'] !== null && $data['berat_reweight'] !== '' && ! is_numeric($data['berat_reweight'])) {
            $masalah[] = 'Berat reweight bukan angka: '.$data['berat_reweight'];
        }

        return $masalah;
    }

    /** @return array<int, string> */
    private function periksaProperty(array $data): array
    {
        $masalah = [];

        if (trim((string) ($data['kode'] ?? '')) === '') {
            $masalah[] = 'Kode PIC kosong';
        }

        if (trim((string) ($data['nama'] ?? '')) === '') {
            $masalah[] = 'Nama property kosong';
        }

        return $masalah;
    }

    /** @return array<int, string> */
    private function periksaPembelian(array $data): array
    {
        $masalah = [];

        $kode = Shipment::normalisasiKode((string) ($data['shipment'] ?? ''));

        if ($kode === '') {
            $masalah[] = 'Kolom Ship kosong';
        }

        if (trim((string) ($data['jenis'] ?? '')) === '') {
            $masalah[] = 'Kolom Jenis kosong';
        }

        foreach (['berat_muat', 'berat_tiba', 'total_jenis', 'harga_usd'] as $kolom) {
            $nilai = $data[$kolom] ?? null;

            if ($nilai !== null && $nilai !== '' && ! is_numeric($nilai)) {
                $masalah[] = "Kolom {$kolom} bukan angka: {$nilai}";
            }
        }

        return $masalah;
    }

    /** @return array<int, string> */
    private function periksaPenjualan(array $data): array
    {
        $masalah = [];

        $rfid = trim((string) ($data['rfid'] ?? ''));
        $kode = Shipment::normalisasiKode((string) ($data['shipment'] ?? ''));

        if ($rfid === '') {
            $masalah[] = 'Nomor RFID kosong';
        } elseif ($kode === '') {
            $masalah[] = 'Kolom Ship kosong, jadi sapinya tidak bisa dipastikan';
        } elseif (! $this->cariInduksi($kode, $rfid)) {
            // Penjualan untuk sapi yang belum pernah diinduksi tidak masuk
            // akal. Biasanya berarti berkas induksi shipment itu belum diunggah.
            $masalah[] = "RFID tidak ada di data induksi {$kode}. Unggah berkas induksinya dulu.";
        }

        if (! $this->tanggalSah($data['tanggal'] ?? null)) {
            $masalah[] = 'Tanggal penjualan kosong atau tidak terbaca';
        }

        if (($data['berat'] ?? null) !== null && $data['berat'] !== '' && ! is_numeric($data['berat'])) {
            $masalah[] = 'Jumlah Berat bukan angka: '.$data['berat'];
        }

        return $masalah;
    }

    private function cariInduksi(string $kodeShipment, string $rfid): ?Induksi
    {
        $shipment = Shipment::where('kode', $kodeShipment)->first();

        if (! $shipment) {
            return null;
        }

        return Induksi::where('shipment_id', $shipment->id)->where('rfid', $rfid)->first();
    }

    /**
     * Masukkan baris yang valid ke tabel tujuan.
     *
     * Baris bermasalah dilewati, tidak menggagalkan seluruh batch — berkas 300
     * baris dengan 2 baris rusak tetap berguna, dan yang 2 itu bisa dibetulkan
     * lalu diunggah terpisah.
     */
    public function proses(ImportBatch $batch): ImportBatch
    {
        if ($batch->status !== 'pratinjau') {
            throw new RuntimeException("Unggahan ini berstatus \"{$batch->status}\" dan tidak bisa diproses.");
        }

        $batch->update(['status' => 'diproses']);

        $jumlah = ['baru' => 0, 'diperbarui' => 0, 'dilewati' => 0, 'gagal' => 0];

        try {
            $batch->baris()->valid()->chunkById(200, function ($potongan) use ($batch, &$jumlah) {
                foreach ($potongan as $baris) {
                    DB::transaction(function () use ($batch, $baris, &$jumlah) {
                        $hasil = match ($batch->jenis) {
                            TemplatImpor::INDUKSI => $this->simpanInduksi($batch, $baris),
                            TemplatImpor::REWEIGHT => $this->simpanReweight($batch, $baris),
                            TemplatImpor::PROPERTY => $this->simpanProperty($baris),
                            TemplatImpor::PEMBELIAN => $this->simpanPembelian($batch, $baris),
                            TemplatImpor::PENJUALAN => $this->simpanPenjualan($batch, $baris),
                        };

                        $jumlah[$hasil]++;
                    });
                }
            });
        } catch (Throwable $e) {
            $batch->update(['status' => 'gagal', 'pesan' => $e->getMessage()]);

            throw $e;
        }

        $batch->update([
            'status' => 'selesai',
            'jumlah_baru' => $jumlah['baru'],
            'jumlah_diperbarui' => $jumlah['diperbarui'],
            'jumlah_dilewati' => $jumlah['dilewati'] + $batch->jumlah_bermasalah,
            'diproses_pada' => now(),
        ]);

        return $batch->fresh();
    }

    private function simpanInduksi(ImportBatch $batch, ImportBaris $baris): string
    {
        $d = $baris->data_mentah;

        // Diperiksa ulang di sini, bukan cuma saat pratinjau. Antara pratinjau
        // dan konfirmasi bisa ada unggahan lain yang memasukkan RFID yang sama.
        $sudahAda = Induksi::where('shipment_id', $batch->shipment_id)
            ->where(fn ($q) => $q->where('rfid', $d['rfid'])->orWhere('ear_tag', $d['ear_tag']))
            ->exists();

        if ($sudahAda) {
            $baris->update(['status' => 'dilewati', 'catatan' => 'Sudah ada saat diproses']);

            return 'dilewati';
        }

        Induksi::create([
            'shipment_id' => $batch->shipment_id,
            'rfid' => trim((string) $d['rfid']),
            'ear_tag' => trim((string) $d['ear_tag']),
            'tanggal_induksi' => $this->keTanggal($d['tanggal_induksi'] ?? null),
            'berat_induksi' => $this->keAngka($d['berat_induksi'] ?? null),
            'pen' => $this->keTeks($d['pen'] ?? null),
            'gigi' => $this->keTeks($d['gigi'] ?? null),
            'frame' => $this->keTeks($d['frame'] ?? null),
            'kode_prop' => $this->keTeks($d['kode_prop'] ?? null),
            'data_prop' => $this->keTeks($d['data_prop'] ?? null),
            'asal' => $this->keTeks($d['asal'] ?? null),
            'jenis' => $this->keTeks($d['jenis'] ?? null),
            'import_batch_id' => $batch->id,
        ]);

        $baris->update(['status' => 'diproses']);

        return 'baru';
    }

    private function simpanReweight(ImportBatch $batch, ImportBaris $baris): string
    {
        $d = $baris->data_mentah;

        $induksi = Induksi::where('shipment_id', $batch->shipment_id)
            ->where('rfid', trim((string) $d['rfid']))
            ->first();

        if (! $induksi) {
            $baris->update(['status' => 'dilewati', 'catatan' => 'Data induksinya tidak ditemukan saat diproses']);

            return 'dilewati';
        }

        $tanggal = $this->keTanggal($d['tanggal_reweight'] ?? null);

        $sudahAda = Reweight::where('induksi_id', $induksi->id)
            ->whereDate('tanggal_reweight', $tanggal)
            ->exists();

        if ($sudahAda) {
            $baris->update(['status' => 'dilewati', 'catatan' => 'Sudah ada saat diproses']);

            return 'dilewati';
        }

        Reweight::create([
            'induksi_id' => $induksi->id,
            'tanggal_reweight' => $tanggal,
            'berat_reweight' => $this->keAngka($d['berat_reweight'] ?? null),
            'pen_awal' => $this->keTeks($d['pen_awal'] ?? null),
            'pen_akhir' => $this->keTeks($d['pen_akhir'] ?? null),
            'import_batch_id' => $batch->id,
        ]);

        $baris->update(['status' => 'diproses']);

        return 'baru';
    }

    /**
     * Property dan pembelian bersifat master: mengunggah ulang berkas yang
     * lebih baru MEMPERBARUI baris yang sudah ada, tidak dilewati. Berbeda
     * dengan induksi dan penjualan yang tiap barisnya satu peristiwa.
     */
    private function simpanProperty(ImportBaris $baris): string
    {
        $d = $baris->data_mentah;

        $property = Property::updateOrCreate(
            ['kode' => trim((string) $d['kode'])],
            [
                'nama' => trim((string) $d['nama']),
                'region' => $this->keTeks($d['region'] ?? null),
                'lga' => $this->keTeks($d['lga'] ?? null),
                'aktif' => true,
            ],
        );

        $baris->update(['status' => 'diproses']);

        return $property->wasRecentlyCreated ? 'baru' : 'diperbarui';
    }

    private function simpanPembelian(ImportBatch $batch, ImportBaris $baris): string
    {
        $d = $baris->data_mentah;

        // Shipment dibuat otomatis kalau belum ada — berkas pembelian sering
        // jadi yang pertama masuk untuk rombongan baru.
        $kode = Shipment::normalisasiKode((string) $d['shipment']);

        $shipment = Shipment::firstOrCreate(
            ['kode' => $kode],
            [
                'nomor' => preg_match('/^SCK(\d+)$/', $kode, $c) ? (int) $c[1] : null,
                'aktif' => true,
            ],
        );

        $pembelian = PembelianShipment::updateOrCreate(
            ['shipment_id' => $shipment->id, 'jenis' => trim((string) $d['jenis'])],
            [
                'tanggal_muat' => $this->keTanggal($d['tanggal_muat'] ?? null),
                'berat_muat' => $this->keAngka($d['berat_muat'] ?? null),
                'tanggal_tiba' => $this->keTanggal($d['tanggal_tiba'] ?? null),
                'berat_tiba' => $this->keAngka($d['berat_tiba'] ?? null),
                'jumlah_ekor' => $this->keBulat($d['total_jenis'] ?? null),
                'importir' => $this->keTeks($d['importir'] ?? null),
                'harga_usd' => $this->keAngka($d['harga_usd'] ?? null),
                'daerah' => $this->keTeks($d['daerah'] ?? null),
                'salvage_jumlah' => $this->keBulat($d['salvage_jumlah'] ?? null),
                'salvage_persen' => $this->keAngka($d['salvage_persen'] ?? null),
                'mati_jumlah' => $this->keBulat($d['mati_jumlah'] ?? null),
                'mati_persen' => $this->keAngka($d['mati_persen'] ?? null),
                'bunting_jumlah' => $this->keBulat($d['bunting_jumlah'] ?? null),
                'bunting_persen' => $this->keAngka($d['bunting_persen'] ?? null),
                'import_batch_id' => $batch->id,
            ],
        );

        $baris->update(['status' => 'diproses']);

        return $pembelian->wasRecentlyCreated ? 'baru' : 'diperbarui';
    }

    private function simpanPenjualan(ImportBatch $batch, ImportBaris $baris): string
    {
        $d = $baris->data_mentah;

        $induksi = $this->cariInduksi(
            Shipment::normalisasiKode((string) $d['shipment']),
            trim((string) $d['rfid']),
        );

        if (! $induksi) {
            $baris->update(['status' => 'dilewati', 'catatan' => 'Data induksinya tidak ditemukan saat diproses']);

            return 'dilewati';
        }

        $tanggal = $this->keTanggal($d['tanggal'] ?? null);

        // Satu ekor dijual sekali di satu tanggal. Baris yang sama diunggah
        // ulang memperbarui, bukan menambah baris kedua.
        $penjualan = Penjualan::updateOrCreate(
            ['induksi_id' => $induksi->id, 'tanggal' => $tanggal],
            [
                'berat' => $this->keAngka($d['berat'] ?? null),
                'satuan' => $this->keTeks($d['satuan'] ?? null),
                'customer' => $this->keTeks($d['customer'] ?? null),
                'kode_customer' => $this->keTeks($d['kode_customer'] ?? null),
                'no_invoice' => $this->keTeks($d['no_invoice'] ?? null),
                'no_surat_jalan' => $this->keTeks($d['no_surat_jalan'] ?? null),
                'nama_barang' => $this->keTeks($d['nama_barang'] ?? null),
                'harga_per_kg' => $this->keAngka($d['harga_per_kg'] ?? null),
                'realisasi' => $this->keAngka($d['realisasi'] ?? null),
                'total' => $this->keAngka($d['total'] ?? null),
                'potongan' => $this->keAngka($d['potongan'] ?? null),
                'status_sapi' => $this->keTeks($d['status_sapi'] ?? null),
                'import_batch_id' => $batch->id,
            ],
        );

        $baris->update(['status' => 'diproses']);

        return $penjualan->wasRecentlyCreated ? 'baru' : 'diperbarui';
    }

    public function batalkan(ImportBatch $batch): ImportBatch
    {
        if ($batch->status === 'selesai') {
            throw new RuntimeException('Unggahan yang sudah diproses tidak bisa dibatalkan.');
        }

        $batch->update(['status' => 'dibatalkan']);

        return $batch->fresh();
    }

    private function tanggalSah(mixed $nilai): bool
    {
        return $this->keTanggal($nilai) !== null;
    }

    private function keTanggal(mixed $nilai): ?string
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $nilai)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function keAngka(mixed $nilai): ?float
    {
        return ($nilai === null || $nilai === '' || ! is_numeric($nilai)) ? null : (float) $nilai;
    }

    private function keBulat(mixed $nilai): ?int
    {
        return ($nilai === null || $nilai === '' || ! is_numeric($nilai)) ? null : (int) round((float) $nilai);
    }

    private function keTeks(mixed $nilai): ?string
    {
        $teks = trim((string) ($nilai ?? ''));

        return $teks === '' ? null : $teks;
    }
}
