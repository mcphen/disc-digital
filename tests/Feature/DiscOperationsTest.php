<?php

namespace Tests\Feature;

use App\Models\Courrier;
use App\Models\CourrierType;
use App\Models\Division;
use App\Models\User;
use App\Services\Disc\DeadlineNotificationService;
use Database\Seeders\DiscReferenceSeeder;
use Database\Seeders\DiscUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DiscOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DiscReferenceSeeder::class);
    }

    public function test_user_seeder_creates_every_operational_role(): void
    {
        $this->seed(DiscUserSeeder::class);

        foreach (['admin', 'director', 'secretariat', 'division_head', 'agent', 'auditor'] as $role) {
            $this->assertDatabaseHas('users', ['role' => $role, 'is_active' => true]);
        }
    }

    public function test_authorized_user_can_upload_and_download_a_private_attachment(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $courrier = $this->courrier($admin);

        $this->actingAs($admin)->post(route('courriers.attachments.store', $courrier), [
            'file' => UploadedFile::fake()->create('reponse.pdf', 120, 'application/pdf'),
        ])->assertRedirect();

        $attachment = $courrier->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->actingAs($admin)->get(route('attachments.download', $attachment))->assertOk();
    }

    public function test_deadline_notifications_are_idempotent(): void
    {
        Carbon::setTestNow('2026-10-14 08:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $courrier = $this->courrier($admin, ['arrival_date' => '2026-09-01']);

        $first = app(DeadlineNotificationService::class)->dispatch();
        $second = app(DeadlineNotificationService::class)->dispatch();

        $this->assertGreaterThan(0, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertDatabaseHas('courrier_notifications', [
            'courrier_id' => $courrier->id,
            'user_id' => $admin->id,
            'level' => 'ROUGE',
        ]);
    }

    public function test_reports_are_restricted_and_exportable(): void
    {
        $director = User::factory()->create(['role' => 'director']);
        $agent = User::factory()->create(['role' => 'agent']);
        $this->courrier($director);

        $this->actingAs($agent)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($director)->get(route('reports.index'))->assertOk();
        $this->actingAs($director)->get(route('reports.export'))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_read_notifications_have_their_own_tab(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $courrier = $this->courrier($admin);
        $courrier->notifications()->create([
            'user_id' => $admin->id,
            'level' => 'APPROVED',
            'message' => 'Validation terminée.',
            'read_at' => now(),
        ]);
        $courrier->notifications()->create([
            'user_id' => $admin->id,
            'level' => 'ROUGE',
            'message' => 'Échéance dépassée.',
        ]);

        $this->actingAs($admin)
            ->get('/notifications?category=read')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.category', 'read')
                ->where('counts.read', 1)
                ->has('notifications.data', 1)
                ->where('notifications.data.0.message', 'Validation terminée.'));
    }

    /** @param array<string, mixed> $overrides */
    private function courrier(User $creator, array $overrides = []): Courrier
    {
        $type = CourrierType::where('code', 'ordinary')->firstOrFail();
        $division = Division::where('code', 'DRI')->firstOrFail();

        return Courrier::create($overrides + [
            'sequence_number' => 1,
            'registration_year' => 2026,
            'arrival_date' => '2026-10-01',
            'origin_service' => 'Service test',
            'courrier_type_id' => $type->id,
            'subject' => 'Courrier de test',
            'division_id' => $division->id,
            'status' => Courrier::STATUS_ASSIGNED_DIVISION,
            'agent_delay_days' => 5,
            'director_delay_days' => 2,
            'created_by_id' => $creator->id,
            'updated_by_id' => $creator->id,
        ]);
    }
}
