<?php

namespace Tests\Feature;

use App\Models\Courrier;
use App\Models\CourrierType;
use App\Models\Division;
use App\Models\User;
use App\Services\Disc\CourrierDeadlineService;
use Database\Seeders\DiscReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CourrierWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DiscReferenceSeeder::class);
    }

    public function test_secretariat_can_register_a_courrier_with_delay_snapshot(): void
    {
        $user = User::factory()->create(['role' => 'secretariat']);
        $type = CourrierType::where('code', 'ordinary')->firstOrFail();

        $response = $this->actingAs($user)->post(route('courriers.store'), [
            'arrival_date' => '2026-10-10',
            'reference' => 'REF-001',
            'origin_service' => 'DGCPT',
            'courrier_type_id' => $type->id,
            'subject' => 'Demande de traitement',
            'arrival_number' => '607',
        ]);

        $courrier = Courrier::firstOrFail();
        $response->assertRedirect(route('courriers.show', $courrier));
        $this->assertSame(5, $courrier->agent_delay_days);
        $this->assertSame(2, $courrier->director_delay_days);
        $this->assertSame(Courrier::STATUS_REGISTERED, $courrier->status);
        $this->assertDatabaseHas('courrier_events', ['courrier_id' => $courrier->id, 'event' => 'created']);
    }

    public function test_agent_cannot_register_a_courrier(): void
    {
        $agent = User::factory()->create(['role' => 'agent']);
        $type = CourrierType::where('code', 'ordinary')->firstOrFail();

        $this->actingAs($agent)->post(route('courriers.store'), [
            'arrival_date' => '2026-10-10',
            'origin_service' => 'DGCPT',
            'courrier_type_id' => $type->id,
            'subject' => 'Demande de traitement',
        ])->assertForbidden();
    }

    public function test_administrator_can_display_a_courrier_detail(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $type = CourrierType::where('code', 'ordinary')->firstOrFail();
        $courrier = Courrier::create([
            'sequence_number' => 1,
            'registration_year' => 2026,
            'arrival_date' => '2026-10-10',
            'origin_service' => 'DGCPT',
            'courrier_type_id' => $type->id,
            'subject' => 'Détail à afficher',
            'status' => Courrier::STATUS_REGISTERED,
            'agent_delay_days' => 5,
            'director_delay_days' => 2,
            'created_by_id' => $admin->id,
            'updated_by_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('courriers.show', $courrier))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('courriers/Show')
                ->where('courrier.id', $courrier->id)
                ->where('courrier.registration_number', '0001/2026')
                ->where('permissions.assign', true)
                ->where('permissions.review', true)
                ->where('permissions.sign', true));
    }

    public function test_courrier_can_be_assigned_submitted_and_signed(): void
    {
        $division = Division::where('code', 'DSSE')->firstOrFail();
        $type = CourrierType::where('code', 'ordinary')->firstOrFail();
        $director = User::factory()->create(['role' => 'director']);
        $agent = User::factory()->create(['role' => 'agent', 'division_id' => $division->id]);
        $divisionHead = User::factory()->create(['role' => 'division_head', 'division_id' => $division->id]);
        $courrier = Courrier::create([
            'sequence_number' => 1,
            'registration_year' => 2026,
            'arrival_date' => '2026-10-01',
            'origin_service' => 'DGCPT',
            'courrier_type_id' => $type->id,
            'subject' => 'Courrier à traiter',
            'status' => Courrier::STATUS_REGISTERED,
            'agent_delay_days' => 5,
            'director_delay_days' => 2,
            'created_by_id' => $director->id,
            'updated_by_id' => $director->id,
        ]);

        $this->actingAs($director)->put(route('courriers.assign', $courrier), [
            'division_id' => $division->id,
            'division_assigned_at' => '2026-10-01',
            'assigned_agent_id' => $agent->id,
            'agent_assigned_at' => '2026-10-02',
            'instructions' => 'Préparer une réponse.',
        ])->assertRedirect();

        $this->actingAs($agent)->put(route('courriers.submit', $courrier), [
            'submitted_at' => '2026-10-06',
        ])->assertRedirect();

        $this->assertSame(Courrier::STATUS_PENDING_DIVISION_REVIEW, $courrier->fresh()->status);
        $this->assertDatabaseHas('courrier_notifications', [
            'courrier_id' => $courrier->id,
            'user_id' => $divisionHead->id,
            'level' => 'REVIEW',
        ]);

        $this->actingAs($divisionHead)->put(route('courriers.review', $courrier), [
            'decision' => 'approve',
            'reviewed_at' => '2026-10-06',
            'comment' => 'Projet conforme.',
        ])->assertRedirect();

        $this->actingAs($director)->put(route('courriers.sign', $courrier), [
            'signed_at' => '2026-10-07',
        ])->assertRedirect();

        $courrier->refresh()->load('type');
        $this->assertSame(Courrier::STATUS_SIGNED, $courrier->status);
        $this->assertSame($divisionHead->id, $courrier->division_validated_by_id);
        $this->assertSame('Dans les délais', app(CourrierDeadlineService::class)->calculate($courrier)['observation']);
        $this->assertDatabaseCount('courrier_events', 4);
    }

    public function test_division_head_can_request_a_correction_before_approval(): void
    {
        $division = Division::where('code', 'DRI')->firstOrFail();
        $otherDivision = Division::where('code', 'DP')->firstOrFail();
        $type = CourrierType::where('code', 'ordinary')->firstOrFail();
        $creator = User::factory()->create(['role' => 'director']);
        $agent = User::factory()->create(['role' => 'agent', 'division_id' => $division->id]);
        $divisionHead = User::factory()->create(['role' => 'division_head', 'division_id' => $division->id]);
        $otherHead = User::factory()->create(['role' => 'division_head', 'division_id' => $otherDivision->id]);
        $courrier = Courrier::create([
            'sequence_number' => 2,
            'registration_year' => 2026,
            'arrival_date' => '2026-10-01',
            'origin_service' => 'DGCPT',
            'courrier_type_id' => $type->id,
            'subject' => 'Projet à corriger',
            'division_id' => $division->id,
            'division_assigned_at' => '2026-10-01',
            'assigned_agent_id' => $agent->id,
            'agent_assigned_at' => '2026-10-02',
            'status' => Courrier::STATUS_ASSIGNED_AGENT,
            'agent_delay_days' => 5,
            'director_delay_days' => 2,
            'created_by_id' => $creator->id,
            'updated_by_id' => $creator->id,
        ]);

        $this->actingAs($agent)->put(route('courriers.submit', $courrier), [
            'submitted_at' => '2026-10-04',
        ])->assertRedirect();

        $this->actingAs($otherHead)->put(route('courriers.review', $courrier), [
            'decision' => 'approve',
            'reviewed_at' => '2026-10-05',
        ])->assertForbidden();

        $this->actingAs($divisionHead)->put(route('courriers.review', $courrier), [
            'decision' => 'reject',
            'reviewed_at' => '2026-10-05',
        ])->assertSessionHasErrors('comment');

        $this->actingAs($divisionHead)->put(route('courriers.review', $courrier), [
            'decision' => 'reject',
            'reviewed_at' => '2026-10-05',
            'comment' => 'Préciser les montants dans le projet.',
        ])->assertRedirect();

        $this->assertSame(Courrier::STATUS_CORRECTION_REQUESTED, $courrier->fresh()->status);
        $this->assertDatabaseHas('courrier_notifications', [
            'courrier_id' => $courrier->id,
            'user_id' => $agent->id,
            'level' => 'CORRECT',
        ]);

        $this->actingAs($agent)->put(route('courriers.submit', $courrier), [
            'submitted_at' => '2026-10-06',
            'comment' => 'Montants ajoutés.',
        ])->assertRedirect();

        $this->actingAs($divisionHead)->put(route('courriers.review', $courrier), [
            'decision' => 'approve',
            'reviewed_at' => '2026-10-06',
        ])->assertRedirect();

        $this->assertSame(Courrier::STATUS_DIVISION_VALIDATED, $courrier->fresh()->status);
        $this->assertDatabaseHas('courrier_events', [
            'courrier_id' => $courrier->id,
            'event' => 'division_rejected',
            'comment' => 'Préciser les montants dans le projet.',
        ]);
    }

    public function test_no_response_courrier_is_closed_immediately(): void
    {
        $user = User::factory()->create(['role' => 'secretariat']);
        $type = CourrierType::where('code', 'no_response')->firstOrFail();

        $this->actingAs($user)->post(route('courriers.store'), [
            'arrival_date' => '2026-10-10',
            'origin_service' => 'DGCPT',
            'courrier_type_id' => $type->id,
            'subject' => 'Courrier pour information',
        ])->assertRedirect();

        $this->assertSame(Courrier::STATUS_CLOSED_NO_RESPONSE, Courrier::firstOrFail()->status);
    }
}
