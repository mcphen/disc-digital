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

class PerformanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DiscReferenceSeeder::class);
        Carbon::setTestNow('2026-10-16 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_measures_stage_durations_backlog_and_corrections(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $division = Division::where('code', 'DRI')->firstOrFail();
        $agent = User::factory()->create(['role' => 'agent', 'division_id' => $division->id]);

        // Signé : 1 j imputation, 2 j attribution, 4 j rédaction, 1 j validation, 1 j signature = 9 j (délai 7 j).
        $signed = $this->courrier($admin, 1, Courrier::STATUS_SIGNED, [
            'arrival_date' => '2026-09-01',
            'division_id' => $division->id,
            'division_assigned_at' => '2026-09-02',
            'assigned_agent_id' => $agent->id,
            'agent_assigned_at' => '2026-09-04',
            'submitted_at' => '2026-09-08',
            'division_validated_at' => '2026-09-09',
            'signed_at' => '2026-09-10',
        ]);
        $signed->events()->create(['event' => 'division_rejected', 'from_status' => 'pending_division_review', 'to_status' => 'correction_requested']);

        // En attente d'imputation depuis 40 jours.
        $this->courrier($admin, 2, Courrier::STATUS_REGISTERED, ['arrival_date' => '2026-09-06']);

        // En rédaction depuis 3 jours.
        $this->courrier($admin, 3, Courrier::STATUS_ASSIGNED_AGENT, [
            'arrival_date' => '2026-10-10',
            'division_id' => $division->id,
            'division_assigned_at' => '2026-10-11',
            'assigned_agent_id' => $agent->id,
            'agent_assigned_at' => '2026-10-13',
        ]);

        // Importé sans dates intermédiaires : exclu des étapes, pas compté à zéro.
        $this->courrier($admin, 4, Courrier::STATUS_SIGNED, [
            'arrival_date' => '2026-08-01',
            'signed_at' => '2026-08-31',
        ]);

        $this->actingAs($admin)
            ->get('/reports/performance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/Performance')
                ->where('performance.summary.signed', 2)
                ->where('performance.summary.open', 2)
                ->where('performance.summary.stuck_over_30', 1)
                ->where('performance.summary.bottleneck', 'À imputer à une division')
                ->where('performance.summary.on_time_rate', 0)
                ->where('performance.stages.0.key', 'division_assignment')
                ->where('performance.stages.0.count', 2)
                ->where('performance.stages.2.key', 'agent_processing')
                ->where('performance.stages.2.median', 4)
                ->where('performance.stages.2.is_longest', true)
                ->where('performance.stages.4.count', 1)
                ->where('performance.backlog.stages.0.status', Courrier::STATUS_REGISTERED)
                ->where('performance.backlog.stages.0.count', 1)
                ->where('performance.backlog.stages.0.over_30', 1)
                ->where('performance.backlog.oldest.0.age_days', 40)
                ->where('performance.corrections.rate', 100)
                ->where('performance.agents.0.in_hand', 1)
                ->where('performance.agents.0.median_processing_days', 4)
                ->where('performance.agents.0.on_time_rate', 100));
    }

    public function test_agents_cannot_open_it_and_auditors_do_not_see_per_agent_figures(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'agent']))
            ->get('/reports/performance')
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'auditor']))
            ->get('/reports/performance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('performance.agents', null));
    }

    /** @param array<string, mixed> $overrides */
    private function courrier(User $creator, int $sequence, string $status, array $overrides = []): Courrier
    {
        $type = CourrierType::where('code', 'ordinary')->firstOrFail();

        return Courrier::create($overrides + [
            'sequence_number' => $sequence,
            'registration_year' => 2026,
            'arrival_date' => '2026-10-10',
            'origin_service' => 'Service performance',
            'courrier_type_id' => $type->id,
            'subject' => "Courrier {$sequence}",
            'status' => $status,
            'agent_delay_days' => 5,
            'director_delay_days' => 2,
            'created_by_id' => $creator->id,
            'updated_by_id' => $creator->id,
        ]);
    }
}
