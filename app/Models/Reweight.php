<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reweight extends Model
{
    protected $table = 'reweight';

    protected $fillable = [
        'induksi_id', 'tanggal_reweight', 'berat_reweight',
        'pen_awal', 'pen_akhir', 'import_batch_id',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_reweight' => 'date',
            'berat_reweight' => 'decimal:2',
        ];
    }

    public function induksi(): BelongsTo
    {
        return $this->belongsTo(Induksi::class);
    }
}
