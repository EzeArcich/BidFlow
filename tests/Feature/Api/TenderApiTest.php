<?php

namespace Tests\Feature\Api;

use App\Domain\Integrations\Models\AuditLog;
use App\Domain\Integrations\Models\OutboxEvent;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\OrganizationMembership;
use App\Domain\Organizations\TenantContext;
use App\Domain\Suppliers\Models\OrganizationSupplier;
use App\Domain\Suppliers\Models\SupplierContact;
use App\Domain\Tenders\Models\RepairScope;
use App\Domain\Tenders\Models\Tender;
use App\Domain\Tenders\Models\TenderInvitation;
use App\Domain\Tenders\Models\TenderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;

class TenderApiTest extends ApiTestCase
{
    public function test_complete_create_items_select_invite_publish_flow(): void
    {
        Mail::fake();
        Queue::fake();
        $supplier = $this->supplier();
        $contact = SupplierContact::factory()->create(['supplier_id' => $supplier->id]);
        $id = $this->postJson('/api/v1/tenders', $this->tenderPayload(['conditions' => ['delivery' => 'En oficina']]))
            ->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.currency', 'ARS')
            ->assertJsonPath('data.organization_id', $this->organization->id)->json('data.id');
        $this->postJson("/api/v1/tenders/{$id}/items", $this->itemPayload())->assertCreated()->assertJsonPath('data.quantity', '1.250')->assertJsonPath('data.attributes.color', 'blanco');
        $this->getJson('/api/v1/suppliers?q='.urlencode($supplier->trade_name))->assertOk()->assertJsonPath('data.0.id', $supplier->id)->assertJsonPath('data.0.contacts.0.id', $contact->id);
        $this->postJson("/api/v1/tenders/{$id}/invitations", ['invitations' => [['supplier_id' => $supplier->id, 'contact_id' => $contact->id]]])->assertOk()->assertJsonPath('data.0.status', 'pending')->assertJsonPath('data.0.sent_at', null);
        $this->getJson("/api/v1/tenders/{$id}/invitations")->assertOk()->assertJsonCount(1, 'data');
        $this->postJson("/api/v1/tenders/{$id}/publish")->assertOk()->assertJsonPath('data.status', 'published');
        $this->getJson("/api/v1/tenders/{$id}")->assertOk()->assertJsonCount(1, 'data.items')->assertJsonCount(1, 'data.invitations');
        $this->assertDatabaseHas('tenders', ['id' => $id, 'organization_id' => $this->organization->id, 'created_by' => $this->user->id, 'type' => 'products', 'status' => 'published']);
        $this->assertDatabaseHas('tender_status_histories', ['tender_id' => $id, 'from_status' => 'draft', 'to_status' => 'published', 'actor_id' => $this->user->id]);
        $this->assertDatabaseHas('audit_logs', ['entity_id' => $id, 'action' => 'tender.published']);
        $this->assertDatabaseHas('outbox_events', ['resource_id' => $id, 'event_type' => 'tender.published', 'processed_at' => null]);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Queue::assertNothingPushed();
    }

    public static function types(): array
    {
        return [['products'], ['services'], ['works']];
    }

    #[DataProvider('types')]
    public function test_generic_types_create_without_sector_specific_details(string $type): void
    {
        $this->postJson('/api/v1/tenders', $this->tenderPayload(['type' => $type]))->assertCreated()->assertJsonPath('data.type', $type);
        $this->assertDatabaseCount('repair_scopes', 0);
    }

    public function test_list_detail_update_and_decimal_item_edit_delete(): void
    {
        $tender = $this->tender();
        $url = "/api/v1/tenders/{$tender->id}";
        $this->patchJson($url, ['title' => 'Servicio de limpieza', 'type' => 'services'])->assertOk()->assertJsonPath('data.title', 'Servicio de limpieza');
        $this->getJson('/api/v1/tenders?q=limpieza&status=draft&type=services&per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.per_page', 1);
        $item = $this->postJson($url.'/items', $this->itemPayload())->assertCreated()->json('data.id');
        $this->getJson($url.'/items')->assertOk()->assertJsonCount(1, 'data');
        $this->patchJson($url.'/items/'.$item, ['quantity' => '0.125', 'attributes' => ['quality' => 'A']])->assertOk()->assertJsonPath('data.quantity', '0.125');
        $this->assertDatabaseHas('tender_items', ['id' => $item, 'quantity' => '0.125']);
        $this->deleteJson($url.'/items/'.$item)->assertNoContent();
        $this->getJson($url.'/items')->assertOk()->assertJsonCount(0, 'data');
        $this->deleteJson($url.'/items/'.$item)->assertNotFound();
    }

