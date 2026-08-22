<?php

namespace Tests\Feature\Household;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class HouseholdTest extends TestCase
{
    use RefreshDatabase;

    private const NOTIFICATION_URL = 'http://notification-service.test/api/v1/events';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.notification.url' => self::NOTIFICATION_URL,
            'services.notification.api_key' => 'test-api-key',
            'services.notification.timeout' => 5,
        ]);

        Http::fake();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function tokenFor(User $user): string
    {
        return JWTAuth::fromUser($user);
    }

    private function authenticatedRequest(User $user): static
    {
        return $this->withToken($this->tokenFor($user));
    }

    private function validHouseholdData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Johnson Family',
            'base_currency_code' => 'XAF',
            'timezone' => 'Africa/Douala',
        ], $overrides);
    }

    private function createHousehold(User $owner, array $overrides = []): Household
    {
        return Household::factory()->create(array_merge([
            'owner_id' => $owner->id,
        ], $overrides));
    }

    private function assertSuccessResponse($response, string $message, int $status = 200): void
    {
        $response
            ->assertStatus($status)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', $message)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta',
                'timestamp',
            ]);

        $this->assertNotEmpty($response->json('timestamp'));
    }

    private function assertErrorResponse($response, int $status): void
    {
        $response
            ->assertStatus($status)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
                'meta',
                'timestamp',
            ]);
    }

    private function assertNotificationEvent(string $eventType, string $userId, array $data): void
    {
        Http::assertSent(function ($request) use ($eventType, $userId, $data): bool {
            $payload = $request->data();

            return $request->url() === self::NOTIFICATION_URL
                && $request->method() === 'POST'
                && ($request->header('X-Service-API-Key')[0] ?? null) === 'test-api-key'
                && ($payload['eventType'] ?? null) === $eventType
                && ($payload['userId'] ?? null) === $userId
                && ($payload['source'] ?? null) === 'household-service'
                && isset($payload['eventId'])
                && is_string($payload['eventId'])
                && $payload['eventId'] !== ''
                && isset($payload['timestamp'])
                && is_string($payload['timestamp'])
                && $payload['timestamp'] !== ''
                && ($payload['data'] ?? null) === $data;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/households');

        $this->assertErrorResponse($response, 401);
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/households', $this->validHouseholdData());

        $this->assertErrorResponse($response, 401);
    }

    public function test_show_requires_authentication(): void
    {
        $owner = User::factory()->create();
        $household = $this->createHousehold($owner);

        $response = $this->getJson('/api/v1/households/'.$household->id);

        $this->assertErrorResponse($response, 401);
    }

    public function test_update_requires_authentication(): void
    {
        $owner = User::factory()->create();
        $household = $this->createHousehold($owner);

        $response = $this->patchJson('/api/v1/households/'.$household->id, ['name' => 'Updated Family']);

        $this->assertErrorResponse($response, 401);
    }

    public function test_delete_requires_authentication(): void
    {
        $owner = User::factory()->create();
        $household = $this->createHousehold($owner);

        $response = $this->deleteJson('/api/v1/households/'.$household->id);

        $this->assertErrorResponse($response, 401);
    }

    public function test_invalid_jwt_is_rejected(): void
    {
        $response = $this
            ->withToken('this-is-not-a-valid-jwt')
            ->getJson('/api/v1/households');

        $this->assertErrorResponse($response, 401);
    }

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function test_owner_can_list_owned_households(): void
    {
        $owner = User::factory()->create();

        $householdOne = $this->createHousehold($owner, ['name' => 'First Family']);
        $householdTwo = $this->createHousehold($owner, ['name' => 'Second Family']);

        $response = $this
            ->authenticatedRequest($owner)
            ->getJson('/api/v1/households');

        $this->assertSuccessResponse($response, 'Households retrieved successfully.');

        $response->assertJsonCount(2, 'data');

        $this->assertEqualsCanonicalizing([$householdOne->id, $householdTwo->id], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_member_can_list_households_they_belong_to(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $household = $this->createHousehold($owner);

        HouseholdMember::factory()->create([
            'household_id' => $household->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);

        $response = $this
            ->authenticatedRequest($member)
            ->getJson('/api/v1/households');

        $this->assertSuccessResponse($response, 'Households retrieved successfully.');

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $household->id);
    }

    public function test_user_cannot_list_households_they_do_not_have_access_to(): void
    {
        $owner = User::factory()->create();
        $unrelatedUser = User::factory()->create();

        $household = $this->createHousehold($owner);

        $response = $this
            ->authenticatedRequest($unrelatedUser)
            ->getJson('/api/v1/households');

        $this->assertSuccessResponse($response, 'Households retrieved successfully.');

        $response->assertJsonCount(0, 'data');

        $this->assertNotContains($household->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_index_returns_only_accessible_households(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $otherOwner = User::factory()->create();

        $owned = $this->createHousehold($member, ['name' => 'Owned']);
        $joined = $this->createHousehold($owner, ['name' => 'Joined']);
        $hidden = $this->createHousehold($otherOwner, ['name' => 'Hidden']);

        HouseholdMember::factory()->create([
            'household_id' => $joined->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);

        $response = $this
            ->authenticatedRequest($member)
            ->getJson('/api/v1/households');

        $this->assertSuccessResponse($response, 'Households retrieved successfully.');

        $this->assertEqualsCanonicalizing([$owned->id, $joined->id], collect($response->json('data'))->pluck('id')->all());

        $this->assertNotContains($hidden->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_soft_deleted_households_are_not_returned_by_index(): void
    {
        $owner = User::factory()->create();

        $active = $this->createHousehold($owner);
        $deleted = $this->createHousehold($owner);

        $deleted->delete();

        $response = $this
            ->authenticatedRequest($owner)
            ->getJson('/api/v1/households');

        $this->assertSuccessResponse($response, 'Households retrieved successfully.');

        $this->assertEquals([$active->id], collect($response->json('data'))->pluck('id')->all());
        $this->assertNotContains($deleted->id, collect($response->json('data'))->pluck('id')->all());
    }

    /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */

    public function test_authenticated_user_can_create_household(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->authenticatedRequest($owner)
            ->postJson('/api/v1/households', $this->validHouseholdData());

        $this->assertSuccessResponse($response, 'Household created successfully.', 201);

        $householdId = $response->json('data.id');

        $this->assertIsString($householdId);
        $this->assertNotEmpty($householdId);

        $this->assertDatabaseHas('households', [
            'id' => $householdId,
            'name' => 'Johnson Family',
            'owner_id' => $owner->id,
            'base_currency_code' => 'XAF',
            'timezone' => 'Africa/Douala',
        ]);
    }

    public function test_household_creation_assigns_authenticated_user_as_owner(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $response = $this
            ->authenticatedRequest($owner)
            ->postJson('/api/v1/households', $this->validHouseholdData([
                'owner_id' => $attacker->id,
            ]));

        $response->assertCreated();

        $householdId = $response->json('data.id');

        $this->assertDatabaseHas('households', [
            'id' => $householdId,
            'owner_id' => $owner->id,
        ]);

        $this->assertDatabaseMissing('households', [
            'id' => $householdId,
            'owner_id' => $attacker->id,
        ]);
    }

    public function test_household_creation_generates_uuid(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->authenticatedRequest($owner)
            ->postJson('/api/v1/households', $this->validHouseholdData());

        $response->assertCreated();

        $id = $response->json('data.id');

        $this->assertIsString($id);
        $this->assertMatchesRegularExpression('/^[0-9a-fA-F-]{36}$/', $id);
    }

    public function test_household_creation_uses_database_defaults(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->authenticatedRequest($owner)
            ->postJson('/api/v1/households', [
                'name' => 'Default Family',
            ]);

        $response->assertCreated();

        $householdId = $response->json('data.id');

        $this->assertDatabaseHas('households', [
            'id' => $householdId,
            'owner_id' => $owner->id,
            'base_currency_code' => 'XAF',
            'timezone' => 'UTC',
        ]);
    }

    public function test_household_creation_accepts_custom_currency_and_timezone(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->authenticatedRequest($owner)
            ->postJson('/api/v1/households', [
                'name' => 'Custom Family',
                'base_currency_code' => 'NGN',
                'timezone' => 'Africa/Lagos',
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('households', [
            'id' => $response->json('data.id'),
            'base_currency_code' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);
    }

    public function test_household_creation_returns_expected_resource_shape(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->authenticatedRequest($owner)
            ->postJson('/api/v1/households', $this->validHouseholdData());

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'owner_id',
                    'base_currency_code',
                    'timezone',
                    'created_at',
                    'updated_at',
                ],
                'meta',
                'timestamp',
            ])
            ->assertJsonPath('data.owner_id', $owner->id);
    }

    /*
    |--------------------------------------------------------------------------
    | Create Validation
    |--------------------------------------------------------------------------
    */

    public function test_household_creation_requires_name(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->authenticatedRequest($owner)
            ->postJson('/api/v1/households', []);

        $this->assertErrorResponse($response, 422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_household_name_cannot_be_empty(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->authenticatedRequest($owner)
            ->postJson('/api/v1/households', [
                'name' => '',
            ]);

        $this->assertErrorResponse($response, 422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_household_name_cannot_exceed_255_characters(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->authenticatedRequest($owner)
            ->postJson('/api/v1/households', [
                'name' => str_repeat('A', 256),
            ]);

        $this->assertErrorResponse($response, 422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_household_name_accepts_exactly_255_characters(): void
    {
        $owner = User::factory()->create();

        $name = str_repeat('A', 255);

        $response = $this
            ->authenticatedRequest($owner)
            ->postJson('/api/v1/households', [
                'name' => $name,
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('households', [
            'id' => $response->json('data.id'),
            'name' => $name,
        ]);
    }

    public function test_currency_must_be_exactly_three_letters(): void
    {
        $owner = User::factory()->create();

        foreach ([
            'XA',
            'XAFC',
            '123',
            'X@F',
        ] as $currency) {
            $response = $this
                ->authenticatedRequest($owner)
                ->postJson('/api/v1/households', [
                    'name' => 'Test Family',
                    'base_currency_code' => $currency,
                ]);

            $this->assertErrorResponse($response, 422);
            $response->assertJsonValidationErrors(['base_currency_code']);
        }
    }

    public function test_currency_must_be_alphabetic(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->authenticatedRequest($owner)
            ->postJson('/api/v1/households', [
                'name' => 'Test Family',
                'base_currency_code' => '12F',
            ]);

        $this->assertErrorResponse($response, 422);
        $response->assertJsonValidationErrors(['base_currency_code']);
    }

    public function test_valid_three_letter_currency_is_accepted(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->authenticatedRequest($owner)
            ->postJson('/api/v1/households', [
                'name' => 'Test Family',
                'base_currency_code' => 'GHS',
            ]);

        $response->assertCreated();
    }

    public function test_timezone_must_be_valid(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->authenticatedRequest($owner)
            ->postJson('/api/v1/households', [
                'name' => 'Test Family',
                'timezone' => 'Not/A/Timezone',
            ]);

        $this->assertErrorResponse($response, 422);
        $response->assertJsonValidationErrors(['timezone']);
    }

    public function test_timezone_cannot_exceed_50_characters(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->authenticatedRequest($owner)
            ->postJson('/api/v1/households', [
                'name' => 'Test Family',
                'timezone' => str_repeat('A', 51),
            ]);

        $this->assertErrorResponse($response, 422);
        $response->assertJsonValidationErrors(['timezone']);
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function test_owner_can_view_household(): void
    {
        $owner = User::factory()->create();
        $household = $this->createHousehold($owner);

        $response = $this
            ->authenticatedRequest($owner)
            ->getJson('/api/v1/households/'.$household->id);

        $this->assertSuccessResponse($response, 'Household retrieved successfully.');

        $response
            ->assertJsonPath('data.id', $household->id)
            ->assertJsonPath('data.owner_id', $owner->id)
            ->assertJsonStructure([
                'data' => [
                    'owner',
                    'members',
                ],
            ]);
    }

    public function test_member_can_view_household(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $household = $this->createHousehold($owner);

        HouseholdMember::factory()->create([
            'household_id' => $household->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);

        $response = $this
            ->authenticatedRequest($member)
            ->getJson('/api/v1/households/'.$household->id);

        $this->assertSuccessResponse($response, 'Household retrieved successfully.');

        $response->assertJsonPath('data.id', $household->id);
    }

    public function test_unrelated_user_cannot_view_household(): void
    {
        $owner = User::factory()->create();
        $unrelatedUser = User::factory()->create();

        $household = $this->createHousehold($owner);

        $response = $this
            ->authenticatedRequest($unrelatedUser)
            ->getJson('/api/v1/households/'.$household->id);

        $this->assertErrorResponse($response, 403);
    }

    public function test_show_unknown_household_returns_not_found(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->authenticatedRequest($user)
            ->getJson('/api/v1/households/00000000-0000-0000-0000-000000000000');

        $this->assertErrorResponse($response, 404);
    }

    public function test_show_soft_deleted_household_returns_not_found(): void
    {
        $owner = User::factory()->create();
        $household = $this->createHousehold($owner);

        $household->delete();

        $response = $this
            ->authenticatedRequest($owner)
            ->getJson('/api/v1/households/'.$household->id);

        $this->assertErrorResponse($response, 404);
    }

    public function test_show_does_not_expose_passwords_or_remember_tokens(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $household = $this->createHousehold($owner);

        HouseholdMember::factory()->create([
            'household_id' => $household->id,
            'user_id' => $member->id,
        ]);

        $response = $this
            ->authenticatedRequest($owner)
            ->getJson('/api/v1/households/'.$household->id);

        $response
            ->assertOk()
            ->assertJsonMissingPath('data.owner.password')
            ->assertJsonMissingPath('data.owner.remember_token')
            ->assertJsonMissingPath('data.members.0.password')
            ->assertJsonMissingPath('data.members.0.remember_token');
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function test_owner_can_update_household(): void
    {
        $owner = User::factory()->create();
        $household = $this->createHousehold($owner);

        $response = $this
            ->authenticatedRequest($owner)
            ->patchJson('/api/v1/households/'.$household->id, [
                'name' => 'Updated Family',
                'base_currency_code' => 'NGN',
                'timezone' => 'Africa/Lagos',
            ]);

        $this->assertSuccessResponse($response, 'Household updated successfully.');

        $response
            ->assertJsonPath('data.id', $household->id)
            ->assertJsonPath('data.name', 'Updated Family')
            ->assertJsonPath('data.base_currency_code', 'NGN')
            ->assertJsonPath('data.timezone', 'Africa/Lagos');

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'name' => 'Updated Family',
            'base_currency_code' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);
    }

    public function test_member_cannot_update_household(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $household = $this->createHousehold($owner, [
            'name' => 'Original Name',
        ]);

        HouseholdMember::factory()->create([
            'household_id' => $household->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);

        $response = $this
            ->authenticatedRequest($member)
            ->patchJson('/api/v1/households/'.$household->id, [
                'name' => 'Attacker Updated Name',
            ]);

        $this->assertErrorResponse($response, 403);

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'name' => 'Original Name',
        ]);
    }

    public function test_admin_cannot_update_household(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();

        $household = $this->createHousehold($owner, [
            'name' => 'Original Name',
        ]);

        HouseholdMember::factory()->create([
            'household_id' => $household->id,
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);

        $response = $this
            ->authenticatedRequest($admin)
            ->patchJson('/api/v1/households/'.$household->id, [
                'name' => 'Admin Update',
            ]);

        $this->assertErrorResponse($response, 403);

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'name' => 'Original Name',
        ]);
    }

    public function test_unrelated_user_cannot_update_household(): void
    {
        $owner = User::factory()->create();
        $unrelatedUser = User::factory()->create();

        $household = $this->createHousehold($owner, [
            'name' => 'Original Name',
        ]);

        $response = $this
            ->authenticatedRequest($unrelatedUser)
            ->patchJson('/api/v1/households/'.$household->id, [
                'name' => 'Unauthorized Update',
            ]);

        $this->assertErrorResponse($response, 403);

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'name' => 'Original Name',
        ]);
    }

    public function test_update_supports_partial_updates(): void
    {
        $owner = User::factory()->create();

        $household = $this->createHousehold($owner, [
            'name' => 'Original',
            'base_currency_code' => 'XAF',
            'timezone' => 'Africa/Douala',
        ]);

        $response = $this
            ->authenticatedRequest($owner)
            ->patchJson('/api/v1/households/'.$household->id, [
                'name' => 'Updated',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'name' => 'Updated',
            'base_currency_code' => 'XAF',
            'timezone' => 'Africa/Douala',
        ]);
    }

    public function test_update_cannot_change_owner(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $household = $this->createHousehold($owner);

        $response = $this
            ->authenticatedRequest($owner)
            ->patchJson('/api/v1/households/'.$household->id, [
                'name' => 'Updated',
                'owner_id' => $attacker->id,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'owner_id' => $owner->id,
            'name' => 'Updated',
        ]);

        $this->assertDatabaseMissing('households', [
            'id' => $household->id,
            'owner_id' => $attacker->id,
        ]);
    }

    public function test_update_ignores_unvalidated_fields(): void
    {
        $owner = User::factory()->create();

        $household = $this->createHousehold($owner);

        $response = $this
            ->authenticatedRequest($owner)
            ->patchJson('/api/v1/households/'.$household->id, [
                'name' => 'Updated Name',
                'is_admin' => true,
                'secret' => 'attacker-value',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'name' => 'Updated Name',
        ]);

        $household->refresh();

        $this->assertSame('Updated Name', $household->name);
        $this->assertFalse(array_key_exists('secret', $household->getAttributes()));
        $this->assertFalse(array_key_exists('is_admin', $household->getAttributes()));
    }

    public function test_update_with_invalid_currency_is_rejected(): void
    {
        $owner = User::factory()->create();
        $household = $this->createHousehold($owner);

        $response = $this
            ->authenticatedRequest($owner)
            ->patchJson('/api/v1/households/'.$household->id, [
                'base_currency_code' => 'INVALID',
            ]);

        $this->assertErrorResponse($response, 422);
        $response->assertJsonValidationErrors(['base_currency_code']);
    }

    public function test_update_with_invalid_timezone_is_rejected(): void
    {
        $owner = User::factory()->create();
        $household = $this->createHousehold($owner);

        $response = $this
            ->authenticatedRequest($owner)
            ->patchJson('/api/v1/households/'.$household->id, [
                'timezone' => 'Invalid/Timezone',
            ]);

        $this->assertErrorResponse($response, 422);
        $response->assertJsonValidationErrors(['timezone']);
    }

    public function test_update_unknown_household_returns_not_found(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->authenticatedRequest($owner)
            ->patchJson('/api/v1/households/00000000-0000-0000-0000-000000000000', ['name' => 'Updated']);

        $this->assertErrorResponse($response, 404);
    }

    public function test_update_soft_deleted_household_returns_not_found(): void
    {
        $owner = User::factory()->create();
        $household = $this->createHousehold($owner);

        $household->delete();

        $response = $this
            ->authenticatedRequest($owner)
            ->patchJson('/api/v1/households/'.$household->id, [
                'name' => 'Updated',
            ]);

        $this->assertErrorResponse($response, 404);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    public function test_owner_can_delete_household(): void
    {
        $owner = User::factory()->create();
        $household = $this->createHousehold($owner);

        $response = $this
            ->authenticatedRequest($owner)
            ->deleteJson('/api/v1/households/'.$household->id);

        $this->assertSuccessResponse($response, 'Household deleted successfully.');

        $this->assertSoftDeleted('households', [
            'id' => $household->id,
        ]);
    }

    public function test_member_cannot_delete_household(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $household = $this->createHousehold($owner);

        HouseholdMember::factory()->create([
            'household_id' => $household->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);

        $response = $this
            ->authenticatedRequest($member)
            ->deleteJson('/api/v1/households/'.$household->id);

        $this->assertErrorResponse($response, 403);

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'deleted_at' => null,
        ]);
    }

    public function test_admin_cannot_delete_household(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();

        $household = $this->createHousehold($owner);

        HouseholdMember::factory()->create([
            'household_id' => $household->id,
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);

        $response = $this
            ->authenticatedRequest($admin)
            ->deleteJson('/api/v1/households/'.$household->id);

        $this->assertErrorResponse($response, 403);

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'deleted_at' => null,
        ]);
    }

    public function test_unrelated_user_cannot_delete_household(): void
    {
        $owner = User::factory()->create();
        $unrelatedUser = User::factory()->create();

        $household = $this->createHousehold($owner);

        $response = $this
            ->authenticatedRequest($unrelatedUser)
            ->deleteJson('/api/v1/households/'.$household->id);

        $this->assertErrorResponse($response, 403);

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'deleted_at' => null,
        ]);
    }

    public function test_delete_is_soft_delete_not_hard_delete(): void
    {
        $owner = User::factory()->create();
        $household = $this->createHousehold($owner);

        $this
            ->authenticatedRequest($owner)
            ->deleteJson('/api/v1/households/'.$household->id)
            ->assertOk();

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
        ]);

        $this->assertNotNull(Household::withTrashed()->findOrFail($household->id)->deleted_at);
    }

    public function test_deleted_household_is_no_longer_accessible_through_normal_model_queries(): void
    {
        $owner = User::factory()->create();
        $household = $this->createHousehold($owner);

        $this
            ->authenticatedRequest($owner)
            ->deleteJson('/api/v1/households/'.$household->id)
            ->assertOk();

        $this->assertNull(Household::query()->find($household->id));

        $this->assertNotNull(Household::withTrashed()->find($household->id));
    }

    public function test_delete_unknown_household_returns_not_found(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->authenticatedRequest($owner)
            ->deleteJson('/api/v1/households/00000000-0000-0000-0000-000000000000');

        $this->assertErrorResponse($response, 404);
    }

    /*
    |--------------------------------------------------------------------------
    | IDOR / Authorization Boundaries
    |--------------------------------------------------------------------------
    */

    public function test_user_cannot_view_another_users_household_by_uuid(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $household = $this->createHousehold($owner);

        $response = $this
            ->authenticatedRequest($attacker)
            ->getJson('/api/v1/households/'.$household->id);

        $this->assertErrorResponse($response, 403);
    }

    public function test_user_cannot_modify_another_users_household_by_uuid(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $household = $this->createHousehold($owner, [
            'name' => 'Protected Family',
        ]);

        $response = $this
            ->authenticatedRequest($attacker)
            ->patchJson('/api/v1/households/'.$household->id, [
                'name' => 'Compromised Family',
            ]);

        $this->assertErrorResponse($response, 403);

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'name' => 'Protected Family',
        ]);
    }

    public function test_user_cannot_delete_another_users_household_by_uuid(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $household = $this->createHousehold($owner);

        $response = $this
            ->authenticatedRequest($attacker)
            ->deleteJson('/api/v1/households/'.$household->id);

        $this->assertErrorResponse($response, 403);

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'deleted_at' => null,
        ]);
    }

    public function test_membership_in_one_household_does_not_grant_access_to_another(): void
    {
        $ownerOne = User::factory()->create();
        $ownerTwo = User::factory()->create();
        $member = User::factory()->create();

        $householdOne = $this->createHousehold($ownerOne);
        $householdTwo = $this->createHousehold($ownerTwo);

        HouseholdMember::factory()->create([
            'household_id' => $householdOne->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);

        $response = $this
            ->authenticatedRequest($member)
            ->getJson('/api/v1/households/'.$householdTwo->id);

        $this->assertErrorResponse($response, 403);
    }

    public function test_member_of_one_household_cannot_update_another_household(): void
    {
        $ownerOne = User::factory()->create();
        $ownerTwo = User::factory()->create();
        $member = User::factory()->create();

        $householdOne = $this->createHousehold($ownerOne);
        $householdTwo = $this->createHousehold($ownerTwo, [
            'name' => 'Protected Family',
        ]);

        HouseholdMember::factory()->create([
            'household_id' => $householdOne->id,
            'user_id' => $member->id,
            'role' => 'admin',
        ]);

        $response = $this
            ->authenticatedRequest($member)
            ->patchJson('/api/v1/households/'.$householdTwo->id, [
                'name' => 'Unauthorized',
            ]);

        $this->assertErrorResponse($response, 403);

        $this->assertDatabaseHas('households', [
            'id' => $householdTwo->id,
            'name' => 'Protected Family',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Relationship Integrity
    |--------------------------------------------------------------------------
    */

    public function test_household_owner_relationship_is_correct(): void
    {
        $owner = User::factory()->create();
        $household = $this->createHousehold($owner);

        $this->assertTrue($household->owner->is($owner));
        $this->assertSame($owner->id, $household->owner_id);
    }

    public function test_household_members_relationship_is_correct(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $household = $this->createHousehold($owner);

        HouseholdMember::factory()->create([
            'household_id' => $household->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);

        $household->load('members');

        $this->assertTrue($household->members->contains(fn (User $user): bool => $user->id === $member->id));
    }

    public function test_household_has_unique_uuid(): void
    {
        $owner = User::factory()->create();

        $first = $this->createHousehold($owner);
        $second = $this->createHousehold($owner);

        $this->assertNotSame($first->id, $second->id);
    }

    /*
    |--------------------------------------------------------------------------
    | Notification Events
    |--------------------------------------------------------------------------
    */

    public function test_household_creation_emits_notification_event(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->authenticatedRequest($owner)
            ->postJson('/api/v1/households', [
                'name' => 'Notification Family',
                'base_currency_code' => 'XAF',
                'timezone' => 'Africa/Douala',
            ]);

        $response->assertCreated();

        $householdId = $response->json('data.id');

        $this->assertNotificationEvent('HOUSEHOLD_CREATED', (string) $owner->id, [
            'householdId' => $householdId,
            'ownerId' => $owner->id,
            'name' => 'Notification Family',
        ]);
    }

    public function test_household_update_emits_notification_event(): void
    {
        $owner = User::factory()->create();

        $household = $this->createHousehold($owner, [
            'name' => 'Original Family',
        ]);

        $response = $this
            ->authenticatedRequest($owner)
            ->patchJson('/api/v1/households/'.$household->id, [
                'name' => 'Updated Family',
            ]);

        $response->assertOk();

        $this->assertNotificationEvent('HOUSEHOLD_UPDATED', (string) $owner->id, [
            'householdId' => $household->id,
            'ownerId' => $owner->id,
            'changes' => [
                'name' => 'Updated Family',
            ],
        ]);
    }

    public function test_household_deletion_emits_notification_event(): void
    {
        $owner = User::factory()->create();

        $household = $this->createHousehold($owner);

        $response = $this
            ->authenticatedRequest($owner)
            ->deleteJson('/api/v1/households/'.$household->id);

        $response->assertOk();

        $this->assertNotificationEvent('HOUSEHOLD_DELETED', (string) $owner->id, [
            'householdId' => $household->id,
            'ownerId' => $owner->id,
        ]);
    }

    public function test_notification_service_failure_does_not_break_household_creation(): void
    {
        Http::fake([
            self::NOTIFICATION_URL => Http::response(null, 500),
        ]);

        $owner = User::factory()->create();

        $response = $this
            ->authenticatedRequest($owner)
            ->postJson('/api/v1/households', $this->validHouseholdData());

        $response->assertCreated();

        $this->assertDatabaseHas('households', [
            'id' => $response->json('data.id'),
            'owner_id' => $owner->id,
        ]);
    }

    public function test_notification_service_failure_does_not_break_household_update(): void
    {
        Http::fake([
            self::NOTIFICATION_URL => Http::response(null, 500),
        ]);

        $owner = User::factory()->create();

        $household = $this->createHousehold($owner);

        $response = $this
            ->authenticatedRequest($owner)
            ->patchJson('/api/v1/households/'.$household->id, [
                'name' => 'Updated Family',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'name' => 'Updated Family',
        ]);
    }

    public function test_notification_service_failure_does_not_break_household_deletion(): void
    {
        Http::fake([
            self::NOTIFICATION_URL => Http::response(null, 500),
        ]);

        $owner = User::factory()->create();

        $household = $this->createHousehold($owner);

        $response = $this
            ->authenticatedRequest($owner)
            ->deleteJson('/api/v1/households/'.$household->id);

        $response->assertOk();

        $this->assertSoftDeleted('households', [
            'id' => $household->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Response / Data Exposure
    |--------------------------------------------------------------------------
    */

    public function test_index_returns_standard_api_response_metadata(): void
    {
        $owner = User::factory()->create();
        $this->createHousehold($owner);

        $response = $this
            ->authenticatedRequest($owner)
            ->getJson('/api/v1/households');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Households retrieved successfully.')
            ->assertJsonStructure([
                'data',
                'meta',
                'timestamp',
            ]);

        $this->assertIsArray($response->json('data'));
    }

    public function test_create_does_not_return_sensitive_user_information(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->authenticatedRequest($owner)
            ->postJson('/api/v1/households', $this->validHouseholdData());

        $response
            ->assertCreated()
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');
    }

    public function test_household_update_does_not_change_unrelated_database_fields(): void
    {
        $owner = User::factory()->create();

        $household = $this->createHousehold($owner, [
            'base_currency_code' => 'XAF',
            'timezone' => 'Africa/Douala',
        ]);

        $originalOwnerId = $household->owner_id;

        $this
            ->authenticatedRequest($owner)
            ->patchJson('/api/v1/households/'.$household->id, [
                'name' => 'Only Name Changed',
            ])
            ->assertOk();

        $household->refresh();

        $this->assertSame($originalOwnerId, $household->owner_id);
        $this->assertSame('XAF', $household->base_currency_code);
        $this->assertSame('Africa/Douala', $household->timezone);
        $this->assertSame('Only Name Changed', $household->name);
    }
}
