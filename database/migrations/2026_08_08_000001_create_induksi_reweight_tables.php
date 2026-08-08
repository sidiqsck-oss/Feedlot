<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data induksi dan reweight sapi.
 *
 * Struktur kolomnya diambil apa adanya dari berkas Excel yang sekarang dipakai
 * (sheet INDUKSI dan RWT di "Database Induksi Reweight.xlsx"), supaya berkas
 * yang sudah ada bisa langsung diimpor tanpa diketik ulang.
 *
 * SOAL IDENTITAS SAPI — ada dua kunci, dan keduanya perlu:
 *
 *   shipment + rfid     : identitas utama. RFID adalah cip fisik di telinga
 *                         sapi, jadi inilah penanda yang sebenarnya.
 *
 *   shipment + ear_tag  : jembatan ke rekam medis. Sheet dokter hewan hanya
 *                         mencatat Shipment dan Ear Tag — tidak ada kolom RFID
 *                         sama sekali — jadi tanpa kunci ini, biaya obat per
 *                         ekor tidak bisa dihitung.
 *
 * Ear tag sendiri TIDAK unik: di data lama ada 40 nomor yang dipakai ulang di
 * shipment berbeda. Karena itu keunikannya selalu dipasangkan dengan shipment,
 * tidak pernah berdiri sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('induksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments');

            $table->string('rfid', 50);
            $table->string('ear_tag', 50);

            $table->date('tanggal_induksi')->nullable();
            $table->decimal('berat_induksi', 8, 2)->nullable();

            $table->string('pen', 30)->nullable();
            $table->string('gigi', 20)->nullable();
            $table->string('frame', 30)->nullable();
            $table->string('kode_prop', 30)->nullable();
            $table->string('data_prop', 100)->nullable();
            $table->string('asal', 100)->nullable();
            $table->string('jenis', 50)->nullable();

            $table->foreignId('import_batch_id')->nullable()->constrained('import_batch')->nullOnDelete();
            $table->timestamps();

            // Identitas utama.
            $table->unique(['shipment_id', 'rfid']);

            // Jembatan ke rekam medis dokter, yang tidak punya RFID.
            $table->unique(['shipment_id', 'ear_tag']);

            $table->index('ear_tag');
            $table->index('tanggal_induksi');
        });

        Schema::create('reweight', function (Blueprint $table) {
            $table->id();

            // Menempel ke ekor yang sudah diinduksi, bukan berdiri sendiri.
            // Reweight untuk sapi yang belum pernah diinduksi tidak masuk akal,
            // dan foreign key ini yang menahannya.
            $table->foreignId('induksi_id')->constrained('induksi')->cascadeOnDelete();

            $table->date('tanggal_reweight')->nullable();
            $table->decimal('berat_reweight', 8, 2)->nullable();
            $table->string('pen_awal', 30)->nullable();
            $table->string('pen_akhir', 30)->nullable();

            $table->foreignId('import_batch_id')->nullable()->constrained('import_batch')->nullOnDelete();
            $table->timestamps();

            // Satu ekor bisa ditimbang ulang lebih dari sekali, tapi tidak dua
            // kali di tanggal yang sama — itu tanda berkas terimpor dobel.
            $table->unique(['induksi_id', 'tanggal_reweight']);

            $table->index('tanggal_reweight');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reweight');
        Schema::dropIfExists('induksi');
    }
};
