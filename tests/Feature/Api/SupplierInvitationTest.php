<?php

namespace Tests\Feature\Api;

use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\TenantContext;
use App\Domain\Suppliers\Models\OrganizationSupplier;
use App\Domain\Suppliers\Models\Supplier;
use App\Domain\Suppliers\Models\SupplierContact;
use App\Domain\Tenders\Models\TenderInvitation;

class SupplierInvitationTest extends ApiTestCase
{
    public function test_supplier_list_only_returns_active_linked_suppliers(): void
    {
        $available = $this->supplier();
        $this->supplier(['status' => 'inactive']);
        $inactive = $this->supplier();
        $inactive->update(['status' => 'inactive']);
        $other = Organization::factory()->create();
        app(TenantContext::class)->run($other->id, fn () => OrganizationSupplier::factory()->create());
        Supplier::factory()->create();
        $this->getJson('/api/v1/suppliers')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $available->id)->assertJsonMissingPath('data.0.organizationLinks');
    }

    public function test_repeated_invitations_return_same_record_without_duplicate_audit(): void
    {
        $tender = $this->tender();
        $supplier = $this->supplier();
        $contact = SupplierContact::factory()->create(['supplier_id' => $supplier->id]);
        $url = "/api/v1/tenders/{$tender->id}/invitations";
        $payload = ['invitations' => [['supplier_id' => $supplier->id, 'contact_id' => $contact->id]]];
        $id = $this->postJson($url, $payload)->assertOk()->json('data.0.id');
        $this->postJson($url, ['invitations' => [['supplier_id' => $supplier->id]]])->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.contact_id', $contact->id);
        $this->assertDatabaseCount('tender_invitations', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->postJson($url, ['invitations' => [['supplier_id' => $supplier->id], ['supplier_id' => $supplier->id]]])->assertUnprocessable();
    }

    public function test_invalid_supplier_and_contacts_roll_back_whole_batch(): void
    {
        $tender = $this->tender();
        $supplier = $this->supplier();
        $unlinked = Supplier::factory()->create();
        $url = "/api/v1/tenders/{$tender->id}/invitations";
        $this->postJson($url, ['invitations' => [['supplier_id' => $supplier->id], ['supplier_id' => $unlinked->id]]])->assertUnprocessable()->assertJsonValidationErrors('invitations.1.supplier_id');
        $this->assertDatabaseCount('tender_invitations', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $foreignContact = SupplierContact::factory()->create();
        $this->postJson($url, ['invitations' => [['supplier_id' => $supplier->id, 'contact_id' => $foreignContact->id]]])->assertUnprocessable()->assertJsonValidationErrors('invitations.0.contact_id');
        $this->inOrganization(fn () => OrganizationSupplier::where('supplier_id', $supplier->id)->firstOrFail()->update(['status' => 'inactive']));
        $this->postJson($url, ['invitations' => [['supplier_id' => $supplier->id]]])->assertUnprocessable();
        $this->postJson($url, ['invitations' => []])->assertUnprocessable();
        $this->assertDatabaseCount('tender_invitations', 0);
    }

    public function test_revoked_invitation_and_inactive_supplier_block_publication(): void
    {
        $tender = $this->tender();
        $supplier = $this->supplier();
        $url = "/api/v1/tenders/{$tender->id}";
        $this->postJson($url.'/items', $this->itemPayload())->assertCreated();
        $this->postJson($url.'/invitations', ['invitations' => [['supplier_id' => $supplier->id]]])->assertOk();
        $this->inOrganization(fn () => TenderInvitation::firstOrFail()->update(['status' => 'revoked']));
        $this->postJson($url.'/publish')->assertUnprocessable();
        $this->inOrganization(fn () => TenderInvitation::firstOrFail()->update(['status' => 'pending']));
        $supplier->update(['status' => 'inactive']);
        $this->postJson($url.'/publish')->assertUnprocessable();
        $this->assertDatabaseHas('tenders', ['id' => $tender->id, 'status' => 'draft']);
    }
}
