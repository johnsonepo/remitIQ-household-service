<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function validRegistrationData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test User',
            'username' => 'testuser001',
            'email' => 'testuser@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ], $overrides);
    }

    public function test_user_can_register_successfully(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData());

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Account created successfully.')
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
            ])
            ->assertJsonPath('data.user.name', 'Test User')
            ->assertJsonPath('data.user.username', 'testuser001')
            ->assertJsonPath('data.user.email', 'testuser@example.com')
            ->assertJsonPath('data.token_type', 'bearer')
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.remember_token');

        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'username' => 'testuser001',
            'email' => 'testuser@example.com',
        ]);

        $user = User::where('email', 'testuser@example.com')->firstOrFail();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('testuser001', $user->username);
        $this->assertSame('testuser@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('Password123!', $user->password));
        $this->assertNotSame('Password123!', $user->password);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_registration_returns_a_valid_jwt(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData());

        $response->assertCreated();

        $token = $response->json('data.token');

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        $this->assertSame('bearer', $response->json('data.token_type'));
        $this->assertIsInt($response->json('data.expires_in'));
        $this->assertGreaterThan(0, $response->json('data.expires_in'));
    }

    public function test_registration_persists_optional_fields(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData([
            'country_code' => 'CM',
            'phone' => '+237670000000',
        ]));

        $response
            ->assertCreated()
            ->assertJsonPath('data.user.country_code', 'CM')
            ->assertJsonPath('data.user.phone', '+237670000000');

        $this->assertDatabaseHas('users', [
            'email' => 'testuser@example.com',
            'country_code' => 'CM',
            'phone' => '+237670000000',
        ]);
    }

    public function test_registration_allows_username_to_be_omitted(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData([
            'username' => null,
            'email' => 'withoutusername@example.com',
        ]));

        $response
            ->assertCreated()
            ->assertJsonPath('data.user.username', null);

        $this->assertDatabaseHas('users', [
            'email' => 'withoutusername@example.com',
            'username' => null,
        ]);
    }

    public function test_registration_allows_country_code_to_be_omitted(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData([
            'email' => 'nocountry@example.com',
        ]));

        $response
            ->assertCreated()
            ->assertJsonPath('data.user.country_code', null);
    }

    public function test_registration_allows_phone_to_be_omitted(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData([
            'email' => 'nophone@example.com',
        ]));

        $response
            ->assertCreated()
            ->assertJsonPath('data.user.phone', null);
    }

    public function test_registration_requires_name(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData([
            'name' => null,
        ]));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_rejects_empty_name(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData([
            'name' => '',
        ]));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_registration_rejects_name_longer_than_255_characters(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData([
            'name' => str_repeat('a', 256),
        ]));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_registration_requires_email(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData([
            'email' => null,
        ]));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_rejects_invalid_email(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData([
            'email' => 'not-an-email',
        ]));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData([
            'email' => 'existing@example.com',
        ]));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_requires_password(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData([
            'password' => null,
            'password_confirmation' => null,
        ]));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registration_rejects_password_shorter_than_eight_characters(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData([
            'password' => '1234567',
            'password_confirmation' => '1234567',
        ]));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registration_accepts_password_of_exactly_eight_characters(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData([
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ]));

        $response->assertCreated();
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData([
            'password_confirmation' => 'DifferentPassword123!',
        ]));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_requires_password_confirmation_field(): void
    {
        $data = $this->validRegistrationData();
        unset($data['password_confirmation']);

        $response = $this->postJson('/api/v1/auth/register', $data);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_rejects_duplicate_username(): void
    {
        User::factory()->create([
            'username' => 'existinguser',
        ]);

        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData([
            'username' => 'existinguser',
            'email' => 'another@example.com',
        ]));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_rejects_invalid_username_format(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData([
            'username' => 'invalid username',
        ]));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    public function test_registration_rejects_username_longer_than_50_characters(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData([
            'username' => str_repeat('a', 51),
        ]));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    public function test_registration_accepts_valid_alpha_dash_username(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData([
            'username' => 'john-doe_123',
        ]));

        $response
            ->assertCreated()
            ->assertJsonPath('data.user.username', 'john-doe_123');
    }

    public function test_registration_rejects_country_code_with_wrong_length(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData([
            'country_code' => 'CMR',
        ]));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['country_code']);
    }

    public function test_registration_rejects_country_code_shorter_than_two_characters(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData([
            'country_code' => 'C',
        ]));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['country_code']);
    }

    public function test_registration_accepts_two_character_country_code(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData([
            'country_code' => 'CM',
        ]));

        $response
            ->assertCreated()
            ->assertJsonPath('data.user.country_code', 'CM');
    }

    public function test_registration_rejects_phone_longer_than_20_characters(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData([
            'phone' => str_repeat('1', 21),
        ]));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_registration_accepts_phone_of_20_characters(): void
    {
        Notification::fake();

        $phone = str_repeat('1', 20);

        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData([
            'phone' => $phone,
        ]));

        $response
            ->assertCreated()
            ->assertJsonPath('data.user.phone', $phone);
    }

    public function test_registration_does_not_store_plaintext_password(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', $this->validRegistrationData())->assertCreated();

        $user = User::where('email', 'testuser@example.com')->firstOrFail();

        $this->assertNotSame('Password123!', $user->password);
        $this->assertTrue(Hash::check('Password123!', $user->password));
    }

    public function test_registration_does_not_expose_sensitive_user_fields(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData());

        $response
            ->assertCreated()
            ->assertJsonMissing(['password'])
            ->assertJsonMissing(['remember_token'])
            ->assertJsonMissing(['is_active']);
    }

    public function test_failed_registration_does_not_create_user(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData([
            'email' => 'invalid-email',
        ]));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_failed_duplicate_registration_does_not_create_second_user(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $this->postJson('/api/v1/auth/register', $this->validRegistrationData([
            'email' => 'existing@example.com',
            'username' => 'anotheruser',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_does_not_accept_unexpected_fields_as_user_attributes(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->validRegistrationData([
            'is_active' => false,
            'email_verified_at' => now()->toISOString(),
            'remember_token' => 'attacker-token',
            'bio' => 'Unexpected field',
        ]));

        $response->assertCreated();

        $user = User::where('email', 'testuser@example.com')->firstOrFail();

        $this->assertTrue($user->is_active);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->remember_token);
        $this->assertNull($user->bio);
    }

    public function test_registration_normalizes_email_to_lowercase(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'TESTUSER@EXAMPLE.COM',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'testuser@example.com',
        ]);
    }

    public function test_registration_rejects_email_that_only_differs_by_case(): void
    {
        User::factory()->create([
            'email' => 'testuser@example.com',
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Another User',
            'email' => 'TESTUSER@EXAMPLE.COM',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_notification_service_failure_does_not_break_registration(): void
    {
        Http::fake([
            '*' => Http::response([], 500),
        ]);

        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Notification Failure User',
            'username' => 'notificationfailure',
            'email' => 'notification-failure@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'notification-failure@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'notification-failure@example.com',
        ]);
    }

    public function test_registration_emits_user_registered_event(): void
    {
        Http::fake();

        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Event User',
            'username' => 'eventuser',
            'email' => 'event@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated();

        Http::assertSent(function ($request) {
            return $request->url() === config('services.notification.url')
                && $request['eventType'] === 'USER_REGISTERED'
                && $request['source'] === 'household-service'
                && $request['data']['email'] === 'event@example.com'
                && $request['data']['name'] === 'Event User';
        });
    }

    public function test_registration_event_contains_correct_user_id(): void
    {
        Http::fake();

        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Event ID User',
            'email' => 'event-id@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated();

        $user = User::where('email', 'event-id@example.com')->firstOrFail();

        Http::assertSent(function ($request) use ($user) {
            return $request['eventType'] === 'USER_REGISTERED'
                && $request['userId'] === (string) $user->id
                && $request['data']['userId'] === $user->id;
        });
    }
}
