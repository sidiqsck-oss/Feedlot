<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NomorDokumen extends Model
{
    protected $table = 'nomor_dokumen';

    protected $fillable = ['jenis', 'tahun', 'urutan_terakhir'];
}
