<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

    public function test_login_returns_the_rotated_csrf_token_for_the_next_mutation(): void
    {
        $this->app->instance('env', 'local');
        $user = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret123', 'role' => 'admin']);
        $guestToken = Str::random(40);

        $login = $this->withSession(['_token' => $guestToken])
            ->withHeader('X-CSRF-TOKEN', $guestToken)
            ->postJson('/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertOk();

        $freshToken = $login->json('csrf_token');
        $this->assertIsString($freshToken);
        $this->assertNotSame($guestToken, $freshToken);
        $this->withHeader('X-CSRF-TOKEN', $freshToken)->postJson('/api/bookings', [])->assertUnprocessable();
    }
}