    public function test_cross_tenant_rfqs_and_nested_items_are_hidden(): void
    {
        $other = Organization::factory()->create();
        $foreignTender = app(TenantContext::class)->run($other->id, fn () => Tender::factory()->hasItems(1)->create());
        $url = "/api/v1/tenders/{$foreignTender->id}";
        $this->getJson('/api/v1/tenders')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($url)->assertNotFound();
        $this->patchJson($url, ['title' => 'forged'])->assertNotFound();
        $this->getJson($url.'/items')->assertNotFound();
        $this->postJson($url.'/items', $this->itemPayload())->assertNotFound();
        $this->getJson($url.'/invitations')->assertNotFound();
        $this->postJson($url.'/invitations', ['invitations' => []])->assertNotFound();
        $this->postJson($url.'/publish')->assertNotFound();
        $own = $this->tender();
        $otherOwn = $this->tender();
        $item = $this->inOrganization(fn () => TenderItem::factory()->create(['tender_id' => $otherOwn->id]));
        $this->patchJson("/api/v1/tenders/{$own->id}/items/{$item->id}", ['notes' => 'wrong parent'])->assertNotFound();
        $this->deleteJson("/api/v1/tenders/{$own->id}/items/{$item->id}")->assertNotFound();
    }

    public static function roles(): array
    {
        return [['owner', true], ['admin', true], ['operator', true], ['viewer', false]];
    }

    #[DataProvider('roles')]
    public function test_roles_on_every_mutating_endpoint(string $role, bool $allowed): void
    {
        $this->inOrganization(fn () => OrganizationMembership::where('user_id', $this->user->id)->firstOrFail()->update(['role' => $role]));
        $tender = $this->tender();
        $url = "/api/v1/tenders/{$tender->id}";
        $item = $this->inOrganization(fn () => TenderItem::factory()->create(['tender_id' => $tender->id]));
        $supplier = $this->supplier();
        foreach (['/tenders', "/tenders/{$tender->id}", "/tenders/{$tender->id}/items", "/tenders/{$tender->id}/invitations", '/suppliers'] as $path) {
            $this->getJson('/api/v1'.$path)->assertOk();
        }
        $this->postJson('/api/v1/tenders', $this->tenderPayload())->assertStatus($allowed ? 201 : 403);
        $this->patchJson($url, ['title' => 'Cambio'])->assertStatus($allowed ? 200 : 403);
        $this->postJson($url.'/items', $this->itemPayload())->assertStatus($allowed ? 201 : 403);
        $this->patchJson($url.'/items/'.$item->id, ['notes' => 'Cambio'])->assertStatus($allowed ? 200 : 403);
        $this->deleteJson($url.'/items/'.$item->id)->assertStatus($allowed ? 204 : 403);
        $this->postJson($url.'/invitations', ['invitations' => [['supplier_id' => $supplier->id]]])->assertStatus($allowed ? 200 : 403);
        $this->postJson($url.'/publish')->assertStatus($allowed ? 200 : 403);
    }

    public static function invalidTenderData(): array
    {
        return [
            [['title' => ''], 'title'], [['type' => 'vehicles'], 'type'], [['type' => 'parts'], 'type'],
            [['currency' => 'USD'], 'currency'], [['deadline_at' => '2020-01-01T00:00:00Z'], 'deadline_at'],
            [['deadline_at' => 'tomorrow'], 'deadline_at'], [['organization_id' => null], 'organization_id'],
            [['status' => 'published'], 'status'], [['created_by' => 'forged'], 'created_by'],
            [['conditions' => 'text'], 'conditions'], [['repair_scope' => ['vehicle' => []]], 'repair_scope'],
        ];
    }

