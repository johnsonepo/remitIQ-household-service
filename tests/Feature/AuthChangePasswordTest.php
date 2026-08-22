<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class AuthChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    private string $currentPassword = 'CurrentPassword123';

    private string $newPassword = 'NewPassword123';

    private function createUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'password' => $this->currentPassword,
            'is_active' => true,
        ], $attributes));
    }

    private function authenticate(User $user): string
    {
        return JWTAuth::fromUser($user);
    }

    private function changePassword(string $token, array $payload = [])
    {
        return $this->withHeader('Authorization', 'Bearer '.$token)->putJson('/api/v1/auth/profile/password', array_merge([
            'current_password' => $this->currentPassword,
            'password' => $this->newPassword,
            'password_confirmation' => $this->newPassword,
        ], $payload));
    }

    public function test_authenticated_user_can_change_password(): void
    {
        $user = $this->createUser();
        $token = $this->authenticate($user);

        $response = $this->changePassword($token);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', 'Password changed successfully. Please log in again.');
    }

    public function test_password_is_changed_in_database(): void
    {
        $user = $this->createUser();
        $token = $this->authenticate($user);

        $this->changePassword($token);

        $user->refresh();

        $this->assertTrue(Hash::check($this->newPassword, $user->password));
    }

    public function test_password_is_stored_hashed(): void
    {
        $user = $this->createUser();
        $token = $this->authenticate($user);

        $this->changePassword($token);

        $user->refresh();

        $this->assertNotSame($this->newPassword, $user->password);

        $this->assertTrue(Hash::check($this->newPassword, $user->password));
    }

    public function test_old_password_no_longer_works(): void
    {
        $user = $this->createUser();
        $token = $this->authenticate($user);

        $this->changePassword($token);

        $this->assertFalse(Hash::check($this->currentPassword, $user->fresh()->password));
    }

    public function test_new_password_can_authenticate_user(): void
    {
        $user = $this->createUser();
        $token = $this->authenticate($user);

        $this->changePassword($token);

        $newToken = JWTAuth::attempt([
            'email' => $user->email,
            'password' => $this->newPassword,
        ]);

        $this->assertIsString($newToken);
        $this->assertNotEmpty($newToken);
    }

    public function test_old_password_cannot_authenticate_user(): void
    {
        $user = $this->createUser();
        $token = $this->authenticate($user);

        $this->changePassword($token);

        $oldToken = JWTAuth::attempt([
            'email' => $user->email,
            'password' => $this->currentPassword,
        ]);

        $this->assertFalse($oldToken);
    }

    public function test_current_token_is_invalidated_after_password_change(): void
    {
        $user = $this->createUser();
        $token = $this->authenticate($user);

        $response = $this->changePassword($token);

        $response->assertOk();

        $meResponse = $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/auth/me');

        $meResponse->assertUnauthorized();
    }

    public function test_change_password_requires_authentication(): void
    {
        $response = $this->putJson('/api/v1/auth/profile/password', [
            'current_password' => $this->currentPassword,
            'password' => $this->newPassword,
            'password_confirmation' => $this->newPassword,
        ]);

        $response->assertUnauthorized();
    }

    public function test_malformed_bearer_token_is_rejected(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer malformed-token')->putJson('/api/v1/auth/profile/password', [
            'current_password' => $this->currentPassword,
            'password' => $this->newPassword,
            'password_confirmation' => $this->newPassword,
        ]);

        $response->assertUnauthorized();
    }

    public function test_invalid_bearer_token_is_rejected(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.str_repeat('a', 100))->putJson('/api/v1/auth/profile/password', [
            'current_password' => $this->currentPassword,
            'password' => $this->newPassword,
            'password_confirmation' => $this->newPassword,
        ]);

        $response->assertUnauthorized();
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = $this->createUser();
        $token = $this->authenticate($user);

        $response = $this->changePassword($token, [
            'current_password' => 'WrongPassword123',
        ]);

        $response
            ->assertBadRequest()
            ->assertJsonPath('message', 'Current password is incorrect.');
    }

    public function test_wrong_current_password_does_not_change_password(): void
    {
        $user = $this->createUser();
        $token = $this->authenticate($user);

        $this->changePassword($token, [
            'current_password' => 'WrongPassword123',
        ]);

        $this->assertTrue(Hash::check($this->currentPassword, $user->fresh()->password));
    }

    public function test_missing_current_password_is_rejected(): void
    {
        $user = $this->createUser();
        $token = $this->authenticate($user);

        $response = $this->changePassword($token, ['current_password' => null]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_empty_current_password_is_rejected(): void
    {
        $user = $this->createUser();
        $token = $this->authenticate($user);

        $response = $this->changePassword($token, ['current_password' => '']);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_current_password_must_be_string(): void
    {
        $user = $this->createUser();
        $token = $this->authenticate($user);

        $response = $this->changePassword($token, ['current_password' => ['invalid']]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_missing_new_password_is_rejected(): void
    {
        $user = $this->createUser();
        $token = $this->authenticate($user);

        $response = $this->changePassword($token, ['password' => null, 'password_confirmation' => null]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_empty_new_password_is_rejected(): void
    {
        $user = $this->createUser();
        $token = $this->authenticate($user);

        $response = $this->changePassword($token, [
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_new_password_must_be_string(): void
    {
        $user = $this->createUser();
        $token = $this->authenticate($user);

        $response = $this->changePassword($token, ['password' => ['invalid']]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_shorter_than_eight_characters_is_rejected(): void
    {
        $user = $this->createUser();
        $token = $this->authenticate($user);

        $response = $this->changePassword($token, [
            'password' => '1234567',
            'password_confirmation' => '1234567',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_of_exactly_eight_characters_is_accepted(): void
    {
        $user = $this->createUser();
        $token = $this->authenticate($user);

        $response = $this->changePassword($token, [
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ]);

        $response->assertOk();

        $this->assertTrue(Hash::check('12345678', $user->fresh()->password));
    }

    public function test_password_confirmation_is_required(): void
    {
        $user = $this->createUser();
        $token = $this->authenticate($user);

        $response = $this->changePassword($token, [
            'password_confirmation' => null,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_confirmation_must_match(): void
    {
        $user = $this->createUser();
        $token = $this->authenticate($user);

        $response = $this->changePassword($token, [
            'password_confirmation' => 'DifferentPassword123',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_new_password_must_differ_from_current_password(): void
    {
        $user = $this->createUser();
        $token = $this->authenticate($user);

        $response = $this->changePassword($token, [
            'password' => $this->currentPassword,
            'password_confirmation' => $this->currentPassword,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_same_password_does_not_modify_database(): void
    {
        $user = $this->createUser();
        $originalHash = $user->password;
        $token = $this->authenticate($user);

        $this->changePassword($token, [
            'password' => $this->currentPassword,
            'password_confirmation' => $this->currentPassword,
        ]);

        $this->assertSame($originalHash, $user->fresh()->password);
    }

    public function test_change_password_does_not_change_email(): void
    {
        $user = $this->createUser();
        $email = $user->email;
        $token = $this->authenticate($user);

        $this->changePassword($token);

        $this->assertSame($email, $user->fresh()->email);
    }

    public function test_change_password_does_not_change_email_verification(): void
    {
        $user = $this->createUser([
            'email_verified_at' => now(),
        ]);

        $token = $this->authenticate($user);

        $this->changePassword($token);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_unverified_user_can_change_password(): void
    {
        $user = $this->createUser([
            'email_verified_at' => null,
        ]);

        $token = $this->authenticate($user);

        $this->changePassword($token)
            ->assertOk();

        $this->assertTrue(Hash::check($this->newPassword, $user->fresh()->password));
    }

    public function test_inactive_user_cannot_change_password(): void
    {
        $user = $this->createUser([
            'is_active' => false,
        ]);

        $token = $this->authenticate($user);

        $response = $this->changePassword($token);

        $response->assertUnauthorized();
    }

    public function test_inactive_user_password_is_not_changed(): void
    {
        $user = $this->createUser([
            'is_active' => false,
        ]);

        $token = $this->authenticate($user);

        $this->changePassword($token);

        $this->assertTrue(Hash::check($this->currentPassword, $user->fresh()->password));
    }

    public function test_change_password_does_not_modify_another_user(): void
    {
        $user = $this->createUser();
        $otherUser = $this->createUser([
            'email' => 'other@example.com',
        ]);

        $otherPasswordHash = $otherUser->password;

        $token = $this->authenticate($user);

        $this->changePassword($token);

        $this->assertSame($otherPasswordHash, $otherUser->fresh()->password);
    }

    public function test_unexpected_fields_are_ignored(): void
    {
        $user = $this->createUser();
        $token = $this->authenticate($user);

        $this->changePassword($token, [
            'is_active' => false,
            'email' => 'attacker@example.com',
            'name' => 'Attacker',
        ])->assertOk();

        $user->refresh();

        $this->assertTrue($user->is_active);
        $this->assertNotSame('attacker@example.com', $user->email);
        $this->assertNotSame('Attacker', $user->name);
        $this->assertTrue(Hash::check($this->newPassword, $user->password));
    }

    public function test_successful_change_emits_password_changed_event(): void
    {
        $user = $this->createUser();

        Http::fake(function ($request) use ($user) {
            $payload = $request->data();

            $this->assertSame('PASSWORD_CHANGED', $payload['eventType']);
            $this->assertSame((string) $user->id, $payload['userId']);
            $this->assertSame('household-service', $payload['source']);
            $this->assertSame($user->id, $payload['data']['userId']);
            $this->assertSame($user->email, $payload['data']['email']);

            return Http::response([], 200);
        });

        config([
            'services.notification.url' => 'http://notification-service/api/events',
        ]);

        $token = $this->authenticate($user);

        $this->changePassword($token)
            ->assertOk();

        Http::assertSentCount(1);
    }

    public function test_failed_change_does_not_emit_password_changed_event(): void
    {
        $user = $this->createUser();

        Http::fake();

        config([
            'services.notification.url' => 'http://notification-service/api/events',
        ]);

        $token = $this->authenticate($user);

        $this->changePassword($token, [
            'current_password' => 'WrongPassword123',
        ])->assertBadRequest();

        Http::assertNothingSent();
    }

    public function test_notification_failure_does_not_break_password_change(): void
    {
        $user = $this->createUser();

        Http::fake([
            '*' => Http::response([], 500),
        ]);

        config([
            'services.notification.url' => 'http://notification-service/api/events',
        ]);

        $token = $this->authenticate($user);

        $response = $this->changePassword($token);

        $response->assertOk();

        $this->assertTrue(Hash::check($this->newPassword, $user->fresh()->password));
    }

    public function test_successful_response_does_not_expose_sensitive_data(): void
    {
        $user = $this->createUser();
        $token = $this->authenticate($user);

        $response = $this->changePassword($token);

        $response
            ->assertOk()
            ->assertJsonMissing(['password'])
            ->assertJsonMissing(['current_password'])
            ->assertJsonMissing(['password_confirmation'])
            ->assertJsonMissing(['token']);
    }

    public function test_password_change_response_contains_no_user_data(): void
    {
        $user = $this->createUser();
        $token = $this->authenticate($user);

        $response = $this->changePassword($token);

        $response
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_password_change_does_not_authenticate_user_again(): void
    {
        $user = $this->createUser();
        $token = $this->authenticate($user);

        $this->changePassword($token)
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_password_change_preserves_account_state(): void
    {
        $user = $this->createUser([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $token = $this->authenticate($user);

        $this->changePassword($token)
            ->assertOk();

        $user->refresh();

        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_password_change_route_is_protected(): void
    {
        $response = $this->putJson('/api/v1/auth/profile/password', [
            'current_password' => $this->currentPassword,
            'password' => $this->newPassword,
            'password_confirmation' => $this->newPassword,
        ]);

        $response->assertUnauthorized();
    }
}
