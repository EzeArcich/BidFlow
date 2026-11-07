<?php

namespace Tests\Feature;

use App\Domain\Awards\Models\Award;
use App\Domain\Bids\Models\Bid;
use App\Domain\Bids\Models\BidItem;
use App\Domain\Integrations\Models\IdempotencyRecord;
use App\Domain\Integrations\Models\InvitationAccessToken;
use App\Domain\Integrations\Models\WebhookDelivery;
use App\Domain\Integrations\Models\WebhookEndpoint;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\TenantContext;
use App\Domain\Tenders\Models\RepairScope;
use App\Domain\Tenders\Models\Tender;
use App\Domain\Tenders\Models\TenderInvitation;
use App\Domain\Tenders\Models\TenderItem;
use App\Domain\WorkOrders\Models\WorkOrder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(callable $callback): mixed
    {
        return app(TenantContext::class)->run(Organization::factory()->create()->id, $callback(...));
    }

    private function assertRejected(callable $callback): void
    {
        try {
            // A savepoint lets the surrounding PostgreSQL test transaction continue.
            DB::transaction($callback(...));
            $this->fail('Expected a database integrity violation.');
        } catch (QueryException $exception) {
            $this->assertContains($exception->errorInfo[0], ['23503', '23505', '23514']);
        }
    }

    public function test_factory_can_build_multiple_lines_for_the_same_bid(): void
    {
        $this->tenant(function (): void {
            $bid = Bid::factory()->create();
            $lines = BidItem::factory()->count(2)->create(['bid_id' => $bid->id]);
            $this->assertCount(2, $lines);
            $this->assertSame([1, 2], TenderItem::where('tender_id', $bid->tender_id)->orderBy('position')->pluck('position')->all());
        });
    }

    public function test_database_rejects_cross_tenant_children_even_when_bypassing_eloquent(): void
    {
        $a = Organization::factory()->create();
        $b = Organization::factory()->create();
        $tender = app(TenantContext::class)->run($a->id, fn () => Tender::factory()->create());
        $this->assertRejected(fn () => DB::table('tender_items')->insert([
            'id' => (string) Str::ulid(), 'organization_id' => $b->id,
            'tender_id' => $tender->id, 'position' => 1, 'description' => 'Cross-tenant', 'quantity' => 1, 'unit' => 'unit',
        ]));
    }

    public function test_bid_must_match_invitation_tender_supplier_and_currency(): void
    {
        $this->tenant(function (): void {
            $invitation = TenderInvitation::factory()->create();
            $other = TenderInvitation::factory()->create();
            foreach ([['tender_id' => $other->tender_id], ['supplier_id' => $other->supplier_id], ['currency' => 'USD']] as $invalid) {
                $this->assertRejected(fn () => Bid::factory()->create(['invitation_id' => $invitation->id, ...$invalid]));
            }
            Bid::factory()->create(['invitation_id' => $invitation->id]);
            $this->assertRejected(fn () => Bid::factory()->create(['invitation_id' => $invitation->id]));
        });
    }

    public function test_lines_and_awards_cannot_reference_another_tender(): void
    {
        $this->tenant(function (): void {
            $bid = Bid::factory()->create();
            $item = TenderItem::factory()->create();
            $this->assertRejected(fn () => BidItem::factory()->create(['bid_id' => $bid->id, 'tender_item_id' => $item->id]));
            $this->assertRejected(fn () => Award::factory()->create(['bid_id' => $bid->id, 'tender_id' => $item->tender_id]));
        });
    }

    public function test_only_one_award_and_order_are_allowed_and_numbers_are_unique_per_tenant(): void
    {
        $this->tenant(function (): void {
            $award = Award::factory()->create();
            $otherBid = Bid::factory()->create(['invitation_id' => TenderInvitation::factory()->create(['tender_id' => $award->tender_id])->id]);
            $this->assertRejected(fn () => Award::factory()->create(['bid_id' => $otherBid->id]));
            $this->assertRejected(fn () => Award::factory()->create(['bid_id' => $award->bid_id]));
            $order = WorkOrder::factory()->create(['award_id' => $award->id]);
            $this->assertRejected(fn () => WorkOrder::factory()->create(['award_id' => $award->id]));
            $this->assertRejected(fn () => WorkOrder::factory()->create(['number' => $order->number]));
        });
    }

    public static function invalidAmounts(): array
    {
        return [[['quantity' => '0']], [['quantity' => '-1']], [['unit_price' => '-1']], [['subtotal' => '-1']], [['subtotal' => '999.99']]];
    }

    #[DataProvider('invalidAmounts')]
    public function test_database_rejects_invalid_money_and_quantities(array $attributes): void
    {
        $this->tenant(fn () => $this->assertRejected(fn () => BidItem::factory()->create($attributes)));
    }

    public function test_decimal_values_roundtrip_exactly_and_repair_scope_requires_repair(): void
    {
        $this->tenant(function (): void {
            $line = BidItem::factory()->create(['quantity' => '0.125', 'unit_price' => '1234567.89', 'subtotal' => '154320.99']);
            $this->assertSame('1234567.89', $line->fresh()->unit_price);
            $this->assertSame('154320.99', $line->fresh()->subtotal);
            $this->assertRejected(fn () => RepairScope::factory()->create(['tender_id' => Tender::factory()->create()->id]));
        });
    }

    public function test_delivery_and_idempotency_uniqueness_and_secret_storage(): void
    {
        $this->tenant(function (): void {
            $endpoint = WebhookEndpoint::factory()->create(['secret' => 'test-secret']);
            $this->assertNotSame('test-secret', DB::table('webhook_endpoints')->where('id', $endpoint->id)->value('secret'));
            $this->assertSame('test-secret', $endpoint->fresh()->secret);
            $this->assertArrayNotHasKey('secret', $endpoint->toArray());
            $delivery = WebhookDelivery::factory()->create(['webhook_endpoint_id' => $endpoint->id]);
            $this->assertRejected(fn () => WebhookDelivery::factory()->create(['webhook_endpoint_id' => $endpoint->id, 'outbox_event_id' => $delivery->outbox_event_id]));
            $record = IdempotencyRecord::factory()->create();
            $this->assertRejected(fn () => IdempotencyRecord::factory()->create(['api_client_id' => $record->api_client_id, 'operation' => $record->operation, 'key' => $record->key]));
            $token = InvitationAccessToken::factory()->create();
            $this->assertArrayNotHasKey('token_hash', $token->toArray());
        });
    }
}
