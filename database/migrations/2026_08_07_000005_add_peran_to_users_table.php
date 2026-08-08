<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mengganti satu password global di sistem lama dengan user beneran.
 *
 * Di streamlit_app.py, check_password() membandingkan satu password yang sama
 * untuk semua orang, disimpan di st.secrets. Akibatnya tidak ada cara tahu
 * siapa yang menginput apa. Semua tabel transaksi di sini punya kolom
 * dibuat_oleh yang menunjuk ke sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('peran', ['admin', 'gudang', 'viewer'])->default('viewer')->after('email');
            $table->boolean('aktif')->default(true)->after('peran');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['peran', 'aktif']);
        });
    }
};
