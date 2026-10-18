<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Courrier extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_REGISTERED = 'registered';

    public const STATUS_ASSIGNED_DIVISION = 'assigned_division';

    public const STATUS_ASSIGNED_AGENT = 'assigned_agent';

    public const STATUS_PENDING_DIVISION_REVIEW = 'pending_division_review';

    public const STATUS_CORRECTION_REQUESTED = 'correction_requested';

    public const STATUS_DIVISION_VALIDATED = 'division_validated';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_SIGNED = 'signed';

    public const STATUS_CLOSED_NO_RESPONSE = 'closed_no_response';

    protected $fillable = [
        'sequence_number', 'registration_year', 'arrival_date', 'reference',
        'origin_service', 'courrier_type_id', 'subject', 'arrival_number',
        'division_id', 'division_assigned_at', 'agent_assigned_at',
        'instructions', 'assigned_agent_id', 'submitted_at', 'division_validated_at',
        'division_validated_by_id', 'signed_at',
        'status', 'agent_delay_days', 'director_delay_days', 'notes',
        'legacy_agent_name', 'source_sheet', 'source_row', 'source_hash',
        'created_by_id', 'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer',
            'registration_year' => 'integer',
            'arrival_date' => 'date:Y-m-d',
            'division_assigned_at' => 'date:Y-m-d',
            'agent_assigned_at' => 'date:Y-m-d',
            'submitted_at' => 'date:Y-m-d',
            'division_validated_at' => 'date:Y-m-d',
            'signed_at' => 'date:Y-m-d',
            'agent_delay_days' => 'integer',
            'director_delay_days' => 'integer',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(CourrierType::class, 'courrier_type_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    /** Divisions imputées en contribution, en plus de la division pilote (division_id). */
    public function contributingDivisions(): BelongsToMany
    {
        return $this->belongsToMany(Division::class, 'courrier_contributing_divisions')->withTimestamps()->orderBy('code');
    }

    /** Agents associés au dossier, en plus de l'agent responsable (assigned_agent_id). */
    public function coAgents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'courrier_co_agents')->withTimestamps()->orderBy('name');
    }

    public function divisionValidatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'division_validated_by_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CourrierEvent::class)->latest('created_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CourrierAttachment::class)->latest();
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(CourrierNotification::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['admin', 'director', 'secretariat'])) {
            return $query;
        }

        if ($user->role === 'auditor') {
            return $query->whereHas('type', fn (Builder $query) => $query->where('code', '!=', 'confidential'));
        }

        if ($user->role === 'division_head') {
            return $query
                ->involvingDivision($user->division_id)
                ->where(function (Builder $query) use ($user) {
                    $query->whereHas('type', fn (Builder $query) => $query->where('code', '!=', 'confidential'))
                        ->orWhere(fn (Builder $query) => $query->involvingAgent($user->id));
                });
        }

        return $query->involvingAgent($user->id);
    }

    /** Courriers dont la division est pilote ou contributrice. */
    public function scopeInvolvingDivision(Builder $query, ?int $divisionId): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->where('division_id', $divisionId)
            ->orWhereHas('contributingDivisions', fn (Builder $query) => $query->whereKey($divisionId)));
    }

    /** Courriers dont l'agent est responsable ou co-agent. */
    public function scopeInvolvingAgent(Builder $query, int $userId): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->where('assigned_agent_id', $userId)
            ->orWhereHas('coAgents', fn (Builder $query) => $query->whereKey($userId)));
    }

    /**
     * Enregistre les divisions contributrices et les co-agents. La division pilote et l'agent
     * responsable en sont retirés : ils ont déjà leur place sur le courrier.
     *
     * @param  array<int, int|string>  $divisionIds
     * @param  array<int, int|string>  $agentIds
     */
    public function syncContributors(array $divisionIds, array $agentIds): void
    {
        $this->contributingDivisions()->sync(collect($divisionIds)->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === $this->division_id)->unique()->values()->all());
        $this->coAgents()->sync(collect($agentIds)->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === $this->assigned_agent_id)->unique()->values()->all());
        $this->unsetRelation('contributingDivisions')->unsetRelation('coAgents');
    }

    public function involvesDivision(?int $divisionId): bool
    {
        return $divisionId !== null
            && ($this->division_id === $divisionId || $this->contributingDivisions->contains('id', $divisionId));
    }

    public function involvesAgent(int $userId): bool
    {
        return $this->assigned_agent_id === $userId || $this->coAgents->contains('id', $userId);
    }

    public function getRegistrationNumberAttribute(): string
    {
        return sprintf('%04d/%d', $this->sequence_number, $this->registration_year);
    }
}
