<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase Order dan penerimaan barang.
 *
 * PO boleh dipenuhi bertahap, direvisi (tambah/kurang item), ditutup meski
 * barang kurang, atau dibatalkan. Aturannya:
 *
 *   - "selesai"  : semua item terpenuhi penuh (otomatis)
 *   - "ditutup"  : barang kosong/kurang tapi diputuskan sudahi (manual, wajib alasan)
 *   - "batal"    : dibatalkan — HANYA boleh kalau belum ada barang masuk sama sekali
 *
 * Kalau sudah ada barang masuk, jalurnya "ditutup", bukan "batal". Membatalkan
 * PO yang barangnya sudah sebagian diterima akan bikin penerimaan jadi yatim.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('nomor')->unique();
            $table->date('tanggal');
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->enum('status', [
                'draft',      // masih disusun, belum dikirim ke supplier
                'terbuka',    // sudah jalan, belum ada barang masuk
                'sebagian',   // sebagian barang sudah masuk
                'selesai',    // semua item terpenuhi penuh
                'ditutup',    // disudahi meski kurang
                'batal',      // dibatalkan sebelum ada barang masuk
            ])->default('draft');
            $table->text('catatan')->nullable();

            $table->text('alasan_penutupan')->nullable();
            $table->timestamp('ditutup_pada')->nullable();
            $table->foreignId('ditutup_oleh')->nullable()->constrained('users');

            $table->foreignId('dibuat_oleh')->constrained('users');
            $table->timestamps();

            $table->index(['status', 'tanggal']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barang');
            $table->decimal('qty', 12, 3);
            $table->decimal('harga_satuan', 15, 2)->default(0);

            // Akumulasi dari penerimaan. Sisa PO = qty - qty_diterima.
            // Qty PO tidak boleh direvisi turun di bawah angka ini.
            $table->decimal('qty_diterima', 12, 3)->default(0);

            $table->timestamps();

            $table->unique(['purchase_order_id', 'barang_id']);
        });

        // Jejak perubahan PO. PO itu dokumen komitmen ke supplier, jadi revisi
        // qty, penutupan, dan pembatalan harus ada riwayatnya — bukan diam-diam
        // ter-update lalu hilang jejak seperti di sistem lama.
        Schema::create('purchase_order_riwayat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->enum('aksi', ['dibuat', 'revisi', 'ditutup', 'dibatalkan', 'dibuka_lagi']);
            $table->text('alasan')->nullable();
            $table->json('perubahan')->nullable();
            $table->foreignId('oleh')->constrained('users');
            $table->timestamps();

            $table->index(['purchase_order_id', 'created_at']);
        });

        Schema::create('penerimaan', function (Blueprint $table) {
            $table->id();
            $table->string('nomor')->unique();
            $table->date('tanggal');
            $table->foreignId('supplier_id')->constrained('suppliers');

            // Boleh kosong: barang masuk tanpa PO tetap sah dicatat.
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders');

            $table->string('no_faktur_supplier')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('dibuat_oleh')->constrained('users');
            $table->timestamps();

            $table->index('tanggal');
        });

        Schema::create('penerimaan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penerimaan_id')->constrained('penerimaan')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items');
            $table->foreignId('barang_id')->constrained('barang');
            $table->decimal('qty', 12, 3);
            $table->decimal('harga_satuan', 15, 2);
            $table->decimal('subtotal', 18, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penerimaan_items');
        Schema::dropIfExists('penerimaan');
        Schema::dropIfExists('purchase_order_riwayat');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
