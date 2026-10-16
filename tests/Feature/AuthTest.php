<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_login_and_logout(): void
    {
        $user = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret123', 'role' => 'admin']);

        $this->postJson('/login', ['email' => $user->email, 'password' => 'secret123'])->assertOk();
        $this->getJson('/api/me')->assertOk()->assertJsonPath('role', 'admin');
        $this->postJson('/logout')->assertOk();
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_role_access(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $approver = User::create(['name' => 'Manager', 'email' => 'manager@example.test', 'password' => 'secret123', 'role' => 'approver']);
        $this->actingAs($approver)->getJson('/api/booking-options')->assertForbidden();
    }

    public function test_csrf_required(): void
    {
        $this->app->instance('env', 'local');
        $this->postJson('/login', ['email' => 'admin@example.test', 'password' => 'secret123'])->assertStatus(419);
    }

    public function test_logout_returns_a_fresh_csrf_token_for_the_next_login(): void
    {
        $user = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret123', 'role' => 'admin']);
        $this->postJson('/login', ['email' => $user->email, 'password' => 'secret123'])->assertOk();

        $this->postJson('/logout')->assertOk()->assertJsonStructure(['csrf_token']);
    }
}
