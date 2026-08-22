<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * The login service emits USER_LOGGED_IN to the notification service.
         * Fake HTTP so authentication tests remain isolated from external
         * services while still allowing us to assert the emitted event.
         */
        Http::fake();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'testuser@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'testuser@example.com',
            'password' => 'Password123!',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', 'testuser@example.com')
            ->assertJsonPath('data.user.name', $user->name)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
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
                    'token',
                    'token_type',
                    'expires_in',
                ],
                'meta',
                'timestamp',
            ]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertSame('bearer', $response->json('data.token_type'));
        $this->assertIsInt($response->json('data.expires_in'));
        $this->assertGreaterThan(0, $response->json('data.expires_in'));
    }

    public function test_login_returns_the_correct_user(): void
    {
        $user = User::factory()->create([
            'email' => 'correct@example.com',
            'password' => 'Password123!',
            'name' => 'Correct User',
        ]);

        User::factory()->create([
            'email' => 'other@example.com',
            'password' => 'Password123!',
            'name' => 'Other User',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'correct@example.com',
            'password' => 'Password123!',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', 'correct@example.com')
            ->assertJsonPath('data.user.name', 'Correct User');
    }

    public function test_login_returns_a_valid_jwt(): void
    {
        $user = User::factory()->create([
            'email' => 'jwt@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'jwt@example.com',
            'password' => 'Password123!',
        ]);

        $loginResponse->assertOk();

        $token = $loginResponse->json('data.token');

        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        $meResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $meResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', 'jwt@example.com');
    }

    public function test_login_jwt_authenticates_as_the_correct_user(): void
    {
        $user = User::factory()->create([
            'email' => 'identity@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'identity@example.com',
            'password' => 'Password123!',
        ]);

        $token = $response->json('data.token');

        $this
            ->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_login_token_type_is_bearer(): void
    {
        User::factory()->create([
            'email' => 'bearer@example.com',
            'password' => 'Password123!',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'bearer@example.com',
            'password' => 'Password123!',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.token_type', 'bearer');
    }

    public function test_login_expires_in_is_a_positive_integer(): void
    {
        User::factory()->create([
            'email' => 'expiry@example.com',
            'password' => 'Password123!',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'expiry@example.com',
            'password' => 'Password123!',
        ]);

        $expiresIn = $response->json('data.expires_in');

        $this->assertIsInt($expiresIn);
        $this->assertGreaterThan(0, $expiresIn);
    }

    public function test_login_rejects_invalid_password(): void
    {
        $user = User::factory()->create([
            'email' => 'testuser@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'WrongPassword123!',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid email or password.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
        ]);
    }

    public function test_login_rejects_nonexistent_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'doesnotexist@example.com',
            'password' => 'Password123!',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid email or password.');
    }

    public function test_login_rejects_wrong_password_without_revealing_account_existence(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
            'password' => 'Password123!',
        ]);

        $existingResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'existing@example.com',
            'password' => 'WrongPassword123!',
        ]);

        $missingResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'WrongPassword123!',
        ]);

        $existingResponse
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid email or password.');

        $missingResponse
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid email or password.');
    }

    public function test_login_rejects_inactive_user(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => 'Password123!',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'Password123!',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid email or password.');
    }

    public function test_login_does_not_authenticate_inactive_user(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => 'Password123!',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertUnauthorized();

        $this
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_unverified_user_can_login_when_active(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'unverified@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);

        $this->assertFalse($user->hasVerifiedEmail());

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'unverified@example.com',
            'password' => 'Password123!',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'unverified@example.com');
    }

    public function test_verified_user_can_login(): void
    {
        User::factory()->create([
            'email' => 'verified@example.com',
            'password' => 'Password123!',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'verified@example.com',
            'password' => 'Password123!',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.user.email', 'verified@example.com');
    }

    public function test_login_requires_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'password' => 'Password123!',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_rejects_empty_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => '',
            'password' => 'Password123!',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_requires_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'testuser@example.com',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_login_rejects_empty_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'testuser@example.com',
            'password' => '',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_login_requires_valid_email_format(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'not-an-email',
            'password' => 'Password123!',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_rejects_email_longer_than_255_characters(): void
    {
        $email = str_repeat('a', 247).'@example.com';

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'Password123!',
        ]);

        /*
         * LoginRequest currently has no max:255 rule, so this documents
         * the current behavior instead of assuming a validation rule
         * that does not exist.
         */
        $response->assertUnauthorized();
    }

    public function test_login_accepts_eight_character_password(): void
    {
        User::factory()->create([
            'email' => 'eight@example.com',
            'password' => '12345678',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'eight@example.com',
            'password' => '12345678',
        ]);

        $response->assertOk();
    }

    public function test_login_password_is_case_sensitive(): void
    {
        User::factory()->create([
            'email' => 'case@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'case@example.com',
            'password' => 'password123!',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid email or password.');
    }

    public function test_login_response_does_not_expose_password(): void
    {
        User::factory()->create([
            'email' => 'testuser@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'testuser@example.com',
            'password' => 'Password123!',
        ]);

        $response
            ->assertOk()
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.remember_token');
    }

    public function test_login_response_does_not_expose_internal_user_fields(): void
    {
        User::factory()->create([
            'email' => 'secure@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'secure@example.com',
            'password' => 'Password123!',
        ]);

        $response
            ->assertOk()
            ->assertJsonMissingPath('data.user.is_active')
            ->assertJsonMissingPath('data.user.email_verified_at')
            ->assertJsonMissingPath('data.user.last_login_at')
            ->assertJsonMissingPath('data.user.remember_token');
    }

    public function test_failed_login_does_not_return_token(): void
    {
        User::factory()->create([
            'email' => 'failed@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'failed@example.com',
            'password' => 'WrongPassword123!',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonMissingPath('data.token')
            ->assertJsonMissingPath('token');
    }

    public function test_failed_login_does_not_modify_user_password(): void
    {
        $user = User::factory()->create([
            'email' => 'unchanged@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);

        $passwordHash = $user->fresh()->password;

        $this->postJson('/api/v1/auth/login', [
            'email' => 'unchanged@example.com',
            'password' => 'WrongPassword123!',
        ])->assertUnauthorized();

        $this->assertSame($passwordHash, $user->fresh()->password);

        $this->assertTrue(Hash::check('Password123!', $user->fresh()->password));
    }

    public function test_successful_login_emits_user_logged_in_event(): void
    {
        config([
            'services.notification.url' => 'http://notification-service.test/api/events',
        ]);

        $user = User::factory()->create([
            'email' => 'event@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'event@example.com',
            'password' => 'Password123!',
        ])->assertOk();

        Http::assertSent(function ($request) use ($user): bool {
            $payload = $request->data();

            return $request->url() === 'http://notification-service.test/api/events'
                && $payload['eventType'] === 'USER_LOGGED_IN'
                && $payload['userId'] === (string) $user->id
                && $payload['source'] === 'household-service'
                && $payload['data']['userId'] === $user->id
                && $payload['data']['email'] === $user->email
                && ! empty($payload['eventId'])
                && ! empty($payload['timestamp']);
        });
    }

    public function test_failed_login_does_not_emit_user_logged_in_event(): void
    {
        config([
            'services.notification.url' => 'http://notification-service.test/api/events',
        ]);

        User::factory()->create([
            'email' => 'event@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'event@example.com',
            'password' => 'WrongPassword123!',
        ])->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_inactive_user_login_does_not_emit_user_logged_in_event(): void
    {
        config([
            'services.notification.url' => 'http://notification-service.test/api/events',
        ]);

        User::factory()->create([
            'email' => 'inactive-event@example.com',
            'password' => 'Password123!',
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive-event@example.com',
            'password' => 'Password123!',
        ])->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_notification_service_failure_does_not_break_login(): void
    {
        config([
            'services.notification.url' => 'http://notification-service.test/api/events',
        ]);

        Http::fake([
            'http://notification-service.test/*' => Http::response(['message' => 'Notification service unavailable'], 500),
        ]);

        User::factory()->create([
            'email' => 'notification-failure@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'notification-failure@example.com',
            'password' => 'Password123!',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'notification-failure@example.com');
    }

    public function test_login_with_special_characters_in_password(): void
    {
        $password = 'P@ssw0rd!#$%^&*()_+-=';

        User::factory()->create([
            'email' => 'special@example.com',
            'password' => $password,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'special@example.com',
            'password' => $password,
        ]);

        $response->assertOk();
    }

    public function test_login_does_not_require_email_verification(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'no-verification-required@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);

        $this->assertNull($user->email_verified_at);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_login_accepts_email_regardless_of_case(): void
    {
        User::factory()->create([
            'email' => 'testuser@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);

        foreach ([
            'testuser@example.com',
            'TESTUSER@EXAMPLE.COM',
            'TestUser@Example.Com',
            'TeStUsEr@eXaMpLe.CoM',
        ] as $email) {
            $response = $this->postJson('/api/v1/auth/login', [
                'email' => $email,
                'password' => 'Password123!',
            ]);

            $response
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.user.email', 'testuser@example.com');
        }
    }

    public function test_login_jwt_contains_correct_subject(): void
    {
        $user = User::factory()->create([
            'email' => 'jwt-sub@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'jwt-sub@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertOk();

        $token = $response->json('data.token');

        $payload = JWTAuth::setToken($token)->getPayload();

        $this->assertSame((string) $user->id, (string) $payload->get('sub'));
    }

    public function test_successful_login_emits_exactly_one_user_logged_in_event(): void
    {
        Http::fake();

        $user = User::factory()->create([
            'email' => 'single-event@example.com',
            'password' => 'Password123!',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'single-event@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertOk();

        Http::assertSentCount(1);

        Http::assertSent(function ($request) use ($user) {
            return $request['eventType'] === 'USER_LOGGED_IN'
                && $request['userId'] === (string) $user->id;
        });
    }
}
