<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourrierType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'requires_response', 'agent_delay_days',
        'director_delay_days', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_response' => 'boolean',
            'is_active' => 'boolean',
            'agent_delay_days' => 'integer',
            'director_delay_days' => 'integer',
        ];
    }

    public function courriers(): HasMany
    {
        return $this->hasMany(Courrier::class);
    }
}
