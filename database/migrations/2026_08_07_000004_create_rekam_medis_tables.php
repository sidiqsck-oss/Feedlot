<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rekam medis dari Google Sheets dokter hewan.
 *
 * PENTING: data ini TIDAK memotong stok. Stok sudah berkurang saat dokter
 * mengambil barang dari gudang (lihat tabel pengeluaran). Kalau pemakaian per
 * ekor ikut memotong stok, stoknya kepotong dua kali untuk barang yang sama.
 *
 * Gunanya di sini: rekam medis, dan menghitung biaya obat per ekor sapi —
 * angka yang di sistem lama tidak bisa dilihat sama sekali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->nullable()->constrained('shipments');
            $table->string('shipment_teks')->nullable();
            $table->string('ear_tag')->nullable();
            $table->date('tanggal');
            $table->foreignId('petugas_id')->nullable()->constrained('petugas');
            $table->string('penanggung_jawab_teks')->nullable();
            $table->string('pen_asal')->nullable();
            $table->string('diagnosa')->nullable();
            $table->decimal('berat_badan', 8, 2)->nullable();
            $table->string('kondisi')->nullable();

            // Sidik jari baris sumber. Bikin impor aman diulang: baris yang
            // sama tidak akan masuk dua kali. Ini pengganti tambalan payload
            // di workflow lama yang harus menghapus file JSON supaya tidak
            // terproses ulang.
            $table->string('hash_baris', 64)->unique();

            $table->timestamps();

            $table->index(['ear_tag', 'tanggal']);
            $table->index('tanggal');
        });

        Schema::create('treatment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_id')->constrained('treatment')->cascadeOnDelete();

            // Boleh kosong. Kalau nama obat yang ditulis dokter belum ada
            // aliasnya, barisnya tetap disimpan dan ditandai perlu dipetakan —
            // tidak dibuang diam-diam seperti di sistem lama.
            $table->foreignId('barang_id')->nullable()->constrained('barang');

            $table->string('nama_obat_asli');
            $table->string('kategori')->nullable();
            $table->decimal('dosis', 12, 3)->nullable();
            $table->string('satuan_dosis', 20)->nullable();
            $table->timestamps();

            $table->index('barang_id');
        });

        // Jejak tiap impor dari Dropbox / Google Sheets. Pengganti notifikasi
        // Telegram yang cuma bilang berhasil/gagal tanpa detail.
        Schema::create('import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sumber');
            $table->timestamp('mulai');
            $table->timestamp('selesai')->nullable();
            $table->unsignedInteger('jumlah_baris')->default(0);
            $table->unsignedInteger('jumlah_baru')->default(0);
            $table->unsignedInteger('jumlah_dilewati')->default(0);
            $table->enum('status', ['jalan', 'sukses', 'gagal'])->default('jalan');
            $table->text('pesan')->nullable();
            $table->json('detail')->nullable();
            $table->timestamps();

            $table->index(['sumber', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_logs');
        Schema::dropIfExists('treatment_items');
        Schema::dropIfExists('treatment');
    }
};