    #[DataProvider('invalidTenderData')]
    public function test_invalid_and_protected_tender_fields_are_rejected(array $attributes, string $field): void
    {
        $this->postJson('/api/v1/tenders', $this->tenderPayload($attributes))->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('tenders', 0);
    }

    public static function invalidQuantity(): array
    {
        return [['0'], ['-1'], ['0.0001'], ['1000000000'], ['1e3'], ['1,5'], [1.25], [null]];
    }

    #[DataProvider('invalidQuantity')]
    public function test_invalid_item_quantities_are_rejected(mixed $quantity): void
    {
        $tender = $this->tender();
        $this->postJson("/api/v1/tenders/{$tender->id}/items", $this->itemPayload(['quantity' => $quantity]))->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertDatabaseCount('tender_items', 0);
    }

    public static function nonDraftStates(): array
    {
        return array_map(fn ($status) => [$status], ['published', 'closed', 'awarded', 'completed', 'cancelled', 'expired']);
    }

    #[DataProvider('nonDraftStates')]
    public function test_all_non_draft_states_reject_writes_and_publication(string $status): void
    {
        $tender = $this->tender(['status' => $status]);
        $item = $this->inOrganization(fn () => TenderItem::factory()->create(['tender_id' => $tender->id]));
        $supplier = $this->supplier();
        $url = "/api/v1/tenders/{$tender->id}";
        $this->patchJson($url, ['title' => 'Change'])->assertConflict();
        $this->postJson($url.'/items', $this->itemPayload())->assertConflict();
        $this->patchJson($url.'/items/'.$item->id, ['notes' => 'Change'])->assertConflict();
        $this->deleteJson($url.'/items/'.$item->id)->assertConflict();
        $this->postJson($url.'/invitations', ['invitations' => [['supplier_id' => $supplier->id]]])->assertConflict();
        $this->postJson($url.'/publish')->assertConflict();
        $this->assertDatabaseHas('tenders', ['id' => $tender->id, 'status' => $status]);
        $this->assertDatabaseCount('outbox_events', 0);
    }

    public function test_publication_preconditions_and_no_partial_side_effects(): void
    {
        $tender = $this->tender();
        $url = "/api/v1/tenders/{$tender->id}";
        $this->postJson($url.'/publish')->assertUnprocessable()->assertJsonValidationErrors(['items', 'invitations']);
        $this->postJson($url.'/items', $this->itemPayload())->assertCreated();
        $this->postJson($url.'/publish')->assertUnprocessable()->assertJsonValidationErrors('invitations');
        $supplier = $this->supplier();
        $this->postJson($url.'/invitations', ['invitations' => [['supplier_id' => $supplier->id]]])->assertOk();
        $this->inOrganization(fn () => OrganizationSupplier::where('supplier_id', $supplier->id)->firstOrFail()->update(['status' => 'inactive']));
        $this->postJson($url.'/publish')->assertUnprocessable()->assertJsonValidationErrors('invitations');
        $this->assertDatabaseHas('tenders', ['id' => $tender->id, 'status' => 'draft']);
        $this->assertSame(0, DB::table('tender_status_histories')->where('to_status', 'published')->count());
        $this->assertDatabaseCount('outbox_events', 0);
    }

    public function test_deadline_offsets_are_normalized_to_utc(): void
    {
        $deadline = now()->addWeek()->setTimezone('America/Argentina/Buenos_Aires')->startOfHour();
        $id = $this->postJson('/api/v1/tenders', $this->tenderPayload(['deadline_at' => $deadline->toIso8601String()]))->assertCreated()->json('data.id');
        $this->inOrganization(function () use ($id, $deadline): void {
            $this->assertTrue(Tender::findOrFail($id)->deadline_at->equalTo($deadline));
            $this->assertSame('UTC', DB::selectOne('SHOW timezone')->TimeZone);
        });
    }

