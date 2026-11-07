<?php

namespace Tests\Feature\Phase3;

use App\Domain\Awards\Actions\AllocateAward;
use App\Domain\Awards\Models\Award;
use App\Domain\Awards\Models\AwardLine;
use App\Domain\Bids\Actions\SaveSupplierBid;
use App\Domain\Bids\Models\Bid;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\OrganizationMembership;
use App\Domain\Organizations\TenantContext;
use App\Domain\Suppliers\Models\OrganizationSupplier;
use App\Domain\Suppliers\Models\SupplierUser;
use App\Domain\Tenders\Actions\CloseTender;
use App\Domain\Tenders\Models\Tender;
use App\Domain\Tenders\Models\TenderInvitation;
use App\Domain\Tenders\Models\TenderItem;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Domain\WorkOrders\Models\WorkOrderLine;
use Database\Seeders\PartialAwardsDemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Api\ApiTestCase;

class PartialLifecycleTest extends ApiTestCase
{
    private function scenario(): array
    {
        $tender = $this->tender(['status' => 'published']);
        $items = $this->inOrganization(fn () => collect([TenderItem::factory()->create(['tender_id' => $tender->id, 'quantity' => '100.000', 'position' => 1]), TenderItem::factory()->create(['tender_id' => $tender->id, 'quantity' => '50.000', 'position' => 2])]));
        $actors = [];
        $invitations = [];
        $bids = [];
        foreach ([0, 1, 2] as $i) {
            $supplier = $this->supplier();
            $actor = SupplierUser::factory()->create(['supplier_id' => $supplier->id]);
            $actors[] = $actor;
            $inv = $this->inOrganization(fn () => TenderInvitation::factory()->create(['tender_id' => $tender->id, 'supplier_id' => $supplier->id]));
            $invitations[] = $inv;
            $rows = $i === 0 ? [[$items[0], '80.000', '1000.00'], [$items[1], '20.000', '210.00']] : ($i === 1 ? [[$items[0], '70.000', '950.00']] : [[$items[1], '30.000', '200.00']]);
            $lines = array_map(fn ($r) => ['tender_item_id' => $r[0]->id, 'quantity' => $r[1], 'unit_price' => $r[2], 'delivery_days' => 3], $rows);
            $bids[] = $this->inOrganization(function () use ($actor, $inv, $lines) {
                $a = app(SaveSupplierBid::class);
                $a->execute($actor, $inv->id, ['lines' => $lines]);

                return $a->execute($actor, $inv->id, [], true);
            });
        }

        return compact('tender', 'items', 'actors', 'invitations', 'bids');
    }

