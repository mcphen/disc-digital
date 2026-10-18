<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourrierImport extends Model
{
    protected $fillable = [
        'original_name', 'file_hash', 'total_rows', 'imported_rows',
        'skipped_rows', 'invalid_rows', 'imported_by_id', 'imported_at',
    ];

    protected function casts(): array
    {
        return ['imported_at' => 'datetime'];
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by_id');
    }
}
