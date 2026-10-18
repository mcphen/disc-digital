<?php

namespace Tests\Feature;

use App\Models\Courrier;
use App\Models\CourrierNotification;
use App\Models\CourrierType;
use App\Models\Division;
use App\Models\User;
use App\Services\Disc\SharedAssignment;
use Database\Seeders\DiscReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MultipleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $director;

    private Division $dri;

    private Division $dp;

    private Division $dsse;

    private User $lead;

    private User $coAgent;

    private User $driHead;

    private User $dpHead;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DiscReferenceSeeder::class);
        Carbon::setTestNow('2026-10-16 10:00:00');

        $this->director = User::factory()->create(['role' => 'director']);
        $this->dri = Division::where('code', 'DRI')->firstOrFail();
        $this->dp = Division::where('code', 'DP')->firstOrFail();
        $this->dsse = Division::where('code', 'DSSE')->firstOrFail();
        $this->lead = User::factory()->create(['role' => 'agent', 'division_id' => $this->dri->id, 'name' => 'Responsable DRI']);
        $this->coAgent = User::factory()->create(['role' => 'agent', 'division_id' => $this->dp->id, 'name' => 'Co-agent DP']);
        $this->driHead = User::factory()->create(['role' => 'division_head', 'division_id' => $this->dri->id]);
        $this->dpHead = User::factory()->create(['role' => 'division_head', 'division_id' => $this->dp->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_courrier_is_assigned_to_a_lead_and_contributors(): void
    {
        $courrier = $this->courrier(Courrier::STATUS_REGISTERED);

        $this->actingAs($this->director)
            ->put("/courriers/{$courrier->id}/assignment", $this->assignment([
                // La division pilote cochée par erreur en contribution est ignorée.
                'contributing_division_ids' => [$this->dp->id, $this->dri->id],
                'co_agent_ids' => [$this->coAgent->id, $this->lead->id],
            ]))
            ->assertSessionHasNoErrors();

        $courrier->refresh();
        $this->assertSame($this->dri->id, $courrier->division_id);
        $this->assertSame($this->lead->id, $courrier->assigned_agent_id);
        $this->assertSame([$this->dp->id], $courrier->contributingDivisions->pluck('id')->all());
        $this->assertSame([$this->coAgent->id], $courrier->coAgents->pluck('id')->all());
        $this->assertSame([$this->dp->id], $courrier->events()->first()->metadata['contributing_division_ids']);
    }

    public function test_co_agents_must_belong_to_an_imputed_division_and_need_a_lead(): void
    {
        $courrier = $this->courrier(Courrier::STATUS_REGISTERED);

        // Le co-agent est à la DP, qui n'est pas imputée.
        $this->actingAs($this->director)
            ->put("/courriers/{$courrier->id}/assignment", $this->assignment(['co_agent_ids' => [$this->coAgent->id]]))
            ->assertSessionHasErrors('co_agent_ids');

        $this->actingAs($this->director)
            ->put("/courriers/{$courrier->id}/assignment", $this->assignment([
                'assigned_agent_id' => null,
                'agent_assigned_at' => null,
                'contributing_division_ids' => [$this->dp->id],
                'co_agent_ids' => [$this->coAgent->id],
            ]))
            ->assertSessionHasErrors('co_agent_ids');

        $this->assertSame(0, $courrier->coAgents()->count());
    }

    public function test_contributors_see_the_courrier_but_only_the_lead_acts_on_it(): void
    {
        $courrier = $this->shared(Courrier::STATUS_PENDING_DIVISION_REVIEW, ['submitted_at' => '2026-10-12']);

        // Le co-agent voit le dossier, le retrouve dans « Mes courriers » et peut joindre des pièces…
        $this->actingAs($this->coAgent)->get("/courriers/{$courrier->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('courrier.co_agents.0.name', 'Co-agent DP')
                ->where('courrier.contributing_divisions.0.code', 'DP')
                ->where('permissions.attach', true)
                ->where('permissions.submit', false));
        $this->actingAs($this->coAgent)->get('/courriers?queue=mine')
            ->assertInertia(fn (Assert $page) => $page->where('courriers.total', 1));

        // … le Chef de la division contributrice le consulte sans pouvoir le répartir ni le valider.
        $this->actingAs($this->dpHead)->get("/courriers/{$courrier->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('permissions.assign', false)
                ->where('permissions.review', false));
        $this->actingAs($this->dpHead)->get('/courriers?queue=review')
            ->assertInertia(fn (Assert $page) => $page->where('courriers.total', 0));
        $this->actingAs($this->dpHead)
            ->put("/courriers/{$courrier->id}/review", ['decision' => 'approve', 'reviewed_at' => '2026-10-14'])
            ->assertForbidden();

        // Le Chef de la division pilote valide comme avant ; une autre division ne voit rien.
        $this->actingAs($this->driHead)->get('/courriers?queue=review')
            ->assertInertia(fn (Assert $page) => $page->where('courriers.total', 1));
        $this->actingAs(User::factory()->create(['role' => 'division_head', 'division_id' => $this->dsse->id]))
            ->get("/courriers/{$courrier->id}")
            ->assertForbidden();
    }

    public function test_co_agents_are_told_when_a_correction_is_requested(): void
    {
        $courrier = $this->shared(Courrier::STATUS_PENDING_DIVISION_REVIEW, ['submitted_at' => '2026-10-12']);

        $this->actingAs($this->driHead)
            ->put("/courriers/{$courrier->id}/review", ['decision' => 'reject', 'reviewed_at' => '2026-10-14', 'comment' => 'Compléter les chiffres.'])
            ->assertSessionHasNoErrors();

        foreach ([$this->lead, $this->coAgent] as $agent) {
            $this->assertTrue(CourrierNotification::where(['courrier_id' => $courrier->id, 'user_id' => $agent->id, 'level' => 'CORRECT'])->exists());
        }

        // Seul l'agent responsable a le dossier dans sa file « À corriger ».
        $this->actingAs($this->coAgent)->get('/courriers?queue=corrections')
            ->assertInertia(fn (Assert $page) => $page->where('courriers.total', 0));
        $this->actingAs($this->lead)->get('/courriers?queue=corrections')
            ->assertInertia(fn (Assert $page) => $page->where('courriers.total', 1));
    }

    public function test_quarterly_reviews_count_every_assignee_and_the_direction_once(): void
    {
        // Échéance agent au 6 août : jamais rendu.
        $this->shared(Courrier::STATUS_ASSIGNED_AGENT, ['arrival_date' => '2026-08-01', 'division_assigned_at' => '2026-08-01', 'agent_assigned_at' => '2026-08-01']);

        $this->actingAs($this->coAgent)->get("/pilotage/agents/{$this->coAgent->id}?periode=2026-T3")
            ->assertInertia(fn (Assert $page) => $page->where('scorecard.scores.0.overdue', 1)
                ->where('scorecard.late.items.0.agent', 'Responsable DRI + Co-agent DP'));
        $this->actingAs($this->dpHead)->get("/pilotage/divisions/{$this->dp->id}?periode=2026-T3")
            ->assertInertia(fn (Assert $page) => $page->where('scorecard.scores.0.overdue', 1)
                ->where('scorecard.agents', fn ($agents) => collect($agents)->firstWhere('name', 'Co-agent DP')['drafting']['overdue'] === 1));
        $this->actingAs($this->director)->get('/pilotage?periode=2026-T3')
            ->assertInertia(fn (Assert $page) => $page
                ->where('scorecard.scores.1.overdue', 1)
                ->where('scorecard.late.items.0.division', 'DRI + DP')
                ->where('scorecard.divisions', fn ($divisions) => collect($divisions)->whereIn('code', ['DRI', 'DP'])->every(fn ($row) => $row['drafting']['overdue'] === 1)));
    }

    public function test_excel_cells_listing_several_names_are_split(): void
    {
        $this->assertSame(['DP', 'DRI'], SharedAssignment::split('DP/DRI'));
        $this->assertSame(['Mme DIENG', 'Mme NGOM'], SharedAssignment::split('Mme DIENG - Mme NGOM'));
        $this->assertSame(['Awa', 'Fatou', 'Moussa'], SharedAssignment::split('Awa et Fatou ; Moussa'));
        $this->assertSame(['Jean-Pierre Diop'], SharedAssignment::split('Jean-Pierre Diop'));
        $this->assertSame([], SharedAssignment::split('  '));
    }

    public function test_combined_accounts_are_split_without_losing_courriers(): void
    {
        $combined = User::factory()->create(['role' => 'agent', 'division_id' => $this->dri->id, 'name' => 'Mme DIENG - Mme NGOM']);
        $ngom = User::factory()->create(['role' => 'agent', 'division_id' => $this->dri->id, 'name' => 'Mme NGOM']);
        $courrier = $this->courrier(Courrier::STATUS_ASSIGNED_AGENT, ['assigned_agent_id' => $combined->id]);

        $this->artisan('disc:split-shared-agents')->assertSuccessful();
        $this->assertSame($combined->id, $courrier->fresh()->assigned_agent_id, 'La simulation ne modifie rien.');

        $this->artisan('disc:split-shared-agents --apply')->assertSuccessful();

        $dieng = User::where('name', 'Mme DIENG')->firstOrFail();
        $courrier->refresh();
        $this->assertSame($dieng->id, $courrier->assigned_agent_id);
        $this->assertSame($this->dri->id, $dieng->division_id);
        $this->assertSame([$ngom->id], $courrier->coAgents->pluck('id')->all());
        $this->assertFalse($combined->fresh()->is_active);
    }

    /** @param array<string, mixed> $overrides */
    private function assignment(array $overrides = []): array
    {
        return $overrides + [
            'division_id' => $this->dri->id,
            'division_assigned_at' => '2026-10-10',
            'assigned_agent_id' => $this->lead->id,
            'agent_assigned_at' => '2026-10-10',
        ];
    }

    /** Courrier DRI (pilote) avec la DP en contribution, responsable DRI et co-agent DP. */
    private function shared(string $status, array $overrides = []): Courrier
    {
        $courrier = $this->courrier($status, $overrides + [
            'division_id' => $this->dri->id,
            'division_assigned_at' => '2026-10-10',
            'assigned_agent_id' => $this->lead->id,
            'agent_assigned_at' => '2026-10-10',
        ]);
        $courrier->syncContributors([$this->dp->id], [$this->coAgent->id]);

        return $courrier;
    }

    /** @param array<string, mixed> $overrides */
    private function courrier(string $status, array $overrides = []): Courrier
    {
        static $sequence = 0;
        $sequence++;

        return Courrier::create($overrides + [
            'sequence_number' => $sequence,
            'registration_year' => 2026,
            'arrival_date' => '2026-10-10',
            'origin_service' => 'Service partagé',
            'courrier_type_id' => CourrierType::where('code', 'ordinary')->value('id'),
            'subject' => "Courrier partagé {$sequence}",
            'status' => $status,
            'agent_delay_days' => 5,
            'director_delay_days' => 2,
            'created_by_id' => $this->director->id,
            'updated_by_id' => $this->director->id,
        ]);
    }
}
