<?php

namespace Tests\Feature;

use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\TenantContext;
use App\Domain\Shared\Enums\BidStatus;
use App\Domain\Shared\Enums\TenderStatus;
use App\Domain\Shared\Enums\TenderType;
use App\Domain\Tenders\Models\Tender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_is_complete_consistent_and_has_no_external_effects(): void
    {
        config(['bidding.demo_enabled' => true]);
        Http::preventStrayRequests();
        Mail::fake();
        Queue::fake();
        $this->artisan('bidding:seed-demo')->assertSuccessful();
        $this->assertDatabaseCount('organizations', 2);
        $this->assertDatabaseCount('suppliers', 15);
        $this->assertDatabaseCount('tenders', 20);
        $this->assertDatabaseCount('bids', 48);
        $this->assertDatabaseCount('awards', 4);
        $this->assertDatabaseCount('work_orders', 4);
        foreach (Organization::all() as $organization) {
            app(TenantContext::class)->run($organization->id, function (): void {
                $tenders = Tender::with(['items', 'repairScope', 'invitations', 'bids.items', 'award.workOrder', 'history'])->get();
                $this->assertCount(10, $tenders);
                $this->assertCount(2, $tenders->pluck('type')->unique());
                foreach ($tenders as $tender) {
                    $this->assertTrue($tender->is_demo);
                    $this->assertCount(2, $tender->items);
                    $this->assertSame($tender->type === TenderType::Repair, $tender->repairScope !== null);
                    $this->assertSame($tender->status->value, $tender->history->sortBy('occurred_at')->last()->to_status);
                    foreach ($tender->bids as $bid) {
                        $this->assertCount(2, $bid->items);
                        $this->assertSame($tender->items->pluck('id')->sort()->values()->all(), $bid->items->pluck('tender_item_id')->sort()->values()->all());
                        $this->assertSame($bid->total_amount, $bid->items->reduce(fn ($total, $item) => bcadd($total, $item->subtotal, 2), '0.00'));
                    }
                    if ($tender->status === TenderStatus::Awarded) {
                        $this->assertNotNull($tender->award->workOrder);
                        $winner = $tender->bids->firstWhere('status', BidStatus::Accepted);
                        $this->assertSame($winner->id, $tender->award->bid_id);
                        $this->assertSame($winner->total_amount, $tender->award->snapshot['total_amount']);
                    }
                }
            });
        }
        $this->artisan('bidding:seed-demo')->assertFailed();
        $this->assertDatabaseCount('tenders', 20);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Queue::assertNothingPushed();
    }

    public function test_seeder_refuses_production_even_when_enabled(): void
    {
        config(['bidding.demo_enabled' => true]);
        $this->app->instance('env', 'production');
        $this->artisan('bidding:seed-demo')->assertFailed();
        $this->assertSame(0, DB::table('organizations')->count());
    }

    public function test_seeder_requires_explicit_enablement_and_valid_profile(): void
    {
        config(['bidding.demo_enabled' => false]);
        $this->artisan('bidding:seed-demo')->assertFailed();
        config(['bidding.demo_enabled' => true]);
        $this->artisan('bidding:seed-demo', ['--profile' => 'invalid'])->assertFailed();
        $this->assertDatabaseCount('organizations', 0);
    }
}
