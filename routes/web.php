<?php

use App\Http\Controllers\BarangController;
use App\Http\Controllers\BiayaObatController;
use App\Http\Controllers\Cpl\ClaimController;
use App\Http\Controllers\Cpl\DashboardCplController;
use App\Http\Controllers\Cpl\LaporanCplController;
use App\Http\Controllers\Cpl\PenjualanController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\ImporController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\OpnameController;
use App\Http\Controllers\PenerimaanController;
use App\Http\Controllers\PengeluaranController;
use App\Http\Controllers\PetugasController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [LoginController::class, 'form'])->name('login');
    Route::post('/masuk', [LoginController::class, 'masuk'])->name('login.proses');
});

Route::middleware('auth')->group(function () {
    Route::post('/keluar', [LoginController::class, 'keluar'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // ── Master ────────────────────────────────────────────────────
    Route::resource('barang', BarangController::class)->except('show');
    Route::post('barang/{barang}/alias', [BarangController::class, 'tambahAlias'])->name('barang.alias.tambah');
    Route::delete('barang/alias/{alias}', [BarangController::class, 'hapusAlias'])->name('barang.alias.hapus');

    Route::resource('supplier', SupplierController::class)->except('show');

    // Tanpa ->parameters(), Laravel memakai aturan jamak bahasa Inggris dan
    // memotong "petugas" jadi "{petuga}", sehingga route model binding tidak
    // pernah cocok dengan argumen $petugas di controller.
    Route::resource('petugas', PetugasController::class)
        ->except('show')
        ->parameters(['petugas' => 'petugas']);

    Route::resource('shipment', ShipmentController::class)->except('show');

    // ── Transaksi ─────────────────────────────────────────────────
    Route::resource('penerimaan', PenerimaanController::class)->except('edit', 'update', 'destroy');
    Route::get('penerimaan/{penerimaan}/cetak', [PenerimaanController::class, 'cetak'])->name('penerimaan.cetak');

    Route::resource('pengeluaran', PengeluaranController::class)->except('edit', 'update', 'destroy');
    Route::get('pengeluaran/{pengeluaran}/cetak', [PengeluaranController::class, 'cetak'])->name('pengeluaran.cetak');

    Route::resource('purchase-order', PurchaseOrderController::class)->except('destroy');
    Route::post('purchase-order/{purchase_order}/tutup', [PurchaseOrderController::class, 'tutup'])->name('purchase-order.tutup');
    Route::post('purchase-order/{purchase_order}/batal', [PurchaseOrderController::class, 'batal'])->name('purchase-order.batal');

    Route::resource('opname', OpnameController::class)->except('edit', 'destroy');
    Route::post('opname/{opname}/finalkan', [OpnameController::class, 'finalkan'])->name('opname.finalkan');
    Route::get('opname/{opname}/cetak', [OpnameController::class, 'cetak'])->name('opname.cetak');

    // ── CPL ───────────────────────────────────────────────────────
    // Modul terpisah dari OVK: dashboard untuk pimpinan, laporan rinci,
    // dan data sapi. Semuanya membaca lewat KueriCpl yang sama.
    Route::prefix('cpl')->name('cpl.')->group(function () {
        Route::get('/', DashboardCplController::class)->name('dashboard');

        // Dua jalur laporan seperti di Streamlit: rinci per ekor, dan
        // closing yang membuang baris detailnya.
        Route::get('/laporan', [LaporanCplController::class, 'detail'])->name('laporan');
        Route::get('/laporan/unduh', [LaporanCplController::class, 'unduh'])->name('laporan.unduh');
        Route::get('/closing', [LaporanCplController::class, 'closing'])->name('closing');

        // Claim ke importir. Satu-satunya data CPL yang diketik, bukan
        // diunggah — kejadiannya satu-satu dan tidak pernah datang dalam
        // bentuk berkas.
        Route::get('/claim/cari', [ClaimController::class, 'cari'])->name('claim.cari');
        Route::get('/claim/unduh', [ClaimController::class, 'unduh'])->name('claim.unduh');
        Route::resource('claim', ClaimController::class)->except('show');

        // Penjualan. Jalur utamanya tetap impor sheet Transaksi dari Dropbox;
        // halaman ini untuk koreksi satu ekor dan nota yang belum masuk berkas.
        Route::get('/penjualan/cari', [PenjualanController::class, 'cari'])->name('penjualan.cari');
        Route::get('/penjualan/unduh', [PenjualanController::class, 'unduh'])->name('penjualan.unduh');
        Route::resource('penjualan', PenjualanController::class)->except('show');
    });

    // ── Impor berkas ──────────────────────────────────────────────
    // Alurnya dua tahap: unggah menghasilkan pratinjau, dan tidak ada data
    // yang masuk sampai /proses dipanggil.
    Route::get('/impor', [ImporController::class, 'index'])->name('impor.index');
    Route::post('/impor', [ImporController::class, 'store'])->name('impor.store');
    Route::get('/impor/templat/{jenis}', [ImporController::class, 'templat'])->name('impor.templat');
    Route::get('/impor/{impor}', [ImporController::class, 'show'])->name('impor.show');
    Route::post('/impor/{impor}/proses', [ImporController::class, 'proses'])->name('impor.proses');
    Route::post('/impor/{impor}/batal', [ImporController::class, 'batal'])->name('impor.batal');

    // ── Laporan ───────────────────────────────────────────────────
    // Setiap laporan punya pasangan unduhan yang memakai query yang sama
    // persis, supaya angka di layar dan di berkas tidak mungkin berbeda.
    Route::get('/laporan/stok', [LaporanController::class, 'stok'])->name('laporan.stok');
    Route::get('/laporan/stok/unduh', [LaporanController::class, 'stokUnduh'])->name('laporan.stok.unduh');

    Route::get('/laporan/mutasi', [LaporanController::class, 'mutasi'])->name('laporan.mutasi');
    Route::get('/laporan/mutasi/unduh', [LaporanController::class, 'mutasiUnduh'])->name('laporan.mutasi.unduh');

    Route::get('/laporan/kartu', [LaporanController::class, 'kartu'])->name('laporan.kartu');
    Route::get('/laporan/kartu/unduh', [LaporanController::class, 'kartuUnduh'])->name('laporan.kartu.unduh');

    // Jembatan OVK ke CPL: dosis dokter dinilai dengan harga FIFO barang
    // yang benar-benar keluar dari gudang.
    Route::get('/laporan/biaya-obat', [BiayaObatController::class, 'index'])->name('laporan.biaya-obat');
    Route::get('/laporan/biaya-obat/unduh', [BiayaObatController::class, 'unduh'])->name('laporan.biaya-obat.unduh');
});

/*
 * Rilis tanpa SSH.
 *
 * Hosting tujuan cuma punya File Manager dan cPanel, tidak ada terminal, jadi
 * `php artisan migrate` dijalankan lewat sini oleh GitHub Actions setelah file
 * selesai diunggah. Route ini mati (404) selama DEPLOY_TOKEN kosong.
 */
Route::prefix('__deploy')->group(function () {
    Route::post('/migrate', [DeployController::class, 'migrate']);
    Route::post('/optimize', [DeployController::class, 'optimize']);
    Route::get('/status', [DeployController::class, 'status']);
});
