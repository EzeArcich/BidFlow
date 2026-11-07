<?php

namespace Tests\Feature;

use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\OrganizationMembership;
use App\Domain\Organizations\TenantContext;
use App\Domain\Shared\Enums\TenderStatus;
use App\Domain\Suppliers\Models\OrganizationSupplier;
use App\Domain\Suppliers\Models\Supplier;
use App\Domain\Tenders\Models\Tender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_queries_without_context_fail_closed(): void
    {
        $this->expectException(LogicException::class);
        Tender::count();
    }

    public function test_writes_without_context_fail_closed(): void
    {
        $this->expectException(LogicException::class);
        Tender::factory()->create();
    }

    public function test_queries_relations_and_route_binding_are_isolated(): void
    {
        $a = Organization::factory()->create();
        $b = Organization::factory()->create();
        $context = app(TenantContext::class);
        $tender = $context->run($a->id, fn () => Tender::factory()->hasItems(2)->create());
        $context->run($b->id, function () use ($a, $tender): void {
            Tender::factory()->create();
            $this->assertSame(1, Tender::count());
            $this->assertNull(Tender::find($tender->id));
            $this->assertNull((new Tender)->resolveRouteBinding($tender->id));
            $this->assertCount(0, $a->tenders);
            $this->assertCount(0, $tender->items);
        });
        $context->run($a->id, function () use ($tender): void {
            $this->assertSame(1, Tender::count());
            $this->assertCount(2, Tender::findOrFail($tender->id)->items);
        });
        $this->expectException(LogicException::class);
        $context->id();
    }

    public function test_context_is_restored_after_nested_exception(): void
    {
        $context = app(TenantContext::class);
        $context->run('first', function () use ($context): void {
            try {
                $context->run('second', fn () => throw new LogicException('test'));
            } catch (LogicException) {
                $this->assertSame('first', $context->id());
            }
        });
        $this->expectException(LogicException::class);
        $context->id();
    }

    public function test_cross_tenant_instance_cannot_be_saved_or_deleted(): void
    {
        $a = Organization::factory()->create();
        $b = Organization::factory()->create();
        $context = app(TenantContext::class);
        $tender = $context->run($a->id, fn () => Tender::factory()->create());
        foreach (['save', 'delete'] as $operation) {
            try {
                $context->run($b->id, fn () => $tender->$operation());
                $this->fail('Cross-tenant mutation must fail.');
            } catch (LogicException) {
                $this->assertTrue($tender->exists);
            }
        }
    }

    public static function roles(): array
    {
        return [['owner', true], ['admin', true], ['operator', true], ['viewer', false]];
    }

    #[DataProvider('roles')]
    public function test_tender_permissions_require_membership_and_draft(string $role, bool $canEdit): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        app(TenantContext::class)->run($org->id, function () use ($user, $role, $canEdit): void {
            OrganizationMembership::factory()->create(['user_id' => $user->id, 'role' => $role]);
            $tender = Tender::factory()->create();
            $gate = Gate::forUser($user);
            $this->assertTrue($gate->allows('view', $tender));
            $this->assertSame($canEdit, $gate->allows('create', Tender::class));
            $this->assertSame($canEdit, $gate->allows('update', $tender));
            $tender->status = TenderStatus::Published;
            $this->assertFalse($gate->allows('update', $tender));
            $this->assertFalse($gate->allows('delete', $tender));
            $this->assertFalse(Gate::forUser(User::factory()->create())->allows('view', $tender));
        });
    }

    public function test_supplier_visibility_and_policies_require_organization_link(): void
    {
        $org = Organization::factory()->create();
        $other = Organization::factory()->create();
        $supplier = Supplier::factory()->create();
        $user = User::factory()->create();
        app(TenantContext::class)->run($org->id, function () use ($user, $supplier): void {
            OrganizationMembership::factory()->create(['user_id' => $user->id]);
            OrganizationSupplier::factory()->create(['supplier_id' => $supplier->id]);
            $this->assertSame(1, Supplier::visibleToCurrentOrganization()->count());
            $this->assertTrue(Gate::forUser($user)->allows('view', $supplier));
            $this->assertFalse(Gate::forUser($user)->allows('update', $supplier));
        });
        app(TenantContext::class)->run($other->id, function () use ($user, $supplier): void {
            OrganizationMembership::factory()->create(['user_id' => $user->id]);
            $this->assertSame(0, Supplier::visibleToCurrentOrganization()->count());
            $this->assertFalse(Gate::forUser($user)->allows('view', $supplier));
        });
    }

    public function test_membership_in_other_organization_does_not_grant_tender_access(): void
    {
        $a = Organization::factory()->create();
        $b = Organization::factory()->create();
        $user = User::factory()->create();
        $context = app(TenantContext::class);
        $tender = $context->run($a->id, function () use ($user) {
            OrganizationMembership::factory()->create(['user_id' => $user->id, 'role' => 'owner']);

            return Tender::factory()->create();
        });
        $context->run($b->id, function () use ($user, $tender): void {
            OrganizationMembership::factory()->create(['user_id' => $user->id, 'role' => 'owner']);
            $this->assertFalse(Gate::forUser($user)->allows('view', $tender));
        });
    }
}