    private function supplierToken(SupplierUser $actor): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($actor->createToken('portal', ['supplier'])->plainTextToken);
    }

    private function portalUrl(array $s, int $i = 0): string
    {
        return '/api/v1/portal/'.$this->organization->id.'/invitations/'.$s['invitations'][$i]->id;
    }

    private function allocation(array $s, string $key = 'operation-1'): array
    {
        return ['request_key' => $key, 'reason' => 'Distribución por capacidad y plazo', 'lines' => [
            ['bid_item_id' => $s['bids'][0]->items[0]->id, 'quantity' => '60.000', 'unit_price' => '1000.00'],
            ['bid_item_id' => $s['bids'][1]->items[0]->id, 'quantity' => '40.000', 'unit_price' => '950.00'],
            ['bid_item_id' => $s['bids'][2]->items[0]->id, 'quantity' => '30.000', 'unit_price' => '200.00'],
        ]];
    }

    public function test_partial_distributed_award_then_completion_and_separate_orders(): void
    {
        $s = $this->scenario();
        $url = '/api/v1/tenders/'.$s['tender']->id;
        $this->postJson($url.'/awards', $this->allocation($s))->assertConflict();
        $this->postJson($url.'/close')->assertSuccessful()->assertJsonPath('data.status', 'closed');
        $award = $this->postJson($url.'/awards', $this->allocation($s))->assertSuccessful()->assertJsonPath('data.total_amount', '104000.00')->assertJsonCount(3, 'data.lines')->assertJsonCount(3, 'data.orders')->json('data.id');
        $this->getJson($url.'/evaluation')->assertSuccessful()->assertJsonPath('data.status', 'partially_awarded')->assertJsonPath('data.items.0.pending_quantity', '0.000')->assertJsonPath('data.items.1.pending_quantity', '20.000');
        $this->postJson($url.'/awards', $this->allocation($s))->assertSuccessful()->assertJsonPath('data.id', $award);
        $this->assertDatabaseCount('awards', 1);
        $this->assertDatabaseCount('work_orders', 3);
        $payload = ['request_key' => 'operation-2', 'reason' => 'Completar monitores', 'lines' => [['bid_item_id' => $s['bids'][0]->items[1]->id, 'quantity' => '20.000', 'unit_price' => '205.00']]];
        $this->postJson($url.'/awards', $payload)->assertSuccessful()->assertJsonPath('data.total_amount', '4100.00');
        $this->getJson($url.'/evaluation')->assertSuccessful()->assertJsonPath('data.status', 'awarded')->assertJsonPath('data.items.1.pending_quantity', '0.000');
        $this->getJson($url.'/awards')->assertSuccessful()->assertJsonCount(2, 'data');
        $orders = $this->getJson($url.'/work-orders')->assertSuccessful()->assertJsonCount(4, 'data')->json('data');
        foreach ($orders as $order) {
            $this->getJson('/api/v1/work-orders/'.$order['id'])->assertSuccessful();
            $this->inOrganization(function () use ($order) {
                $model = WorkOrder::with('lines.awardLine')->findOrFail($order['id']);
                foreach ($model->lines as $line) {
                    $this->assertSame($model->supplier_id, $line->awardLine->supplier_id);
                }
            });
        }
        $this->assertDatabaseHas('audit_logs', ['action' => 'tender.allocated', 'entity_id' => $s['tender']->id]);
    }

    public function test_idempotency_rejects_changed_payload_and_rounds_exactly(): void
    {
        $s = $this->scenario();
        $this->inOrganization(fn () => app(CloseTender::class)->execute($s['tender'], $this->user));
        $url = '/api/v1/tenders/'.$s['tender']->id.'/awards';
        $p = $this->allocation($s);
        $p['lines'] = [$p['lines'][0]];
        $p['lines'][0]['quantity'] = '0.125';
        $p['lines'][0]['unit_price'] = '0.04';
        $this->postJson($url, $p)->assertSuccessful()->assertJsonPath('data.total_amount', '0.01');
        $p['lines'][0]['quantity'] = '0.250';
        $this->postJson($url, $p)->assertConflict();
        $this->assertDatabaseCount('awards', 1);
    }

    public function test_overallocation_limits_offer_and_requested_across_operations(): void
    {
        $s = $this->scenario();
        $this->inOrganization(fn () => app(CloseTender::class)->execute($s['tender'], $this->user));
        $url = '/api/v1/tenders/'.$s['tender']->id.'/awards';
        $p = $this->allocation($s);
        $p['lines'] = [$p['lines'][0]];
        $p['lines'][0]['quantity'] = '81.000';
        $this->postJson($url, $p)->assertUnprocessable();
        $p['lines'][0]['quantity'] = '70.000';
        $this->postJson($url, $p)->assertSuccessful();
        $p['request_key'] = 'other';
        $p['lines'][0]['quantity'] = '11.000';
        $this->postJson($url, $p)->assertUnprocessable();
        $p['lines'][0]['bid_item_id'] = $s['bids'][1]->items[0]->id;
        $p['lines'][0]['quantity'] = '31.000';
        $this->postJson($url, $p)->assertUnprocessable();
        $this->assertDatabaseCount('awards', 1);
        $this->assertDatabaseCount('work_orders', 1);
    }

    public function test_snapshot_and_bid_lines_are_frozen_in_database(): void
    {
        $s = $this->scenario();
        $this->supplierToken($s['actors'][0]);
        $this->putJson($this->portalUrl($s).'/bid', ['lines' => [['tender_item_id' => $s['items'][0]->id, 'quantity' => '1.000', 'unit_price' => '1.00']]])->assertConflict();
        $this->inOrganization(function () use ($s) {
            try {
                DB::transaction(fn () => DB::table('bid_items')->where('id', $s['bids'][0]->items[0]->id)->update(['unit_price' => '1.00', 'subtotal' => '80.00']));
                $this->fail('Expected immutable bid.');
            } catch (QueryException $e) {
                $this->assertSame('23514', $e->errorInfo[0]);
            }
        });
    }

    public function test_supplier_identity_and_economic_isolation(): void
    {
        $s = $this->scenario();
        $this->supplierToken($s['actors'][0]);
        $this->getJson('/api/v1/tenders')->assertForbidden();
        $this->getJson('/api/v1/auth/me')->assertForbidden();
        $this->getJson('/api/v1/portal/invitations')->assertSuccessful()->assertJsonCount(1, 'data');
        $this->getJson($this->portalUrl($s, 1))->assertNotFound();
        $this->getJson($this->portalUrl($s))->assertSuccessful()->assertJsonPath('data.bid.supplier_id', $s['actors'][0]->supplier_id)->assertDontSee($s['bids'][1]->id)->assertDontSee('950.00');
        $this->authenticateUser($this->user);
        $this->getJson('/api/v1/portal/invitations')->assertUnauthorized();
        $this->postJson('/api/v1/portal/auth/login', ['email' => $s['actors'][0]->email, 'password' => 'supplier-test-password'])->assertSuccessful()->assertJsonStructure(['access_token', 'expires_at']);
        $this->postJson('/api/v1/portal/auth/login', ['email' => $this->user->email, 'password' => 'password'])->assertUnprocessable();
    }

    public function test_supplier_sees_only_own_award_lines_and_orders(): void
    {
        $s = $this->scenario();
        $this->inOrganization(function () use ($s) {
            app(CloseTender::class)->execute($s['tender'], $this->user);
            app(AllocateAward::class)->execute($s['tender'], $this->user, $this->allocation($s));
        });
        $this->supplierToken($s['actors'][0]);
        $this->getJson($this->portalUrl($s))->assertSuccessful()->assertJsonCount(1, 'data.awards')->assertJsonCount(1, 'data.orders')->assertDontSee('950.00')->assertDontSee($s['actors'][1]->supplier_id);
    }

    public function test_buyer_viewer_cannot_close_or_award_and_foreign_tender_is_invisible(): void
    {
        $s = $this->scenario();
        $this->inOrganization(fn () => OrganizationMembership::where('user_id', $this->user->id)->firstOrFail()->update(['role' => 'viewer']));
        $url = '/api/v1/tenders/'.$s['tender']->id;
        $this->getJson($url.'/evaluation')->assertSuccessful();
        $this->postJson($url.'/close')->assertForbidden();
        $this->postJson($url.'/awards', $this->allocation($s))->assertForbidden();
        $other = Organization::factory()->create();
        $foreign = app(TenantContext::class)->run($other->id, fn () => Tender::factory()->create());
        $this->getJson('/api/v1/tenders/'.$foreign->id.'/evaluation')->assertNotFound();
    }

    public function test_publication_deadline_invalid_quantities_and_partial_draft(): void
    {
        $tender = $this->tender(['status' => 'published']);
        $item = $this->inOrganization(fn () => TenderItem::factory()->create(['tender_id' => $tender->id, 'quantity' => '100.000']));
        $supplier = $this->supplier();
        $actor = SupplierUser::factory()->create(['supplier_id' => $supplier->id]);
        $inv = $this->inOrganization(fn () => TenderInvitation::factory()->create(['supplier_id' => $supplier->id, 'tender_id' => $tender->id]));
        $this->supplierToken($actor);
        $url = '/api/v1/portal/'.$this->organization->id.'/invitations/'.$inv->id;
        $p = ['lines' => [['tender_item_id' => $item->id, 'quantity' => '25.000', 'unit_price' => '123.45']]];
        $this->putJson($url.'/bid', $p)->assertSuccessful()->assertJsonPath('data.total_amount', '3086.25');
        $p['lines'][0]['quantity'] = '40.000';
        $this->putJson($url.'/bid', $p)->assertSuccessful()->assertJsonPath('data.total_amount', '4938.00');
        $this->assertDatabaseCount('bids', 1);
        foreach (['0', '-1', '100.001', '1.0001'] as $bad) {
            $p['lines'][0]['quantity'] = $bad;
            $this->putJson($url.'/bid', $p)->assertUnprocessable();
        }
        $this->travel(8)->days();
        $this->postJson($url.'/bid/submit')->assertConflict();
    }

    public function test_submit_retry_is_idempotent_and_keeps_snapshot(): void
    {
        $s = $this->scenario();
        $this->supplierToken($s['actors'][0]);
        $this->postJson($this->portalUrl($s).'/bid/submit')->assertSuccessful()->assertJsonPath('data.submitted_snapshot.total_amount', '84200.00');
        $this->assertSame(3, DB::table('audit_logs')->where('action', 'bid.submitted')->count());
    }

    public function test_rollback_if_order_creation_fails(): void
    {
        $s = $this->scenario();
        $this->inOrganization(fn () => app(CloseTender::class)->execute($s['tender'], $this->user));
        WorkOrder::creating(fn () => throw new \RuntimeException('order failure'));
        try {
            $this->inOrganization(fn () => app(AllocateAward::class)->execute($s['tender'], $this->user, $this->allocation($s)));
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('order failure', $e->getMessage());
        }
        $this->assertDatabaseCount('awards', 0);
        $this->assertDatabaseCount('award_lines', 0);
        $this->assertDatabaseCount('work_orders', 0);
        $this->assertDatabaseHas('tenders', ['id' => $s['tender']->id, 'status' => 'closed']);
    }

    public function test_web_portal_and_evaluation_render_and_use_separate_guard(): void
    {
        $s = $this->scenario();
        $this->withoutHeader('Authorization');
        $this->app['auth']->forgetGuards();
        $this->get('/portal')->assertRedirect('/portal/login');
        $this->get('/portal/login')->assertSuccessful()->assertSee('Tu próxima oportunidad');
        $this->post('/portal/login', ['email' => $s['actors'][0]->email, 'password' => 'supplier-test-password'])->assertRedirect('/portal');
        $this->get('/portal')->assertSuccessful()->assertSee($s['tender']->title);
        $this->get('/portal/'.$this->organization->id.'/invitations/'.$s['invitations'][0]->id)->assertSuccessful()->assertSee('Mi oferta')->assertDontSee('950.00');
        $this->get('/portal/'.$this->organization->id.'/invitations/'.$s['invitations'][1]->id)->assertNotFound();
        $this->actingAs($this->user, 'web')->get('/tenders/'.$s['tender']->id.'/evaluation')->assertSuccessful()->assertSee('Matriz comparativa')->assertSee('950.00');
    }

    public function test_database_trigger_enforces_capacity_and_cross_references(): void
    {
        $s = $this->scenario();
        $this->inOrganization(function () use ($s) {
            app(CloseTender::class)->execute($s['tender'], $this->user);
            $award = app(AllocateAward::class)->execute($s['tender'], $this->user, $this->allocation($s));
            $line = $award->lines->first();
            foreach ([['quantity' => '100.000', 'subtotal' => bcmul('100', $line->unit_price, 2)], ['supplier_id' => $s['actors'][2]->supplier_id], ['tender_item_id' => $s['items'][1]->id]] as $bad) {
                try {
                    DB::transaction(function () use ($line, $bad) {
                        $copy = $line->getAttributes();
                        unset($copy['id']);
                        $copy['id'] = (string) Str::ulid();
                        $copy['award_id'] = Award::create(['tender_id' => $line->tender_id, 'legacy' => false, 'bid_id' => null, 'awarded_by' => $this->user->id, 'awarded_at' => now(), 'reason' => 'test', 'request_key' => (string) Str::uuid(), 'request_hash' => str_repeat('a', 64), 'snapshot' => []])->id;
                        DB::table('award_lines')->insert([...$copy, ...$bad]);
                    });
                    $this->fail('Expected constraint');
                } catch (QueryException $e) {
                    $this->assertContains($e->errorInfo[0], ['23503', '23514']);
                }
            }
        });
    }

    public function test_additive_demo_is_repeat_safe_and_preserves_existing_data(): void
    {
        config(['bidding.demo_enabled' => true]);
        $original = $this->tender();
        $seeder = app(PartialAwardsDemoSeeder::class);
        $seeder->run('local-only-test-secret');
        $this->assertDatabaseHas('tenders', ['id' => $original->id]);
        $this->assertDatabaseCount('supplier_users', 3);
        $this->assertDatabaseCount('awards', 3);
        $this->assertDatabaseCount('work_orders', 7);
        $org = Organization::where('name', 'DEMO Fase 3')->firstOrFail();
        app(TenantContext::class)->run($org->id, function () {
            $this->assertSame(1, Tender::where('status', 'partially_awarded')->count());
            $this->assertSame(1, Tender::where('status', 'awarded')->count());
            $this->assertSame(3, Bid::where('status', 'draft')->count());
        });
        try {
            $seeder->run('another-local-secret');
            $this->fail('Expected repeat rejection');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('already exist', $e->getMessage());
        }
        $this->assertDatabaseCount('supplier_users', 3);
    }

    public function test_inactive_identity_link_invitation_and_organization_are_rechecked(): void
    {
        $s = $this->scenario();
        $actor = $s['actors'][0];
        $this->supplierToken($actor);
        $actor->update(['active' => false]);
        $this->getJson($this->portalUrl($s))->assertForbidden();
        $actor->update(['active' => true]);
        $this->app['auth']->forgetGuards();
        $this->inOrganization(fn () => OrganizationSupplier::where('supplier_id', $actor->supplier_id)->update(['status' => 'inactive']));
        $this->getJson($this->portalUrl($s))->assertNotFound();
        $this->inOrganization(fn () => OrganizationSupplier::where('supplier_id', $actor->supplier_id)->update(['status' => 'active']));
        $this->inOrganization(fn () => $s['invitations'][0]->update(['status' => 'revoked']));
        $this->getJson($this->portalUrl($s))->assertNotFound();
        $this->inOrganization(fn () => $s['invitations'][0]->update(['status' => 'responded']));
        $this->organization->update(['status' => 'inactive']);
        $this->getJson($this->portalUrl($s))->assertNotFound();
    }

    public function test_confirmed_headers_lines_and_orders_preserve_economics(): void
    {
        $s = $this->scenario();
        $this->inOrganization(function () use ($s) {
            app(CloseTender::class)->execute($s['tender'], $this->user);
            $award = app(AllocateAward::class)->execute($s['tender'], $this->user, $this->allocation($s));
            foreach (['awards' => [$award->id, ['total_amount' => '1.00']], 'award_lines' => [$award->lines[0]->id, ['quantity' => '1.000']], 'work_orders' => [$award->workOrders[0]->id, ['supplier_id' => $s['actors'][2]->supplier_id]], 'work_order_lines' => [$award->workOrders[0]->lines[0]->id, ['unit_price' => '1.00']]] as $table => $change) {
                try {
                    DB::transaction(fn () => DB::table($table)->where('id', $change[0])->update($change[1]));
                    $this->fail('Expected immutable economics');
                } catch (QueryException $e) {
                    $this->assertSame('23514', $e->errorInfo[0]);
                }
            }
        });
    }

    public function test_new_line_factories_respect_composite_references(): void
    {
        $this->inOrganization(function () {
            $line = WorkOrderLine::factory()->create();
            $this->assertSame($line->supplier_id, $line->awardLine->supplier_id);
            $this->assertSame($line->award_id, $line->workOrder->award_id);
        });
    }

    public function test_web_supplier_draft_submit_and_buyer_allocate_flow(): void
    {
        $tender = $this->tender(['status' => 'published']);
        $item = $this->inOrganization(fn () => TenderItem::factory()->create(['tender_id' => $tender->id, 'quantity' => '10.000']));
        $supplier = $this->supplier();
        $actor = SupplierUser::factory()->create(['supplier_id' => $supplier->id]);
        $inv = $this->inOrganization(fn () => TenderInvitation::factory()->create(['supplier_id' => $supplier->id, 'tender_id' => $tender->id]));
        $this->withoutHeader('Authorization');
        $this->app['auth']->forgetGuards();
        $url = '/portal/'.$this->organization->id.'/invitations/'.$inv->id;
        $this->actingAs($actor, 'supplier')->get($url)->assertOk()->assertSee('Guardar borrador');
        $this->put($url.'/bid', ['lines' => [['quote' => '1', 'tender_item_id' => $item->id, 'quantity' => '6.000', 'unit_price' => '12.50']]])->assertRedirect($url);
        $this->post($url.'/bid/submit', ['confirmation' => '1'])->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('Presentada')->assertDontSee('Guardar borrador');
        $bid = $this->inOrganization(fn () => Bid::with('items')->where('invitation_id', $inv->id)->firstOrFail());
        $buyerUrl = '/tenders/'.$tender->id;
        $this->actingAs($this->user, 'web')->post($buyerUrl.'/close', ['confirmation' => '1'])->assertRedirect();
        $this->post($buyerUrl.'/awards', ['request_key' => 'web-test', 'reason' => 'Prueba desde formularios', 'confirmation' => '1', 'lines' => [['select' => '1', 'bid_item_id' => $bid->items[0]->id, 'quantity' => '6.000', 'unit_price' => '12.50']]])->assertRedirect();
        $this->get($buyerUrl.'/evaluation')->assertOk()->assertSee('4.000')->assertSee('75.00');
        $this->assertDatabaseCount('awards', 1);
        $this->assertDatabaseCount('work_orders', 1);
    }

    public function test_new_endpoints_require_identity_and_supplier_logout_revokes_only_current_token(): void
    {
        $s = $this->scenario();
        $this->withoutHeader('Authorization');
        $this->app['auth']->forgetGuards();
        $buyer = '/api/v1/tenders/'.$s['tender']->id;
        $portal = $this->portalUrl($s);
        foreach ([['get', '/api/v1/portal/invitations'], ['get', $portal], ['put', $portal.'/bid'], ['post', $portal.'/bid/submit'], ['post', '/api/v1/portal/auth/logout'], ['get', $buyer.'/evaluation'], ['get', $buyer.'/bids'], ['get', $buyer.'/awards'], ['get', $buyer.'/work-orders'], ['get', '/api/v1/work-orders/'.strtolower((string) Str::ulid())], ['post', $buyer.'/close'], ['post', $buyer.'/awards']] as [$method,$url]) {
            $this->{$method.'Json'}($url)->assertUnauthorized();
        }
        $other = $s['actors'][0]->createToken('other', ['supplier'])->plainTextToken;
        $this->supplierToken($s['actors'][0]);
        $this->postJson('/api/v1/portal/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/portal/invitations')->assertUnauthorized();
        $this->withToken($other)->getJson('/api/v1/portal/invitations')->assertOk();
    }

    public function test_order_insert_rejects_foreign_supplier_and_altered_awarded_economics(): void
    {
        $other = $this->supplier();
        $this->inOrganization(function () use ($other) {
            $line = AwardLine::factory()->create();
            $order = WorkOrder::factory()->create(['award_id' => $line->award_id, 'supplier_id' => $line->supplier_id, 'tender_id' => $line->tender_id]);
            $row = ['organization_id' => $this->organization->id, 'work_order_id' => $order->id, 'award_id' => $line->award_id, 'supplier_id' => $line->supplier_id, 'award_line_id' => $line->id, 'quantity' => $line->quantity, 'unit_price' => $line->unit_price, 'subtotal' => $line->subtotal, 'currency' => $line->currency, 'snapshot' => '{}', 'created_at' => now(), 'updated_at' => now()];
            foreach ([['change' => ['supplier_id' => $other->id], 'code' => '23503'], ['change' => ['quantity' => '0.500', 'subtotal' => '500.00'], 'code' => '23514']] as $case) {
                try {
                    DB::transaction(fn () => DB::table('work_order_lines')->insert([...$row, 'id' => strtolower((string) Str::ulid()), ...$case['change']]));
                    $this->fail('Expected composite reference or economic constraint');
                } catch (QueryException $e) {
                    $this->assertSame($case['code'], $e->errorInfo[0]);
                }
            }
        });
    }
}
