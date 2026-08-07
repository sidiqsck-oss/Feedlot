<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportLog extends Model
{
    protected $table = 'import_logs';

    protected $fillable = [
        'sumber', 'mulai', 'selesai', 'jumlah_baris', 'jumlah_baru',
        'jumlah_dilewati', 'status', 'pesan', 'detail',
    ];

    protected function casts(): array
    {
        return [
            'mulai' => 'datetime',
            'selesai' => 'datetime',
            'detail' => 'array',
        ];
    }
}
