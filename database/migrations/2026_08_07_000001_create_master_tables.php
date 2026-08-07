<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel master modul OVK.
 *
 * Catatan penamaan: tabelnya "barang", bukan "obat". Isinya campur — selain
 * obat ada alkes (pisau bedah, sarung tangan, tabung sample darah) dan bahan
 * habis pakai (alkohol, jarum suntik). Menamainya "obat" bikin janggal terus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kategori_barang', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->string('keterangan')->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('kontak')->nullable();
            $table->string('telepon')->nullable();
            $table->text('alamat')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->index('aktif');
        });

        // Orang yang mengambil barang dari gudang: dokter dan operator.
        // Nama yang sama juga muncul sebagai "Penanggung Jawab" di sheet rekam
        // medis dokter, jadi satu master ini dipakai di dua tempat.
        Schema::create('petugas', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->enum('peran', ['dokter', 'operator', 'lainnya'])->default('operator');
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->unique('nama');
            $table->index('aktif');
        });

        // Format kode: SCK83, SCK90, dst. Di form kertas kolom SHIPMENT sudah
        // tercetak "SCK" dan yang ditulis tangan cuma angkanya — normalisasi
        // dari sistem lama dibawa ke sini (lihat Shipment::normalisasiKode).
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->unsignedInteger('nomor')->nullable();
            $table->date('tanggal_masuk')->nullable();
            $table->boolean('aktif')->default(true);
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->index('aktif');
        });

        Schema::create('barang', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->foreignId('kategori_barang_id')->constrained('kategori_barang');

            // Satuan stok, apa adanya per barang. Sengaja TIDAK ada konversi
            // global: alkohol per liter, sarung tangan per pcs, Limoxin per
            // botol. Memaksa satu satuan seragam cuma bikin data bohong.
            $table->string('satuan', 20);

            // Isi per satuan — hanya untuk barang yang perlu dihitung biaya per
            // unit terkecil. Contoh: 1 botol Limoxin = 100 ml, supaya dosis
            // 20 ml di rekam medis dokter bisa dinilai rupiahnya.
            // Sarung tangan tidak punya isi, jadi dibiarkan kosong.
            $table->decimal('isi_nilai', 12, 3)->nullable();
            $table->string('isi_satuan', 20)->nullable();

            $table->decimal('stok_minimum', 12, 3)->default(0);
            $table->boolean('aktif')->default(true);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['aktif', 'nama']);
        });

        // Nama obat di sheet dokter ditulis bebas: "Limoxin 200", "vit b
        // complex", "Vit B Kompleks". Menggantikan ALIAS_OBAT yang di sistem
        // lama di-hardcode 5 baris di streamlit_app.py — sekarang bisa
        // ditambah lewat UI tanpa ubah kode.
        Schema::create('alias_barang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained('barang')->cascadeOnDelete();
            $table->string('alias');
            $table->timestamps();

            $table->unique('alias');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alias_barang');
        Schema::dropIfExists('barang');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('petugas');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('kategori_barang');
    }
};
