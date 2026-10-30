<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignCourrierRequest;
use App\Http\Requests\ReviewCourrierRequest;
use App\Http\Requests\SignCourrierRequest;
use App\Http\Requests\SubmitCourrierRequest;
use App\Models\Courrier;
use App\Models\User;
use App\Services\Disc\WorkflowNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourrierWorkflowController extends Controller
{
    public function assign(AssignCourrierRequest $request, Courrier $courrier): RedirectResponse
    {
        $data = $request->validated();

        if (! in_array($courrier->status, [
            Courrier::STATUS_REGISTERED,
            Courrier::STATUS_ASSIGNED_DIVISION,
            Courrier::STATUS_ASSIGNED_AGENT,
            Courrier::STATUS_CORRECTION_REQUESTED,
        ], true)) {
            throw ValidationException::withMessages([
                'division_id' => "L'imputation ne peut plus être modifiée à cette étape.",
            ]);
        }

        if (! empty($data['assigned_agent_id'])) {
            $agent = User::findOrFail($data['assigned_agent_id']);
            if ($agent->division_id && $agent->division_id !== (int) $data['division_id']) {
                throw ValidationException::withMessages([
                    'assigned_agent_id' => "L'agent sélectionné n'appartient pas à cette division.",
                ]);
            }
        }

        // Un co-agent travaille pour la division pilote ou pour l'une des divisions contributrices.
        $involvedDivisions = collect($data['contributing_division_ids'] ?? [])->map(fn ($id) => (int) $id)->push((int) $data['division_id']);
        $outsiders = User::whereIn('id', $data['co_agent_ids'] ?? [])
            ->whereNotNull('division_id')
            ->whereNotIn('division_id', $involvedDivisions)
            ->pluck('name');
        if ($outsiders->isNotEmpty()) {
            throw ValidationException::withMessages([
                'co_agent_ids' => 'Ces agents ne relèvent d’aucune division imputée : '.$outsiders->join(', ').'.',
            ]);
        }

        DB::transaction(function () use ($request, $courrier, $data) {
            $from = $courrier->status;
            $to = ! empty($data['assigned_agent_id'])
                ? Courrier::STATUS_ASSIGNED_AGENT
                : Courrier::STATUS_ASSIGNED_DIVISION;

            $courrier->update([
                'division_id' => $data['division_id'],
                'division_assigned_at' => $data['division_assigned_at'],
                'assigned_agent_id' => $data['assigned_agent_id'] ?? null,
                'agent_assigned_at' => $data['agent_assigned_at'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'status' => $to,
                'updated_by_id' => $request->user()->id,
            ]);
            $courrier->syncContributors($data['contributing_division_ids'] ?? [], $data['co_agent_ids'] ?? []);

            $courrier->events()->create([
                'user_id' => $request->user()->id,
                'event' => 'assigned',
                'from_status' => $from,
                'to_status' => $to,
                'comment' => $data['comment'] ?? null,
                'metadata' => [
                    'division_id' => $data['division_id'],
                    'assigned_agent_id' => $data['assigned_agent_id'] ?? null,
                    'contributing_division_ids' => $courrier->contributingDivisions()->pluck('divisions.id')->all(),
                    'co_agent_ids' => $courrier->coAgents()->pluck('users.id')->all(),
                ],
            ]);
        });

        return back()->with('success', "L'imputation a été enregistrée.");
    }

    public function submit(
        SubmitCourrierRequest $request,
        Courrier $courrier,
        WorkflowNotificationService $notifications,
    ): RedirectResponse {
        if (! $courrier->type->requires_response || ! $courrier->assigned_agent_id) {
            throw ValidationException::withMessages([
                'submitted_at' => 'Ce courrier ne peut pas être soumis dans son état actuel.',
            ]);
        }

        if (! in_array($courrier->status, [
            Courrier::STATUS_ASSIGNED_AGENT,
            Courrier::STATUS_CORRECTION_REQUESTED,
        ], true)) {
            throw ValidationException::withMessages([
                'submitted_at' => "Le projet n'est pas dans un état permettant sa soumission.",
            ]);
        }

        $this->transition($request, $courrier, Courrier::STATUS_PENDING_DIVISION_REVIEW, 'submitted_for_review', [
            'submitted_at' => $courrier->submitted_at ?? $request->validated('submitted_at'),
            'division_validated_at' => null,
            'division_validated_by_id' => null,
        ]);
        $notifications->reviewRequested($courrier->fresh(['assignedAgent']));

        return back()->with('success', 'Le projet de réponse a été soumis au Chef de Division.');
    }

    public function review(
        ReviewCourrierRequest $request,
        Courrier $courrier,
        WorkflowNotificationService $notifications,
    ): RedirectResponse {
        if ($courrier->status !== Courrier::STATUS_PENDING_DIVISION_REVIEW) {
            throw ValidationException::withMessages([
                'decision' => "Ce dossier n'attend pas de validation de division.",
            ]);
        }

        $decision = $request->validated('decision');

        if ($decision === 'reject') {
            $this->transition($request, $courrier, Courrier::STATUS_CORRECTION_REQUESTED, 'division_rejected', [
                'division_validated_at' => null,
                'division_validated_by_id' => null,
            ]);
            $notifications->correctionRequested($courrier->fresh(['assignedAgent']));

            return back()->with('success', "La demande de correction a été transmise à l'agent.");
        }

        $this->transition($request, $courrier, Courrier::STATUS_DIVISION_VALIDATED, 'division_approved', [
            'division_validated_at' => $request->validated('reviewed_at'),
            'division_validated_by_id' => $request->user()->id,
        ]);
        $notifications->approved($courrier->fresh(['assignedAgent']));

        return back()->with('success', 'Le projet a été validé et transmis au Directeur.');
    }

    public function sign(SignCourrierRequest $request, Courrier $courrier): RedirectResponse
    {
        if (! in_array($courrier->status, [Courrier::STATUS_DIVISION_VALIDATED, Courrier::STATUS_SUBMITTED], true)) {
            throw ValidationException::withMessages([
                'signed_at' => "Le dossier doit d'abord être validé par le Chef de Division.",
            ]);
        }

        $this->transition($request, $courrier, Courrier::STATUS_SIGNED, 'signed', [
            'signed_at' => $request->validated('signed_at'),
        ]);

        return back()->with('success', 'Le courrier a été signé et clôturé.');
    }

    /** @param array<string, mixed> $attributes */
    private function transition($request, Courrier $courrier, string $to, string $event, array $attributes): void
    {
        DB::transaction(function () use ($request, $courrier, $to, $event, $attributes) {
            $from = $courrier->status;
            $courrier->update($attributes + [
                'status' => $to,
                'updated_by_id' => $request->user()->id,
            ]);

            $courrier->events()->create([
                'user_id' => $request->user()->id,
                'event' => $event,
                'from_status' => $from,
                'to_status' => $to,
                'comment' => $request->validated('comment'),
            ]);
        });
    }
}
