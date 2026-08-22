<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class AuthSessionTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Session Test User',
            'email' => 'session@example.com',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ], $attributes));
    }

    private function tokenFor(User $user): string
    {
        return JWTAuth::fromUser($user);
    }

    private function authHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/v1/auth/me
    |--------------------------------------------------------------------------
    */

    public function test_authenticated_user_can_retrieve_their_profile(): void
    {
        $user = $this->createUser();

        $token = $this->tokenFor($user);

        $response = $this
            ->withHeaders($this->authHeaders($token))
            ->getJson('/api/v1/auth/me');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Profile retrieved successfully.')
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.name', $user->name)
            ->assertJsonPath('data.username', $user->username)
            ->assertJsonPath('data.country_code', $user->country_code)
            ->assertJsonPath('data.phone', $user->phone)
            ->assertJsonPath('data.bio', $user->bio);

        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'name',
                'username',
                'email',
                'country_code',
                'phone',
                'bio',
                'created_at',
                'updated_at',
            ],
            'meta',
            'timestamp',
        ]);
    }

    public function test_me_returns_the_authenticated_user_not_another_user(): void
    {
        $authenticatedUser = $this->createUser([
            'email' => 'authenticated@example.com',
            'name' => 'Authenticated User',
        ]);

        $otherUser = $this->createUser([
            'email' => 'other@example.com',
            'name' => 'Other User',
        ]);

        $token = $this->tokenFor($authenticatedUser);

        $response = $this
            ->withHeaders($this->authHeaders($token))
            ->getJson('/api/v1/auth/me');

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $authenticatedUser->id)
            ->assertJsonPath('data.email', $authenticatedUser->email)
            ->assertJsonPath('data.name', $authenticatedUser->name)
            ->assertJsonMissing([
                'email' => $otherUser->email,
            ])
            ->assertJsonMissing([
                'name' => $otherUser->name,
            ]);
    }

    public function test_me_does_not_expose_password(): void
    {
        $user = $this->createUser();

        $token = $this->tokenFor($user);

        $response = $this
            ->withHeaders($this->authHeaders($token))
            ->getJson('/api/v1/auth/me');

        $response
            ->assertOk()
            ->assertJsonMissingPath('data.password');
    }

    public function test_me_does_not_expose_remember_token(): void
    {
        $user = $this->createUser();

        $token = $this->tokenFor($user);

        $response = $this
            ->withHeaders($this->authHeaders($token))
            ->getJson('/api/v1/auth/me');

        $response
            ->assertOk()
            ->assertJsonMissingPath('data.remember_token');
    }

    public function test_me_does_not_expose_internal_account_state(): void
    {
        $user = $this->createUser();

        $token = $this->tokenFor($user);

        $response = $this
            ->withHeaders($this->authHeaders($token))
            ->getJson('/api/v1/auth/me');

        $response
            ->assertOk()
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token')
            ->assertJsonMissingPath('data.is_active');
    }

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    }

    public function test_me_rejects_malformed_bearer_token(): void
    {
        $response = $this
            ->withHeaders([
                'Authorization' => 'Bearer definitely-not-a-valid-jwt',
                'Accept' => 'application/json',
            ])
            ->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    }

    public function test_me_rejects_invalid_bearer_token(): void
    {
        $response = $this
            ->withHeaders([
                'Authorization' => 'Bearer eyJhbGciOiJIUzI1NiJ9.invalid.token',
                'Accept' => 'application/json',
            ])
            ->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    }

    public function test_me_does_not_allow_user_to_impersonate_another_user(): void
    {
        $userA = $this->createUser([
            'email' => 'user-a@example.com',
            'name' => 'User A',
        ]);

        $userB = $this->createUser([
            'email' => 'user-b@example.com',
            'name' => 'User B',
        ]);

        $tokenA = $this->tokenFor($userA);

        $response = $this
            ->withHeaders($this->authHeaders($tokenA))
            ->getJson('/api/v1/auth/me');

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $userA->id)
            ->assertJsonPath('data.email', $userA->email)
            ->assertJsonPath('data.name', $userA->name)
            ->assertJsonMissing([
                'email' => $userB->email,
                'name' => $userB->name,
            ]);
    }
}
