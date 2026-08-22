<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const RESET_ENDPOINT = '/api/v1/auth/password/reset';

    private const SUCCESS_MESSAGE = 'Password reset successfully.';

    private string $testIp;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Each test gets a unique IP address so that the global
         * throttle:api middleware does not share its rate-limit counter
         * between tests.
         */
        $this->testIp = '10.201.'
            .random_int(1, 254)
            .'.'
            .random_int(1, 254);

        RateLimiter::clear($this->testIp);
        RateLimiter::clear("api|{$this->testIp}");

        /*
         * Apply the test IP to every HTTP request made by this test,
         * including direct postJson() calls.
         */
        $this->withServerVariables([
            'REMOTE_ADDR' => $this->testIp,
        ]);
    }

    private function createResetToken(User $user): string
    {
        return Password::broker()->createToken($user);
    }

    private function resetPassword(string $token, string $email, string $password = 'NewPassword123!')
    {
        return $this->postJson(self::RESET_ENDPOINT, [
            'token' => $token,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'password' => 'OldPassword123!',
        ]);

        $token = $this->createResetToken($user);

        $response = $this->resetPassword($token, $user->email);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', self::SUCCESS_MESSAGE)
            ->assertJsonPath('data', null)
            ->assertJsonMissingPath('token')
            ->assertJsonMissingPath('user');
    }

    public function test_successful_reset_changes_password(): void
    {
        $user = User::factory()->create([
            'password' => 'OldPassword123!',
        ]);

        $token = $this->createResetToken($user);

        $this->resetPassword($token, $user->email, 'NewPassword123!')->assertOk();

        $user->refresh();

        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
    }

    public function test_old_password_no_longer_works_after_reset(): void
    {
        $user = User::factory()->create([
            'password' => 'OldPassword123!',
        ]);

        $token = $this->createResetToken($user);

        $this->resetPassword($token, $user->email, 'NewPassword123!')->assertOk();

        $this->assertFalse(Hash::check('OldPassword123!', $user->fresh()->password));
    }

    public function test_new_password_works_after_reset(): void
    {
        $user = User::factory()->create([
            'password' => 'OldPassword123!',
        ]);

        $token = $this->createResetToken($user);

        $this->resetPassword($token, $user->email, 'NewPassword123!')->assertOk();

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->resetPassword('invalid-reset-token', $user->email);

        $response
            ->assertBadRequest()
            ->assertJsonPath('message', 'Unable to reset password.');
    }

    public function test_random_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->resetPassword(str_repeat('a', 64), $user->email);

        $response
            ->assertBadRequest()
            ->assertJsonPath('message', 'Unable to reset password.');
    }

    public function test_token_for_another_user_is_rejected(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $token = $this->createResetToken($userA);

        $response = $this->resetPassword($token, $userB->email);

        $response
            ->assertBadRequest()
            ->assertJsonPath('message', 'Unable to reset password.');

        $this->assertTrue(Hash::check('password', $userB->fresh()->password));
    }

    public function test_token_cannot_be_used_with_another_email(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $token = $this->createResetToken($user);

        $response = $this->resetPassword($token, $otherUser->email);

        $response
            ->assertBadRequest()
            ->assertJsonPath('message', 'Unable to reset password.');
    }

    public function test_reset_token_is_single_use(): void
    {
        $user = User::factory()->create();

        $token = $this->createResetToken($user);

        $firstResponse = $this->resetPassword($token, $user->email, 'FirstNewPassword123!');

        $firstResponse->assertOk();

        $secondResponse = $this->resetPassword($token, $user->email, 'SecondNewPassword123!');

        $secondResponse
            ->assertBadRequest()
            ->assertJsonPath('message', 'Unable to reset password.');

        $this->assertTrue(Hash::check('FirstNewPassword123!', $user->fresh()->password));
    }

    public function test_missing_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson(self::RESET_ENDPOINT, [
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
    }

    public function test_empty_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson(self::RESET_ENDPOINT, [
            'token' => '',
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
    }

    public function test_missing_email_is_rejected(): void
    {
        $response = $this->postJson(self::RESET_ENDPOINT, [
            'token' => 'some-token',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_invalid_email_is_rejected(): void
    {
        $response = $this->postJson(self::RESET_ENDPOINT, [
            'token' => 'some-token',
            'email' => 'not-an-email',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_non_string_email_is_rejected(): void
    {
        $response = $this->postJson(self::RESET_ENDPOINT, [
            'token' => 'some-token',
            'email' => ['invalid'],
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_missing_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $token = $this->createResetToken($user);

        $response = $this->postJson(self::RESET_ENDPOINT, [
            'token' => $token,
            'email' => $user->email,
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_empty_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $token = $this->createResetToken($user);

        $response = $this->postJson(self::RESET_ENDPOINT, [
            'token' => $token,
            'email' => $user->email,
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_shorter_than_eight_characters_is_rejected(): void
    {
        $user = User::factory()->create();

        $token = $this->createResetToken($user);

        $response = $this->resetPassword($token, $user->email, '1234567');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_of_exactly_eight_characters_is_accepted(): void
    {
        $user = User::factory()->create();

        $token = $this->createResetToken($user);

        $response = $this->resetPassword($token, $user->email, '12345678');

        $response->assertOk();

        $this->assertTrue(Hash::check('12345678', $user->fresh()->password));
    }

    public function test_password_confirmation_is_required(): void
    {
        $user = User::factory()->create();

        $token = $this->createResetToken($user);

        $response = $this->postJson(self::RESET_ENDPOINT, [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123!',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_confirmation_must_match(): void
    {
        $user = User::factory()->create();

        $token = $this->createResetToken($user);

        $response = $this->postJson(self::RESET_ENDPOINT, [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'DifferentPassword123!',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password'])
            ->assertJsonPath('errors.password.0', 'Password confirmation does not match.');
    }

    public function test_reset_does_not_require_authentication(): void
    {
        $user = User::factory()->create();

        $token = $this->createResetToken($user);

        $this->resetPassword($token, $user->email)->assertOk();
    }

    public function test_valid_bearer_token_is_not_required(): void
    {
        $user = User::factory()->create();

        $token = $this->createResetToken($user);

        $this
            ->withHeaders([
                'Authorization' => 'Bearer invalid-token',
            ])
            ->resetPassword($token, $user->email)
            ->assertOk();
    }

    public function test_reset_does_not_authenticate_user(): void
    {
        $user = User::factory()->create();

        $token = $this->createResetToken($user);

        $this->resetPassword($token, $user->email)->assertOk();

        $this->assertGuest('api');
    }

    public function test_reset_does_not_change_email(): void
    {
        $user = User::factory()->create();

        $email = $user->email;

        $token = $this->createResetToken($user);

        $this->resetPassword($token, $user->email)->assertOk();

        $this->assertSame($email, $user->fresh()->email);
    }

    public function test_reset_does_not_verify_email(): void
    {
        $user = User::factory()->unverified()->create();

        $token = $this->createResetToken($user);

        $this->resetPassword($token, $user->email)->assertOk();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_reset_preserves_account_active_state(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $token = $this->createResetToken($user);

        $this->resetPassword($token, $user->email)->assertOk();

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_inactive_user_can_reset_password(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
        ]);

        $token = $this->createResetToken($user);

        $response = $this->resetPassword($token, $user->email);

        $response->assertOk();

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
    }

    public function test_unverified_user_can_reset_password(): void
    {
        $user = User::factory()->unverified()->create();

        $token = $this->createResetToken($user);

        $response = $this->resetPassword($token, $user->email);

        $response->assertOk();

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_successful_reset_emits_password_reset_event(): void
    {
        $user = User::factory()->create();

        Http::fake(function ($request) use ($user) {
            $payload = $request->data();

            $this->assertSame('PASSWORD_RESET', $payload['eventType']);
            $this->assertSame((string) $user->id, $payload['userId']);
            $this->assertSame('household-service', $payload['source']);
            $this->assertSame($user->id, $payload['data']['userId']);
            $this->assertSame($user->email, $payload['data']['email']);

            return Http::response([], 200);
        });

        config([
            'services.notification.url' => 'http://notification-service/api/events',
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->postJson(self::RESET_ENDPOINT, [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        Http::assertSentCount(1);
    }

    public function test_failed_reset_does_not_emit_password_reset_event(): void
    {
        $user = User::factory()->create();

        Http::fake();

        config([
            'services.notification.url' => 'http://notification-service/api/events',
        ]);

        $response = $this->postJson(self::RESET_ENDPOINT, [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertBadRequest();

        Http::assertNothingSent();
    }

    public function test_reset_response_does_not_expose_sensitive_data(): void
    {
        $user = User::factory()->create();

        $token = $this->createResetToken($user);

        $response = $this->resetPassword($token, $user->email);

        $response
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonMissingPath('token')
            ->assertJsonMissingPath('user')
            ->assertJsonMissingPath('password');
    }

    public function test_reset_accepts_unexpected_fields_without_exposing_them(): void
    {
        $user = User::factory()->create();

        $token = $this->createResetToken($user);

        $response = $this->postJson(self::RESET_ENDPOINT, [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
            'user_id' => $user->id,
            'is_admin' => true,
            'is_active' => false,
            'role' => 'admin',
        ]);

        $response->assertOk();

        $freshUser = $user->fresh();

        $this->assertTrue($freshUser->is_active);
        $this->assertSame($user->id, $freshUser->id);
    }

    public function test_reset_does_not_modify_other_users(): void
    {
        $user = User::factory()->create();

        $otherUser = User::factory()->create([
            'password' => 'OtherPassword123!',
        ]);

        $originalHash = $otherUser->password;

        $token = $this->createResetToken($user);

        $this->resetPassword($token, $user->email)->assertOk();

        $this->assertSame($originalHash, $otherUser->fresh()->password);
    }

    public function test_nonexistent_email_is_rejected_by_broker(): void
    {
        $response = $this->resetPassword('some-token', 'nonexistent@example.com');

        $response
            ->assertBadRequest()
            ->assertJsonPath('message', 'Unable to reset password.');
    }

    public function test_reset_route_is_public(): void
    {
        $route = collect(app('router')->getRoutes())
            ->first(fn ($route) => $route->uri() === 'api/v1/auth/password/reset'
                    && in_array('POST', $route->methods(), true));

        $this->assertNotNull($route);

        $this->assertNotContains('auth:api', $route->gatherMiddleware());
    }
}
