<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class AuthEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function verificationHash(User $user): string
    {
        return sha1($user->getEmailForVerification());
    }

    public function test_user_can_verify_email_with_valid_id_and_hash(): void
    {
        Event::fake([Verified::class]);

        $user = User::factory()->unverified()->create();

        $response = $this->postJson('/api/v1/auth/email/verify', [
            'id' => $user->id,
            'hash' => $this->verificationHash($user),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Email verified successfully.');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        Event::assertDispatched(Verified::class, fn (Verified $event) => $event->user->is($user));
    }

    public function test_verification_requires_id(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->postJson('/api/v1/auth/email/verify', [
            'hash' => $this->verificationHash($user),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['id']);
    }

    public function test_verification_requires_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->postJson('/api/v1/auth/email/verify', [
            'id' => $user->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['hash']);
    }

    public function test_verification_rejects_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->postJson('/api/v1/auth/email/verify', [
            'id' => $user->id,
            'hash' => str_repeat('a', 40),
        ]);

        $response
            ->assertBadRequest()
            ->assertJsonPath('message', 'Invalid verification link.');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_rejects_hash_for_another_user(): void
    {
        $userA = User::factory()->unverified()->create();
        $userB = User::factory()->unverified()->create();

        $response = $this->postJson('/api/v1/auth/email/verify', [
            'id' => $userA->id,
            'hash' => $this->verificationHash($userB),
        ]);

        $response
            ->assertBadRequest()
            ->assertJsonPath('message', 'Invalid verification link.');

        $this->assertFalse($userA->fresh()->hasVerifiedEmail());
        $this->assertFalse($userB->fresh()->hasVerifiedEmail());
    }

    public function test_verification_rejects_nonexistent_user(): void
    {
        $response = $this->postJson('/api/v1/auth/email/verify', [
            'id' => 999999,
            'hash' => str_repeat('a', 40),
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id']);
    }

    public function test_verification_rejects_already_verified_email(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/email/verify', [
            'id' => $user->id,
            'hash' => $this->verificationHash($user),
        ]);

        $response
            ->assertStatus(409)
            ->assertJsonPath('message', 'Email already verified.');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verification_does_not_change_email(): void
    {
        $user = User::factory()->unverified()->create();
        $email = $user->email;

        $this->postJson('/api/v1/auth/email/verify', [
            'id' => $user->id,
            'hash' => $this->verificationHash($user),
        ])->assertOk();

        $this->assertSame($email, $user->fresh()->email);
    }

    public function test_verification_does_not_require_authentication(): void
    {
        $user = User::factory()->unverified()->create();

        $this->postJson('/api/v1/auth/email/verify', [
            'id' => $user->id,
            'hash' => $this->verificationHash($user),
        ])->assertOk();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_response_does_not_expose_user_data(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->postJson('/api/v1/auth/email/verify', [
            'id' => $user->id,
            'hash' => $this->verificationHash($user),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonMissingPath('token')
            ->assertJsonMissingPath('user');
    }

    public function test_verification_is_idempotently_rejected_after_first_success(): void
    {
        $user = User::factory()->unverified()->create();

        $payload = [
            'id' => $user->id,
            'hash' => $this->verificationHash($user),
        ];

        $this->postJson('/api/v1/auth/email/verify', $payload)
            ->assertOk();

        $this->postJson('/api/v1/auth/email/verify', $payload)
            ->assertStatus(409)
            ->assertJsonPath('message', 'Email already verified.');
    }

    public function test_authenticated_unverified_user_can_resend_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $token = $this->loginToken($user);

        $response = $this
            ->withHeaders($this->authHeaders($token))
            ->postJson('/api/v1/auth/email/resend');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Verification email sent.');

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_resend_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/email/resend')
            ->assertUnauthorized();
    }

    public function test_resend_rejects_malformed_token(): void
    {
        $this
            ->withHeaders([
                'Authorization' => 'Bearer malformed-token',
            ])
            ->postJson('/api/v1/auth/email/resend')
            ->assertUnauthorized();
    }

    public function test_resend_rejects_invalid_token(): void
    {
        $this
            ->withHeaders([
                'Authorization' => 'Bearer invalid-token',
            ])
            ->postJson('/api/v1/auth/email/resend')
            ->assertUnauthorized();
    }

    public function test_verified_user_cannot_resend_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $token = $this->loginToken($user);

        $response = $this
            ->withHeaders($this->authHeaders($token))
            ->postJson('/api/v1/auth/email/resend');

        $response
            ->assertStatus(409)
            ->assertJsonPath('message', 'Email already verified.');

        Notification::assertNothingSent();
    }

    public function test_inactive_user_cannot_resend_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create([
            'is_active' => false,
        ]);

        $token = $this->loginToken($user);

        $response = $this
            ->withHeaders($this->authHeaders($token))
            ->postJson('/api/v1/auth/email/resend');

        $response->assertUnauthorized();

        Notification::assertNothingSent();
    }

    public function test_resend_does_not_verify_email(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $token = $this->loginToken($user);

        $this
            ->withHeaders($this->authHeaders($token))
            ->postJson('/api/v1/auth/email/resend')
            ->assertOk();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_resend_does_not_expose_sensitive_user_data(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $token = $this->loginToken($user);

        $response = $this
            ->withHeaders($this->authHeaders($token))
            ->postJson('/api/v1/auth/email/resend');

        $response
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonMissingPath('token')
            ->assertJsonMissingPath('user');
    }

    public function test_verification_preserves_user_account_state(): void
    {
        $user = User::factory()->unverified()->create([
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/auth/email/verify', [
            'id' => $user->id,
            'hash' => $this->verificationHash($user),
        ])->assertOk();

        $freshUser = $user->fresh();

        $this->assertTrue($freshUser->is_active);
        $this->assertSame($user->email, $freshUser->email);
        $this->assertSame($user->name, $freshUser->name);
        $this->assertSame($user->username, $freshUser->username);
    }

    private function loginToken(User $user): string
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
}
