<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Melengkapi kolom sesuai berkas sumber yang sebenarnya.
 *
 * Ditambahkan setelah struktur asli PIC NT, Cattle Performance Log, dan sheet
 * Transaksi di SJ INV diketahui — supaya berkas yang sudah ada bisa diimpor
 * utuh tanpa ada kolom yang terbuang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('region')->nullable()->after('nama');

            // Local Government Area. Inilah yang dipakai sebagai "Daerah"
            // di berkas pembelian, jadi disimpan terpisah dari region.
            $table->string('lga')->nullable()->after('region');
        });

        Schema::table('pembelian_shipment', function (Blueprint $table) {
            $table->string('importir')->nullable()->after('jumlah_ekor');
            $table->decimal('harga_usd', 15, 2)->nullable()->after('importir');
            $table->string('daerah')->nullable()->after('harga_usd');

            /*
             * Angka susut per rombongan, apa adanya dari berkas pembelian.
             *
             * Ini rekapan di tingkat shipment, sementara tabel claim mencatat
             * per ekor. Keduanya sengaja disimpan: yang ini angka yang
             * disepakati dengan importir, yang itu rinciannya. Selisih antara
             * keduanya justru informasi — tanda ada yang belum tercatat.
             */
            $table->unsignedInteger('salvage_jumlah')->nullable()->after('daerah');
            $table->decimal('salvage_persen', 6, 3)->nullable()->after('salvage_jumlah');
            $table->unsignedInteger('mati_jumlah')->nullable()->after('salvage_persen');
            $table->decimal('mati_persen', 6, 3)->nullable()->after('mati_jumlah');
            $table->unsignedInteger('bunting_jumlah')->nullable()->after('mati_persen');
            $table->decimal('bunting_persen', 6, 3)->nullable()->after('bunting_jumlah');
        });

        Schema::table('penjualan', function (Blueprint $table) {
            $table->string('no_surat_jalan')->nullable()->after('no_invoice');
            $table->string('kode_customer')->nullable()->after('customer');
            $table->string('nama_barang')->nullable()->after('kode_customer');
            $table->string('satuan', 20)->nullable()->after('berat');

            // Realisasi dan potongan dicatat terpisah dari total, karena
            // ketiganya muncul sebagai kolom sendiri di surat jalan.
            $table->decimal('realisasi', 18, 2)->nullable()->after('harga_per_kg');
            $table->decimal('potongan', 18, 2)->nullable()->after('total');

            $table->index('no_surat_jalan');
        });
    }

    public function down(): void
    {
        Schema::table('penjualan', function (Blueprint $table) {
            $table->dropIndex(['no_surat_jalan']);
            $table->dropColumn([
                'no_surat_jalan', 'kode_customer', 'nama_barang',
                'satuan', 'realisasi', 'potongan',
            ]);
        });

        Schema::table('pembelian_shipment', function (Blueprint $table) {
            $table->dropColumn([
                'importir', 'harga_usd', 'daerah',
                'salvage_jumlah', 'salvage_persen',
                'mati_jumlah', 'mati_persen',
                'bunting_jumlah', 'bunting_persen',
            ]);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['region', 'lga']);
        });
    }
};
