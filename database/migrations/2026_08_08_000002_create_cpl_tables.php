<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel pelengkap modul CPL.
 *
 * Melengkapi induksi dan reweight yang sudah ada, sehingga satu baris CPL bisa
 * disusun utuh: pembelian → induksi → reweight → penjualan, dengan claim
 * sebagai jalur keluar selain penjualan.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Properti asal sapi. Dari PIC NT.xlsx.
        // Menjawab pertanyaan yang paling bernilai bisnis: beli dari properti
        // mana yang hasilnya bagus.
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('daerah')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->index('aktif');
        });

        /*
         * Pembelian per shipment.
         *
         * PENTING: datanya per shipment + jenis, BUKAN per ekor. Load Wt dan
         * Feedlot Wt di CPL adalah angka rata-rata rombongan yang disalin ke
         * setiap ekor. Jadi keduanya tidak boleh disajikan seolah hasil
         * timbangan masing-masing sapi.
         */
        Schema::create('pembelian_shipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('jenis', 50);

            $table->date('tanggal_muat')->nullable();
            $table->decimal('berat_muat', 8, 2)->nullable();
            $table->date('tanggal_tiba')->nullable();
            $table->decimal('berat_tiba', 8, 2)->nullable();

            // Jumlah ekor yang benar-benar tiba. Ini titik awal corong
            // shipment: dari sekian yang tiba, berapa yang jadi uang.
            $table->unsignedInteger('jumlah_ekor')->nullable();

            $table->foreignId('import_batch_id')->nullable()->constrained('import_batch')->nullOnDelete();
            $table->timestamps();

            // Pasangan inilah kunci sambungannya ke induksi.
            $table->unique(['shipment_id', 'jenis']);
        });

        // Penjualan. Sementara diimpor dari SJ INV, nanti diganti form.
        Schema::create('penjualan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('induksi_id')->constrained('induksi')->cascadeOnDelete();

            $table->date('tanggal');
            $table->decimal('berat', 8, 2)->nullable();
            $table->string('customer')->nullable();
            $table->string('no_invoice')->nullable();
            $table->decimal('harga_per_kg', 15, 2)->nullable();
            $table->decimal('total', 18, 2)->nullable();

            // Sehat / Salvage — nilai yang benar-benar muncul di data lama.
            $table->string('status_sapi', 30)->nullable();

            $table->foreignId('import_batch_id')->nullable()->constrained('import_batch')->nullOnDelete();
            $table->timestamps();

            // Satu ekor bisa punya lebih dari satu baris kalau ada koreksi;
            // yang dipakai selalu yang terakhir, sama seperti perilaku
            // drop_duplicates(keep='last') di sistem lama.
            $table->index(['induksi_id', 'tanggal']);
            $table->index('tanggal');
            $table->index('no_invoice');
            $table->index('customer');
        });

        /*
         * Claim ke importir: sapi mati, dijual salvage, atau sakit bawaan.
         *
         * induksi_id SENGAJA boleh kosong. Kasus yang paling sering justru mati
         * SEBELUM induksi — sapi itu tidak punya baris induksi sama sekali,
         * jadi kalau claim dipaksa menempel ke induksi, yang paling sering
         * terjadi malah tidak bisa dicatat.
         *
         * Karena itu penambatnya shipment, dan identitas sapi disimpan sebagai
         * teks apa adanya.
         */
        Schema::create('claim', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments');
            $table->foreignId('induksi_id')->nullable()->constrained('induksi')->nullOnDelete();

            $table->string('rfid', 50)->nullable();
            $table->string('ear_tag', 50)->nullable();

            $table->date('tanggal_kejadian');
            $table->enum('jenis_claim', ['mati', 'salvage', 'sakit_bawaan']);
            $table->enum('fase', ['sebelum_induksi', 'sesudah_induksi']);

            $table->string('diagnosa')->nullable();
            $table->decimal('berat', 8, 2)->nullable();
            $table->decimal('nilai_klaim', 18, 2)->nullable();
            $table->enum('status_klaim', ['diajukan', 'disetujui', 'ditolak'])->default('diajukan');
            $table->text('keterangan')->nullable();

            $table->foreignId('dibuat_oleh')->constrained('users');
            $table->timestamps();

            $table->index(['shipment_id', 'tanggal_kejadian']);
            $table->index(['jenis_claim', 'fase']);
            $table->index('tanggal_kejadian');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim');
        Schema::dropIfExists('penjualan');
        Schema::dropIfExists('pembelian_shipment');
        Schema::dropIfExists('properties');
    }
};