    public function test_deadline_validation_and_existing_invitation_deadline_updates(): void
    {
        $tender = $this->tender();
        $supplier = $this->supplier();
        $url = "/api/v1/tenders/{$tender->id}";
        $this->postJson($url.'/items', $this->itemPayload())->assertCreated();
        $this->postJson($url.'/invitations', ['invitations' => [['supplier_id' => $supplier->id]]])->assertOk();
        $newDeadline = now()->addDays(10)->toIso8601String();
        $this->patchJson($url, ['deadline_at' => $newDeadline])->assertOk();
        $this->inOrganization(function () use ($tender): void {
            $this->assertTrue(TenderInvitation::firstOrFail()->expires_at->equalTo(Tender::findOrFail($tender->id)->deadline_at));
        });
        $this->travel(11)->days();
        $this->postJson($url.'/publish')->assertUnprocessable()->assertJsonValidationErrors(['deadline_at', 'invitations']);
    }

    public function test_legacy_draft_can_be_edited_without_removing_dependent_demo_data(): void
    {
        $tender = $this->tender(['type' => 'repair']);
        $this->inOrganization(fn () => RepairScope::factory()->create(['tender_id' => $tender->id]));
        $url = "/api/v1/tenders/{$tender->id}";
        $this->patchJson($url, ['title' => 'Legacy compatible'])->assertOk();
        $this->patchJson($url, ['type' => 'services'])->assertConflict();
        $this->assertDatabaseHas('tenders', ['id' => $tender->id, 'type' => 'repair', 'title' => 'Legacy compatible']);
        $this->assertDatabaseCount('repair_scopes', 1);
    }

    public function test_publication_rolls_back_when_outbox_persistence_fails(): void
    {
        $tender = $this->tender();
        $supplier = $this->supplier();
        $url = "/api/v1/tenders/{$tender->id}";
        $this->postJson($url.'/items', $this->itemPayload())->assertCreated();
        $this->postJson($url.'/invitations', ['invitations' => [['supplier_id' => $supplier->id]]])->assertOk();
        OutboxEvent::creating(fn () => throw new \RuntimeException('Simulated outbox failure'));
        $this->withoutExceptionHandling();
        try {
            $this->postJson($url.'/publish');
            $this->fail('Expected outbox failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated outbox failure', $exception->getMessage());
        }
        $this->assertDatabaseHas('tenders', ['id' => $tender->id, 'status' => 'draft']);
        $this->assertSame(0, DB::table('tender_status_histories')->where('to_status', 'published')->count());
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'tender.published')->count());
        $this->assertDatabaseCount('outbox_events', 0);
    }

    public function test_duplicate_publication_creates_one_event_and_one_history_record(): void
    {
        $tender = $this->tender();
        $supplier = $this->supplier();
        $url = "/api/v1/tenders/{$tender->id}";
        $this->postJson($url.'/items', $this->itemPayload())->assertCreated();
        $this->postJson($url.'/invitations', ['invitations' => [['supplier_id' => $supplier->id]]])->assertOk();
        $this->postJson($url.'/publish')->assertOk();
        $this->postJson($url.'/publish')->assertConflict();
        $this->assertDatabaseCount('outbox_events', 1);
        $this->assertSame(1, DB::table('tender_status_histories')->where('to_status', 'published')->count());
        $this->inOrganization(fn () => $this->assertSame(1, AuditLog::where('action', 'tender.published')->count()));
    }

    public function test_item_validation_and_position_conflicts_are_controlled(): void
    {
        $tender = $this->tender();
        $url = "/api/v1/tenders/{$tender->id}/items";
        $this->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors(['description', 'quantity', 'unit']);
        $this->postJson($url, $this->itemPayload(['position' => 1]))->assertCreated();
        $this->postJson($url, $this->itemPayload(['position' => 1]))->assertUnprocessable()->assertJsonValidationErrors('position');
        $id = $this->postJson($url, $this->itemPayload())->assertCreated()->assertJsonPath('data.position', 2)->json('data.id');
        $this->patchJson($url.'/'.$id, ['position' => 1])->assertUnprocessable();
        $this->postJson($url, $this->itemPayload(['attributes' => 'not-an-object']))->assertUnprocessable();
        $this->getJson('/api/v1/tenders?per_page=1000&status=invalid')->assertUnprocessable()->assertJsonValidationErrors(['per_page', 'status']);
    }
}
