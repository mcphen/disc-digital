<?php

namespace Tests\Feature;

use App\Models\DiscSetting;
use App\Models\Division;
use App\Models\User;
use Database\Seeders\DiscReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DiscReferenceSeeder::class);
    }

    public function test_only_admin_can_open_disc_administration(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['role' => 'agent']);

        $this->actingAs($admin)->get(route('administration.users.index'))->assertOk();
        $this->actingAs($agent)->get(route('administration.users.index'))->assertForbidden();
    }

    public function test_admin_can_create_an_agent_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $division = Division::where('code', 'DSSE')->firstOrFail();

        $this->actingAs($admin)->post(route('administration.users.store'), [
            'name' => 'Agent DSSE',
            'email' => 'agent.dsse@example.com',
            'role' => 'agent',
            'division_id' => $division->id,
            'is_active' => true,
            'password' => 'mot-de-passe-solide',
            'password_confirmation' => 'mot-de-passe-solide',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'agent.dsse@example.com',
            'role' => 'agent',
            'division_id' => $division->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_alert_thresholds(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('administration.settings.alerts'), [
            'green_max' => 45,
            'yellow_max' => 75,
        ])->assertRedirect();

        $this->assertSame('0.45', DiscSetting::findOrFail('alert_green_max')->value);
        $this->assertSame('0.75', DiscSetting::findOrFail('alert_yellow_max')->value);
    }

    public function test_admin_can_open_import_screen(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('administration.imports.index'))->assertOk();
    }
}
