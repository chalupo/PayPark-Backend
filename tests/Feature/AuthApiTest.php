<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_hashes_password_and_returns_a_working_token(): void
    {
        $response = $this->postJson('/api/register', $this->registrationData())
            ->assertCreated()->assertJsonPath('data.email', 'ana@example.com')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.remember_token');

        $user = User::sole();
        $this->assertNotSame('password123', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));
        $token = $response->json('token');
        $this->assertNotSame($token, $user->tokens()->sole()->token);
        $this->withToken($token)->getJson('/api/user')->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        $this->postJson('/api/register', $this->registrationData())->assertUnprocessable()
            ->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_incomplete_registration_is_rejected(): void
    {
        $this->postJson('/api/register', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_invalid_email_and_short_password_are_rejected(): void
    {
        $this->postJson('/api/register', [
            ...$this->registrationData(), 'email' => 'invalid', 'password' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_valid_login_returns_a_working_bearer_token(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertOk()->assertJsonPath('data.id', $user->id)->assertJsonPath('token_type', 'Bearer')
            ->assertJsonMissingPath('data.password');

        $this->withToken($response->json('token'))->getJson('/api/user')
            ->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_invalid_credentials_do_not_create_tokens(): void
    {
        $user = User::factory()->create();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/login', ['email' => 'unknown@example.com', 'password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_requires_credentials(): void
    {
        $this->postJson('/api/login', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_authenticated_user_can_be_queried(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/user')->assertOk()
            ->assertJsonPath('data.id', $user->id)->assertJsonPath('data.email', $user->email)
            ->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.remember_token');
    }

    public function test_protected_endpoints_reject_missing_or_invalid_tokens(): void
    {
        $this->get('/api/user')->assertUnauthorized()->assertJsonStructure(['message']);
        $this->postJson('/api/logout')->assertUnauthorized();
        $this->withToken('invalid-token')->getJson('/api/user')->assertUnauthorized();
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('current')->plainTextToken;
        $otherToken = $user->createToken('other')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertOk()
            ->assertJsonPath('message', 'Sesión cerrada correctamente.');
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // Cada petición HTTP real resuelve de nuevo el guard de autenticación.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)->getJson('/api/user')->assertOk();
    }

    private function registrationData(): array
    {
        return ['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'password123'];
    }
}
