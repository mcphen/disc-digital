<?php

namespace Tests\Feature;

use App\Models\Courrier;
use App\Models\CourrierType;
use App\Models\Division;
use App\Models\User;
use Database\Seeders\DiscReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NavigationExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DiscReferenceSeeder::class);
    }

    public function test_work_queues_filter_courriers_and_expose_navigation_counts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $division = Division::where('code', 'DRI')->firstOrFail();
        $agent = User::factory()->create(['role' => 'agent', 'division_id' => $division->id]);
        $unassigned = $this->courrier($admin, 1, Courrier::STATUS_REGISTERED);
        $this->courrier($admin, 2, Courrier::STATUS_ASSIGNED_AGENT, [
            'division_id' => $division->id,
            'division_assigned_at' => '2026-10-10',
            'assigned_agent_id' => $agent->id,
            'agent_assigned_at' => '2026-10-10',
        ]);

        $this->actingAs($admin)
            ->get('/courriers?queue=unassigned')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('courriers/Index')
                ->where('filters.queue', 'unassigned')
                ->where('courriers.total', 1)
                ->where('courriers.data.0.id', $unassigned->id)
                ->where('navigation.unassigned', 1));

        $this->actingAs($agent)
            ->get('/courriers?queue=mine')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.queue', 'mine')
                ->where('courriers.total', 1)
                ->where('navigation.mine', 1));
    }

    public function test_courrier_detail_keeps_a_safe_return_url(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $courrier = $this->courrier($admin, 1, Courrier::STATUS_REGISTERED);

        $this->actingAs($admin)
            ->get(route('courriers.show', $courrier).'?return='.urlencode('/courriers?queue=unassigned&page=2'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('backUrl', '/courriers?queue=unassigned&page=2'));

        $this->actingAs($admin)
            ->get(route('courriers.show', $courrier).'?return='.urlencode('//malicious.example'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('backUrl', '/courriers'));
    }

    /** @param array<string, mixed> $overrides */
    private function courrier(User $creator, int $sequence, string $status, array $overrides = []): Courrier
    {
        $type = CourrierType::where('code', 'ordinary')->firstOrFail();

        return Courrier::create($overrides + [
            'sequence_number' => $sequence,
            'registration_year' => 2026,
            'arrival_date' => '2026-10-10',
            'origin_service' => 'Service navigation',
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
