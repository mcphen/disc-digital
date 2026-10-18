<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_dashboard_actions_follow_server_side_permissions(): void
    {
        $agent = User::factory()->create(['role' => 'agent', 'is_active' => true]);

        $this->actingAs($agent)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('permissions.create', false)
                ->where('permissions.bulkAssign', false)
                ->where('permissions.reports', false)
                ->where('permissions.review', false)
                ->where('permissions.sign', false));

        $secretariat = User::factory()->create(['role' => 'secretariat', 'is_active' => true]);

        $this->actingAs($secretariat)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('permissions.create', true)
                ->where('permissions.bulkAssign', false));
    }
}
