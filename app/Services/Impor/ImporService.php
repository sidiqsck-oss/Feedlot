<?php

namespace App\Services\Impor;

use App\Models\ImportBaris;
use App\Models\ImportBatch;
use App\Models\Induksi;
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
        Shipment $shipment,
        User $user,
    ): ImportBatch {
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
            'shipment_id' => $shipment->id,
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
        Shipment $shipment,
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

            $masalah = $jenis === TemplatImpor::INDUKSI
                ? $this->periksaInduksi($data, $shipment, $baris['nomor'], $rfidTerlihat, $earTagTerlihat)
                : $this->periksaReweight($data, $shipment);

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

        $jumlah = ['baru' => 0, 'dilewati' => 0, 'gagal' => 0];

        try {
            $batch->baris()->valid()->chunkById(200, function ($potongan) use ($batch, &$jumlah) {
                foreach ($potongan as $baris) {
                    DB::transaction(function () use ($batch, $baris, &$jumlah) {
                        $hasil = $batch->jenis === TemplatImpor::INDUKSI
                            ? $this->simpanInduksi($batch, $baris)
                            : $this->simpanReweight($batch, $baris);

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

    private function keTeks(mixed $nilai): ?string
    {
        $teks = trim((string) ($nilai ?? ''));

        return $teks === '' ? null : $teks;
    }
}
