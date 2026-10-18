<?php

namespace Tests\Feature;

use App\Models\Courrier;
use App\Models\CourrierType;
use App\Models\Division;
use App\Models\User;
use Database\Seeders\DiscReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QuarterlyDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Division $dri;

    private Division $dp;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DiscReferenceSeeder::class);
        Carbon::setTestNow('2026-10-16 10:00:00');

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->dri = Division::where('code', 'DRI')->firstOrFail();
        $this->dp = Division::where('code', 'DP')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_courrier_counts_in_the_quarter_of_its_deadline(): void
    {
        $agent = User::factory()->create(['role' => 'agent', 'division_id' => $this->dri->id]);

        // Échéance agent (attribution + 5 j) au T3 : rendu à temps, rendu en retard, jamais rendu.
        $this->assigned($agent, '2026-08-01', ['submitted_at' => '2026-08-05']);
        $this->assigned($agent, '2026-08-01', ['submitted_at' => '2026-08-10', 'status' => Courrier::STATUS_PENDING_DIVISION_REVIEW]);
        $this->assigned($agent, '2026-09-10');
        // Attribué au T3 mais échu au T4 : compte au T4, pas au T3.
        $this->assigned($agent, '2026-09-28', ['submitted_at' => '2026-10-01', 'status' => Courrier::STATUS_PENDING_DIVISION_REVIEW]);
        // T4, pas encore échu (15 + 5 = 20 octobre).
        $this->assigned($agent, '2026-10-15');
        // T2 : rendu à temps.
        $this->assigned($agent, '2026-05-01', ['submitted_at' => '2026-05-03']);

        $this->actingAs($agent)
            ->get('/pilotage/agents/'.$agent->id.'?periode=2026-T3')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pilotage/Agent')
                ->where('period.key', '2026-T3')
                ->where('period.is_current', false)
                ->where('isSelf', true)
                ->where('scorecard.scores.0.measured', 3)
                ->where('scorecard.scores.0.on_time', 1)
                ->where('scorecard.scores.0.late', 1)
                ->where('scorecard.scores.0.overdue', 1)
                ->where('scorecard.scores.0.rate', 33)
                ->where('scorecard.scores.0.previous_rate', 100)
                ->where('scorecard.scores.0.delta', -67)
                // Moins de 5 courriers échus : taux indicatif, pas de conclusion.
                ->where('scorecard.scores.0.standing', 'limited')
                ->where('scorecard.scores.0.significant_change', false)
                ->where('scorecard.quarters.1.rate', 100)
                ->where('scorecard.quarters.3.is_current', true)
                ->where('scorecard.late.total', 2)
                ->where('scorecard.late.items.0.outcome', 'overdue')
                ->where('scorecard.late.items.1.days_late', 4));

        $this->actingAs($agent)
            ->get('/pilotage/agents/'.$agent->id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('period.key', '2026-T4')
                ->where('scorecard.scores.0.measured', 1)
                ->where('scorecard.scores.0.not_due', 1)
                ->where('scorecard.scores.0.rate', 100));
    }

    public function test_the_director_sees_divisions_agents_and_significant_findings(): void
    {
        $director = User::factory()->create(['role' => 'director']);
        $good = User::factory()->create(['role' => 'agent', 'division_id' => $this->dri->id, 'name' => 'Agent Rapide']);
        $slow = User::factory()->create(['role' => 'agent', 'division_id' => $this->dp->id, 'name' => 'Agent Lent']);

        foreach (range(1, 5) as $i) {
            $this->assigned($good, '2026-07-0'.$i, ['submitted_at' => '2026-07-0'.($i + 1)]);
            $this->assigned($slow, '2026-07-0'.$i);
        }

        $this->actingAs($director)
            ->get('/pilotage?periode=2026-T3')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pilotage/Direction')
                ->where('scorecard.scores.0.key', 'global')
                ->where('scorecard.scores.1.key', 'drafting')
                ->where('scorecard.scores.1.rate', 50)
                ->where('scorecard.divisions.0.code', 'DP')
                ->where('scorecard.divisions.0.drafting.rate', 0)
                ->where('scorecard.divisions.0.drafting.standing', 'critical')
                ->where('scorecard.agents.0.name', 'Agent Lent')
                ->where('scorecard.agents.0.drafting.overdue', 5)
                ->where('scorecard.agents.1.drafting.standing', 'good')
                ->where('scorecard.highlights', fn ($notes) => collect($notes)->contains(
                    fn ($note) => str_contains($note['text'], "La DP est sous le seuil d'alerte")
                ) && collect($notes)->contains(fn ($note) => str_contains($note['text'], 'chez Agent Lent (5)'))));
    }

    public function test_the_director_opens_every_agent_and_division_heads_only_their_own(): void
    {
        $director = User::factory()->create(['role' => 'director']);
        $head = User::factory()->create(['role' => 'division_head', 'division_id' => $this->dri->id]);
        $own = User::factory()->create(['role' => 'agent', 'division_id' => $this->dri->id, 'name' => 'Agent DRI']);
        $outsider = User::factory()->create(['role' => 'agent', 'division_id' => $this->dp->id, 'name' => 'Agent DP']);
        // Courrier de la DRI confié à un agent de la DP (réaffectation).
        $this->assigned($outsider, '2026-08-01', ['division_id' => $this->dri->id]);

        foreach ([$own, $outsider] as $agent) {
            $this->actingAs($director)->get('/pilotage/agents/'.$agent->id)->assertOk();
        }

        $this->actingAs($director)->get('/pilotage')
            ->assertInertia(fn (Assert $page) => $page
                ->where('scorecard.agents', fn ($agents) => collect($agents)->every(fn ($row) => $row['can_open'])));

        $this->actingAs($head)->get('/pilotage/divisions/'.$this->dri->id.'?periode=2026-T3')
            ->assertInertia(fn (Assert $page) => $page
                ->where('scorecard.agents', fn ($agents) => collect($agents)->pluck('can_open', 'name')->all() === [
                    'Agent DP' => false,
                    'Agent DRI' => true,
                ]));
    }

    public function test_each_role_lands_on_its_own_level(): void
    {
        $head = User::factory()->create(['role' => 'division_head', 'division_id' => $this->dri->id]);
        $agent = User::factory()->create(['role' => 'agent', 'division_id' => $this->dri->id]);
        $other = User::factory()->create(['role' => 'agent', 'division_id' => $this->dp->id]);
        $auditor = User::factory()->create(['role' => 'auditor']);

        $this->actingAs($agent)->get('/pilotage?periode=2026-T3')
            ->assertRedirect('/pilotage/agents/'.$agent->id.'?periode=2026-T3');
        $this->actingAs($agent)->get('/pilotage/agents/'.$other->id)->assertForbidden();
        $this->actingAs($agent)->get('/pilotage/divisions/'.$this->dri->id)->assertForbidden();

        $this->actingAs($head)->get('/pilotage')->assertRedirect('/pilotage/divisions/'.$this->dri->id);
        $this->actingAs($head)->get('/pilotage/divisions/'.$this->dri->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pilotage/Division')
                ->where('canViewDirection', false)
                ->has('scorecard.agents', 1));
        $this->actingAs($head)->get('/pilotage/divisions/'.$this->dp->id)->assertForbidden();
        $this->actingAs($head)->get('/pilotage/agents/'.$agent->id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('canViewDivision', true));
        $this->actingAs($head)->get('/pilotage/agents/'.$other->id)->assertForbidden();

        // L'auditeur voit les situations globales et par division, jamais les taux individuels.
        $this->actingAs($auditor)->get('/pilotage')->assertOk()->assertInertia(fn (Assert $page) => $page->where('scorecard.agents', null));
        $this->actingAs($auditor)->get('/pilotage/divisions/'.$this->dp->id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('scorecard.agents', null));
        $this->actingAs($auditor)->get('/pilotage/agents/'.$agent->id)->assertForbidden();
    }

    public function test_confidential_subjects_stay_hidden_in_late_lists(): void
    {
        $head = User::factory()->create(['role' => 'division_head', 'division_id' => $this->dri->id]);
        $agent = User::factory()->create(['role' => 'agent', 'division_id' => $this->dri->id]);
        $this->assigned($agent, '2026-08-01', [
            'courrier_type_id' => CourrierType::where('code', 'confidential')->value('id'),
            'subject' => 'Dossier sensible',
        ]);

        $this->actingAs($head)->get('/pilotage/divisions/'.$this->dri->id.'?periode=2026-T3')
            ->assertInertia(fn (Assert $page) => $page
                ->where('scorecard.scores.0.overdue', 1)
                ->where('scorecard.late.items.0.subject', 'Courrier confidentiel')
                ->where('scorecard.late.items.0.can_open', false));

        $this->actingAs($agent)->get('/pilotage/agents/'.$agent->id.'?periode=2026-T3')
            ->assertInertia(fn (Assert $page) => $page
                ->where('scorecard.late.items.0.subject', 'Dossier sensible')
                ->where('scorecard.late.items.0.can_open', true));
    }

    public function test_future_or_malformed_periods_fall_back_to_the_current_quarter(): void
    {
        foreach (['2027-T1', '2026-T9', 'n-importe'] as $period) {
            $this->actingAs($this->admin)->get('/pilotage?periode='.$period)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('period.key', '2026-T4'));
        }
    }

    public function test_the_home_dashboard_shows_the_quarter_summary(): void
    {
        $agent = User::factory()->create(['role' => 'agent', 'division_id' => $this->dri->id]);
        $this->assigned($agent, '2026-08-01', ['submitted_at' => '2026-08-02']);

        $this->actingAs($agent)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('quarter.scope', 'Vos courriers')
                ->where('quarter.period', 'T4 2026')
                ->where('quarter.previous.label', 'T3 2026')
                ->where('quarter.previous.rate', 100));
    }

    /** Courrier attribué à l'agent à la date donnée (délai agent 5 j, Directeur 2 j). */
    private function assigned(User $agent, string $assignedAt, array $overrides = []): Courrier
    {
        $this->sequence++;

        return Courrier::create($overrides + [
            'sequence_number' => $this->sequence,
            'registration_year' => 2026,
            'arrival_date' => $assignedAt,
            'origin_service' => 'Service bilan',
            'courrier_type_id' => CourrierType::where('code', 'ordinary')->value('id'),
            'subject' => "Courrier {$this->sequence}",
            'division_id' => $agent->division_id,
            'division_assigned_at' => $assignedAt,
            'assigned_agent_id' => $agent->id,
            'agent_assigned_at' => $assignedAt,
            'status' => isset($overrides['submitted_at']) ? Courrier::STATUS_DIVISION_VALIDATED : Courrier::STATUS_ASSIGNED_AGENT,
            'agent_delay_days' => 5,
            'director_delay_days' => 2,
            'created_by_id' => $this->admin->id,
            'updated_by_id' => $this->admin->id,
        ]);
    }
}
