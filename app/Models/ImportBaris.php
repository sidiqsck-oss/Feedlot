<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBaris extends Model
{
    protected $table = 'import_baris';

    protected $fillable = [
        'import_batch_id', 'nomor_baris', 'data_mentah',
        'status', 'masalah', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'data_mentah' => 'array',
            'masalah' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function scopeValid($query)
    {
        return $query->where('status', 'valid');
    }

    public function scopeBermasalah($query)
    {
        return $query->where('status', 'bermasalah');
    }
}
