<?php

use App\Http\Controllers\BarangController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeployController;
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
    Route::resource('pengeluaran', PengeluaranController::class)->except('edit', 'update', 'destroy');

    Route::resource('purchase-order', PurchaseOrderController::class)->except('destroy');
    Route::post('purchase-order/{purchase_order}/tutup', [PurchaseOrderController::class, 'tutup'])->name('purchase-order.tutup');
    Route::post('purchase-order/{purchase_order}/batal', [PurchaseOrderController::class, 'batal'])->name('purchase-order.batal');

    Route::resource('opname', OpnameController::class)->except('edit', 'destroy');
    Route::post('opname/{opname}/finalkan', [OpnameController::class, 'finalkan'])->name('opname.finalkan');

    // ── Laporan ───────────────────────────────────────────────────
    Route::get('/laporan/stok', [LaporanController::class, 'stok'])->name('laporan.stok');
    Route::get('/laporan/mutasi', [LaporanController::class, 'mutasi'])->name('laporan.mutasi');
    Route::get('/laporan/kartu', [LaporanController::class, 'kartu'])->name('laporan.kartu');
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
