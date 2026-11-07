<?php

namespace Tests\Feature\Api;

use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\OrganizationMembership;
use App\Domain\Organizations\TenantContext;
use App\Domain\Suppliers\Models\OrganizationSupplier;
use App\Domain\Suppliers\Models\Supplier;
use App\Domain\Tenders\Models\Tender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create(['type' => 'company']);
        $this->user = User::factory()->create();
        $this->inOrganization(fn () => OrganizationMembership::factory()->create(['user_id' => $this->user->id, 'role' => 'operator']));
        $this->authenticateUser($this->user);
        $this->withHeader('X-Organization-ID', $this->organization->id);
    }

    protected function authenticateUser(User $user, array $abilities = ['rfq:read', 'rfq:write']): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($user->createToken('tests', $abilities)->plainTextToken);
    }

    protected function inOrganization(callable $callback): mixed
    {
        return app(TenantContext::class)->run($this->organization->id, $callback(...));
    }

    protected function tender(array $attributes = []): Tender
    {
        return $this->inOrganization(fn () => Tender::factory()->create([
            'type' => 'products', 'created_by' => $this->user->id, ...$attributes,
        ]));
    }

    protected function supplier(array $linkAttributes = []): Supplier
    {
        $supplier = Supplier::factory()->create(['type' => 'vendor']);
        $this->inOrganization(fn () => OrganizationSupplier::factory()->create(['supplier_id' => $supplier->id, ...$linkAttributes]));

        return $supplier;
    }

    protected function tenderPayload(array $attributes = []): array
    {
        return ['title' => 'Insumos de oficina', 'type' => 'products', 'deadline_at' => now()->addWeek()->toIso8601String(), ...$attributes];
    }

    protected function itemPayload(array $attributes = []): array
    {
        return ['description' => 'Papel reciclado', 'unit' => 'kg', 'quantity' => '1.250', 'attributes' => ['color' => 'blanco'], ...$attributes];
    }
}
