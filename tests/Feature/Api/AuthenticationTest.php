<?php

namespace Tests\Feature\Api;

use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\OrganizationMembership;
use App\Domain\Organizations\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;

class AuthenticationTest extends ApiTestCase
{
    public static function protectedEndpoints(): array
    {
        $id = '01M6S46AMG6NY6YXMJV6FADVK1';

        return array_map(fn ($route) => $route, [
            ['GET', '/auth/me'], ['POST', '/auth/logout'],
            ['GET', '/tenders'], ['POST', '/tenders'], ['GET', "/tenders/{$id}"], ['PATCH', "/tenders/{$id}"],
            ['GET', "/tenders/{$id}/items"], ['POST', "/tenders/{$id}/items"], ['PATCH', "/tenders/{$id}/items/{$id}"], ['DELETE', "/tenders/{$id}/items/{$id}"],
            ['GET', '/suppliers'], ['GET', "/tenders/{$id}/invitations"], ['POST', "/tenders/{$id}/invitations"], ['POST', "/tenders/{$id}/publish"],
        ]);
    }

    #[DataProvider('protectedEndpoints')]
    public function test_all_operational_endpoints_require_authentication(string $method, string $path): void
    {
        $this->withoutHeader('Authorization');
        $this->app['auth']->forgetGuards();
        $this->json($method, '/api/v1'.$path)->assertUnauthorized();
    }

    public function test_login_me_and_logout_use_real_hashed_expiring_sanctum_tokens(): void
    {
        $this->user->update(['password' => Hash::make('local-api-password')]);
        $response = $this->postJson('/api/v1/auth/login', ['email' => $this->user->email, 'password' => 'local-api-password', 'device_name' => 'Bruno'])
            ->assertOk()->assertJsonPath('token_type', 'Bearer')->assertJsonPath('data.id', $this->user->id)
            ->assertJsonMissingPath('data.password');
        $token = $response->json('access_token');
        $stored = PersonalAccessToken::findToken($token);
        $this->assertNotSame($token, $stored->token);
        $this->assertTrue($stored->expires_at->isFuture());
        $this->assertSame($this->user->id, $stored->tokenable_id);
        $this->withToken($token);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.organizations.0.id', $this->organization->id)->assertJsonPath('data.organizations.0.role', 'operator');
        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->assertNull(PersonalAccessToken::findToken($token));
    }

    public function test_bad_credentials_and_login_validation(): void
    {
        $this->postJson('/api/v1/auth/login', [])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password', 'device_name']);
        $this->postJson('/api/v1/auth/login', ['email' => $this->user->email, 'password' => 'wrong', 'device_name' => 'tests'])->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['email' => 'unknown@example.test', 'password' => 'wrong', 'device_name' => 'tests'])->assertUnauthorized();
    }

    public function test_login_is_rate_limited(): void
    {
        $payload = ['email' => 'rate-limit@example.test', 'password' => 'wrong', 'device_name' => 'tests'];
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', $payload)->assertUnauthorized();
        }
        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(429);
    }

    public function test_expired_and_read_only_tokens(): void
    {
        $token = $this->user->createToken('expired', ['*'], now()->subMinute());
        $this->withToken($token->plainTextToken);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/tenders')->assertUnauthorized();
        $this->authenticateUser($this->user, ['rfq:read']);
        $this->getJson('/api/v1/tenders')->assertOk();
        $this->postJson('/api/v1/tenders', $this->tenderPayload())->assertForbidden();
    }

    public function test_user_without_membership_can_identify_but_cannot_access_tenant_api(): void
    {
        $outsider = User::factory()->create();
        $this->authenticateUser($outsider);
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonCount(0, 'data.organizations');
        $this->getJson('/api/v1/tenders')->assertForbidden();
        $this->withoutHeader('X-Organization-ID')->getJson('/api/v1/suppliers')->assertForbidden();
    }

    public function test_organization_selection_requires_membership_and_handles_ambiguity(): void
    {
        $this->withoutHeader('X-Organization-ID')->getJson('/api/v1/tenders')->assertOk();
        $other = Organization::factory()->create();
        $this->withHeader('X-Organization-ID', $other->id)->getJson('/api/v1/tenders')->assertForbidden();
        app(TenantContext::class)->run($other->id, fn () => OrganizationMembership::factory()->create(['user_id' => $this->user->id]));
        $this->withoutHeader('X-Organization-ID')->getJson('/api/v1/tenders')->assertUnprocessable()->assertJsonValidationErrors('organization');
        $this->withHeader('X-Organization-ID', $other->id)->getJson('/api/v1/tenders')->assertOk();
        $this->withHeader('X-Organization-ID', 'invalid')->getJson('/api/v1/tenders')->assertUnprocessable();
        $other->update(['status' => 'inactive']);
        $this->withHeader('X-Organization-ID', $other->id)->getJson('/api/v1/tenders')->assertForbidden();
        $this->assertSame(0, DB::table('tenders')->count());
        $this->expectException(LogicException::class);
        app(TenantContext::class)->id();
    }
}
