<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kerangka impor berkas.
 *
 * Satu unggahan = satu batch. Alurnya sengaja dua tahap:
 *
 *   unggah → baca & periksa → PRATINJAU → (konfirmasi) → proses
 *
 * Pratinjau ada karena berkas dari lapangan hampir tidak pernah bersih di
 * percobaan pertama — kolom bergeser, tanggal salah format, ada baris kosong di
 * tengah. Melihat dulu apa yang akan masuk jauh lebih murah daripada
 * membatalkan ratusan baris yang terlanjur tersimpan.
 *
 * Baris disimpan mentah di import_baris supaya kesalahan bisa dilaporkan per
 * baris ("baris 47: RFID kosong"), bukan sekadar "impor gagal".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batch', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 30);
            $table->string('nama_berkas');

            // Sidik jari isi berkas. Mengunggah berkas yang sama persis dua
            // kali langsung ketahuan sebelum sebaris pun diproses.
            $table->string('hash_berkas', 64);

            $table->foreignId('shipment_id')->nullable()->constrained('shipments');

            $table->enum('status', [
                'dibaca',    // sedang diurai dari berkas
                'pratinjau', // siap dilihat, menunggu konfirmasi
                'diproses',  // sedang dimasukkan ke tabel tujuan
                'selesai',
                'gagal',
                'dibatalkan',
            ])->default('dibaca');

            $table->unsignedInteger('jumlah_baris')->default(0);
            $table->unsignedInteger('jumlah_valid')->default(0);
            $table->unsignedInteger('jumlah_bermasalah')->default(0);
            $table->unsignedInteger('jumlah_baru')->default(0);
            $table->unsignedInteger('jumlah_diperbarui')->default(0);
            $table->unsignedInteger('jumlah_dilewati')->default(0);

            $table->text('pesan')->nullable();
            $table->json('ringkasan')->nullable();

            $table->foreignId('diunggah_oleh')->constrained('users');
            $table->timestamp('diproses_pada')->nullable();
            $table->timestamps();

            $table->index(['jenis', 'status']);
            $table->index('hash_berkas');
        });

        Schema::create('import_baris', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained('import_batch')->cascadeOnDelete();

            // Nomor baris di berkas asli, bukan nomor urut hasil baca. Operator
            // mencari kesalahannya dengan membuka Excel dan melompat ke baris
            // itu, jadi angkanya harus cocok dengan yang dia lihat di sana.
            $table->unsignedInteger('nomor_baris');

            $table->json('data_mentah');
            $table->enum('status', ['valid', 'bermasalah', 'diproses', 'dilewati', 'gagal'])->default('valid');
            $table->json('masalah')->nullable();
            $table->string('catatan')->nullable();
            $table->timestamps();

            $table->index(['import_batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_baris');
        Schema::dropIfExists('import_batch');
    }
};
