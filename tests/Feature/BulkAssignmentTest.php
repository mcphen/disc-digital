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

class BulkAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DiscReferenceSeeder::class);
    }

    public function test_admin_can_open_the_bulk_assignment_page_and_assign_multiple_courriers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $division = Division::where('code', 'DRI')->firstOrFail();
        $agent = User::factory()->create(['role' => 'agent', 'division_id' => $division->id]);
        $first = $this->courrier($admin, 1, 'Premier courrier');
        $second = $this->courrier($admin, 2, 'Deuxième courrier');

        $this->actingAs($admin)
            ->get(route('courriers.bulk-assignment.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('courriers/BulkAssignment')
                ->has('courriers', 2)
                ->where('courriers.0.id', $second->id)
                ->where('courriers.1.id', $first->id));

        $response = $this->actingAs($admin)->put(route('courriers.bulk-assignment.store'), [
            'courrier_ids' => [$first->id, $second->id],
            'division_id' => $division->id,
            'division_assigned_at' => '2026-10-14',
            'assigned_agent_id' => $agent->id,
            'agent_assigned_at' => '2026-10-14',
            'instructions' => 'Préparer les projets de réponse.',
            'comment' => 'Affectation groupée du jour.',
        ])->assertRedirect(route('courriers.bulk-assignment.index'))
            ->assertSessionHas('bulk_assignment_undo')
            ->assertSessionHas('bulk_undo');

        foreach ([$first, $second] as $courrier) {
            $this->assertDatabaseHas('courriers', [
                'id' => $courrier->id,
                'division_id' => $division->id,
                'assigned_agent_id' => $agent->id,
                'status' => Courrier::STATUS_ASSIGNED_AGENT,
            ]);
            $this->assertDatabaseHas('courrier_events', [
                'courrier_id' => $courrier->id,
                'event' => 'bulk_assigned',
                'comment' => 'Affectation groupée du jour.',
            ]);
        }

        $token = $response->getSession()->get('bulk_assignment_undo')['token'];
        $this->actingAs($admin)
            ->post(route('courriers.bulk-assignment.undo'), ['token' => $token])
            ->assertRedirect(route('courriers.bulk-assignment.index'));

        foreach ([$first, $second] as $courrier) {
            $this->assertDatabaseHas('courriers', [
                'id' => $courrier->id,
                'division_id' => null,
                'assigned_agent_id' => null,
                'status' => Courrier::STATUS_REGISTERED,
            ]);
            $this->assertDatabaseHas('courrier_events', [
                'courrier_id' => $courrier->id,
                'event' => 'bulk_assignment_undone',
            ]);
        }
    }

    public function test_division_head_is_limited_to_their_division(): void
    {
        $ownDivision = Division::where('code', 'DRI')->firstOrFail();
        $otherDivision = Division::where('code', 'DP')->firstOrFail();
        $head = User::factory()->create(['role' => 'division_head', 'division_id' => $ownDivision->id]);
        $courrier = $this->courrier($head, 1, 'Courrier de la division', [
            'division_id' => $ownDivision->id,
            'division_assigned_at' => '2026-10-10',
            'status' => Courrier::STATUS_ASSIGNED_DIVISION,
        ]);

        $this->actingAs($head)->put(route('courriers.bulk-assignment.store'), [
            'courrier_ids' => [$courrier->id],
            'division_id' => $otherDivision->id,
            'division_assigned_at' => '2026-10-14',
        ])->assertSessionHasErrors('division_id');

        $this->assertSame($ownDivision->id, $courrier->fresh()->division_id);
    }

    public function test_agent_cannot_use_bulk_assignment(): void
    {
        $agent = User::factory()->create(['role' => 'agent']);

        $this->actingAs($agent)
            ->get(route('courriers.bulk-assignment.index'))
            ->assertForbidden();
    }

    /** @param array<string, mixed> $overrides */
    private function courrier(User $creator, int $sequence, string $subject, array $overrides = []): Courrier
    {
        $type = CourrierType::where('code', 'ordinary')->firstOrFail();

        return Courrier::create($overrides + [
            'sequence_number' => $sequence,
            'registration_year' => 2026,
            'arrival_date' => '2026-10-10',
            'origin_service' => 'Service test',
            'courrier_type_id' => $type->id,
            'subject' => $subject,
            'status' => Courrier::STATUS_REGISTERED,
            'agent_delay_days' => 5,
            'director_delay_days' => 2,
            'created_by_id' => $creator->id,
            'updated_by_id' => $creator->id,
        ]);
    }
}
