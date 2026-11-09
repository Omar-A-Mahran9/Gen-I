<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ApiFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_user_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/user')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated')
            ->assertJsonStructure(['code', 'message', 'request_id'])
            ->assertHeader('X-Request-Id');
    }

    public function test_token_can_be_issued_used_and_revoked(): void
    {
        $user = $this->userWithRole('staff', ['email' => 'api@geni.test']);

        $issued = $this->postJson('/api/v1/auth/token', ['email' => 'api@geni.test', 'password' => 'password', 'device_name' => 'tests'])
            ->assertCreated()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'api@geni.test')
            ->assertJsonMissingPath('data.user.password');

        $token = $issued->json('data.token');

        $this->withToken($token)->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.roles', ['staff']);

        $this->withToken($token)->deleteJson('/api/v1/auth/token')->assertOk()->assertJsonPath('success', true);
        $this->assertNull(PersonalAccessToken::findToken($token));
        $this->assertDatabaseHas('audit_logs', ['event' => 'api.token_revoked', 'user_id' => $user->id]);
    }

    public function test_revoked_token_no_longer_authenticates(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('device');
        $token->accessToken->delete();

        $this->withToken($token->plainTextToken)->getJson('/api/v1/user')->assertUnauthorized();
    }

    public function test_invalid_credentials_return_a_validation_envelope(): void
    {
        User::factory()->create(['email' => 'api@geni.test']);

        $this->postJson('/api/v1/auth/token', ['email' => 'api@geni.test', 'password' => 'nope', 'device_name' => 'x'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonStructure(['code', 'message', 'errors' => ['email'], 'request_id']);

        $this->postJson('/api/v1/auth/token', [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['email', 'password', 'device_name']]);
    }

    public function test_inactive_users_cannot_obtain_or_use_tokens(): void
    {
        $user = User::factory()->inactive()->create(['email' => 'off@geni.test']);

        $this->postJson('/api/v1/auth/token', ['email' => 'off@geni.test', 'password' => 'password', 'device_name' => 'x'])->assertStatus(422);

        $token = $user->createToken('legacy')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/user')->assertUnauthorized();
    }

    public function test_api_uses_accept_language_for_messages(): void
    {
        $this->getJson('/api/v1/user', ['Accept-Language' => 'en'])
            ->assertJsonPath('message', 'Authentication is required.')
            ->assertHeader('Content-Language', 'en');

        $this->getJson('/api/v1/user', ['Accept-Language' => 'ar'])
            ->assertJsonPath('message', 'يجب تسجيل الدخول.');
    }

    public function test_unknown_endpoints_return_the_not_found_envelope(): void
    {
        $this->getJson('/api/v1/does-not-exist')->assertNotFound()->assertJsonPath('code', 'not_found');
    }

    public function test_token_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/token', ['email' => 'x@geni.test', 'password' => 'bad', 'device_name' => 'x']);
        }

        $this->postJson('/api/v1/auth/token', ['email' => 'x@geni.test', 'password' => 'bad', 'device_name' => 'x'])
            ->assertStatus(429)
            ->assertJsonPath('code', 'rate_limited');
    }

    public function test_admin_session_does_not_authenticate_the_api(): void
    {
        $this->actingAs($this->superAdmin(), 'web');

        $this->getJson('/api/v1/user')->assertUnauthorized();
    }
}
