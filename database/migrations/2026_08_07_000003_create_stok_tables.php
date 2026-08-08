<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lot FIFO, pengeluaran, opname, dan kartu stok.
 *
 * PRINSIP UTAMA: stok itu dihitung, bukan disimpan.
 *
 * Di sistem lama, sheet "Stok Obat" punya kolom Stok berupa angka yang
 * di-update tiap transaksi. Satu payload keproses dua kali → angkanya ketambah
 * dua kali, tanpa cara untuk tahu maupun membalikkan. Itu penyebab bug
 * "stok/opname ganda" yang terdokumentasi di komentar proses_ovk.yml.
 *
 * Di sini pergerakan_stok hanya boleh ditambah — tidak pernah di-update, tidak
 * pernah dihapus. Stok = jumlahkan pergerakan. Salah input dibalik dengan baris
 * koreksi berlawanan, sehingga riwayatnya tetap utuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Satu baris penerimaan = satu lot. Inilah yang bikin FIFO jalan:
        // pengeluaran selalu mengambil dari lot dengan tanggal_masuk tertua
        // yang qty_sisa-nya masih ada.
        Schema::create('stok_lot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained('barang');
            $table->foreignId('penerimaan_item_id')->constrained('penerimaan_items');
            $table->date('tanggal_masuk');
            $table->decimal('harga_satuan', 15, 2);
            $table->decimal('qty_masuk', 12, 3);
            $table->decimal('qty_sisa', 12, 3);

            // Belum dipakai. FIFO sudah melacak per lot, jadi tempatnya sudah
            // ada — menyalakan pelacakan kadaluarsa nanti tidak perlu bongkar
            // ulang skema.
            $table->date('tanggal_kadaluarsa')->nullable();
            $table->string('nomor_batch')->nullable();

            $table->timestamps();

            // Urutan pengambilan FIFO. qty_sisa ikut di index supaya lot yang
            // sudah habis bisa dilewati tanpa baca baris.
            $table->index(['barang_id', 'tanggal_masuk', 'id'], 'idx_fifo');
            $table->index(['barang_id', 'qty_sisa']);
        });

        Schema::create('pengeluaran', function (Blueprint $table) {
            $table->id();
            $table->string('nomor')->unique();
            $table->date('tanggal');

            $table->enum('tujuan', ['dokter', 'induksi', 'reweight', 'lainnya']);

            // Siapa yang mengambil barangnya (Gunawan, Junaidi, dst).
            $table->foreignId('petugas_id')->nullable()->constrained('petugas');

            // Diisi kalau tujuannya induksi/reweight. Cukup sampai shipment,
            // tidak sampai pen.
            $table->foreignId('shipment_id')->nullable()->constrained('shipments');

            $table->text('catatan')->nullable();
            $table->foreignId('dibuat_oleh')->constrained('users');
            $table->timestamps();

            $table->index(['tanggal', 'tujuan']);
        });

        Schema::create('pengeluaran_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengeluaran_id')->constrained('pengeluaran')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barang');
            $table->decimal('qty', 12, 3);

            // Dihitung sistem dari alokasi FIFO, bukan diketik operator.
            $table->decimal('nilai_hpp', 18, 2)->default(0);

            $table->timestamps();
        });

        // Rincian lot mana saja yang terpakai oleh satu baris pengeluaran.
        // Satu pengeluaran 12 botol bisa makan lot #1 (10 botol @85rb) dan
        // lot #2 (2 botol @92rb). Tanpa tabel ini, FIFO cuma kelihatan hasil
        // akhirnya dan tidak bisa ditelusuri.
        Schema::create('alokasi_lot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengeluaran_item_id')->constrained('pengeluaran_items')->cascadeOnDelete();
            $table->foreignId('stok_lot_id')->constrained('stok_lot');
            $table->decimal('qty', 12, 3);
            $table->decimal('harga_satuan', 15, 2);
            $table->decimal('subtotal', 18, 2);
            $table->timestamps();
        });

        Schema::create('opname', function (Blueprint $table) {
            $table->id();
            $table->string('nomor')->unique();
            $table->date('tanggal');
            $table->unsignedTinyInteger('periode_bulan');
            $table->unsignedSmallInteger('periode_tahun');
            $table->enum('status', ['draft', 'final'])->default('draft');
            $table->text('catatan')->nullable();
            $table->timestamp('difinalkan_pada')->nullable();
            $table->foreignId('dibuat_oleh')->constrained('users');
            $table->timestamps();

            // Opname sebulan sekali — satu periode tidak boleh dobel.
            $table->unique(['periode_tahun', 'periode_bulan']);
        });

        Schema::create('opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opname_id')->constrained('opname')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barang');

            // Dibekukan saat opname dibuat, bukan dihitung ulang saat difinalkan.
            // Kalau dihitung ulang, transaksi yang masuk di sela-sela penghitungan
            // fisik akan bikin selisihnya bohong.
            $table->decimal('stok_sistem', 12, 3);

            $table->decimal('stok_fisik', 12, 3)->nullable();
            $table->decimal('selisih', 12, 3)->default(0);
            $table->decimal('nilai_selisih', 18, 2)->default(0);
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['opname_id', 'barang_id']);
        });

        // ── Kartu stok ────────────────────────────────────────────────
        // APPEND-ONLY. Lihat PergerakanStok::booted() — update dan delete
        // diblokir di level model. Penegakan di database (trigger) sengaja
        // tidak dipakai karena hak TRIGGER sering tidak diberikan di shared
        // hosting cPanel.
        Schema::create('pergerakan_stok', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained('barang');
            $table->date('tanggal');
            $table->enum('tipe', ['masuk', 'keluar', 'opname', 'koreksi']);

            // Bertanda: positif untuk masuk, negatif untuk keluar.
            $table->decimal('qty', 12, 3);
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->decimal('nilai', 18, 2)->default(0);

            $table->foreignId('stok_lot_id')->nullable()->constrained('stok_lot');

            // Nunjuk balik ke nota asalnya (penerimaan, pengeluaran, opname).
            $table->nullableMorphs('sumber');

            $table->string('keterangan')->nullable();
            $table->foreignId('dibuat_oleh')->constrained('users');
            $table->timestamps();

            $table->index(['barang_id', 'tanggal']);
            $table->index(['tanggal', 'tipe']);
        });

        // Counter nomor dokumen. Format: SCK-OVK-{M|K|O|P}-{bulan romawi}-{YY}-{urut}
        // Urut jalan terus sepanjang tahun dan balik ke 1 tiap ganti tahun,
        // dihitung terpisah per jenis dokumen.
        Schema::create('nomor_dokumen', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 4);
            $table->unsignedSmallInteger('tahun');
            $table->unsignedInteger('urutan_terakhir')->default(0);
            $table->timestamps();

            $table->unique(['jenis', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nomor_dokumen');
        Schema::dropIfExists('pergerakan_stok');
        Schema::dropIfExists('opname_items');
        Schema::dropIfExists('opname');
        Schema::dropIfExists('alokasi_lot');
        Schema::dropIfExists('pengeluaran_items');
        Schema::dropIfExists('pengeluaran');
        Schema::dropIfExists('stok_lot');
    }
};
