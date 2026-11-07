<?php

namespace Tests\Feature\Phase3;

use App\Domain\Awards\Actions\AllocateAward;
use App\Domain\Bids\Actions\SaveSupplierBid;
use App\Domain\Bids\Models\Bid;
use App\Domain\Bids\Models\BidItem;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\OrganizationMembership;
use App\Domain\Organizations\TenantContext;
use App\Domain\Suppliers\Models\OrganizationSupplier;
use App\Domain\Suppliers\Models\Supplier;
use App\Domain\Suppliers\Models\SupplierUser;
use App\Domain\Tenders\Models\Tender;
use App\Domain\Tenders\Models\TenderInvitation;
use App\Domain\Tenders\Models\TenderItem;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Independent PostgreSQL connections and committed fixtures in a disposable test-owned schema. */
class PostgresConcurrencyTest extends TestCase
{
    private string $schema;

    private string $directory;

    private mixed $previousPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(function_exists('pcntl_fork'), 'Real concurrency tests require PCNTL.');
        $this->schema = 'phase3_'.strtolower((string) Str::ulid());
        $this->directory = sys_get_temp_dir().'/'.$this->schema;
        mkdir($this->directory, 0700);
        $this->previousPath = config('database.connections.pgsql.search_path');
        DB::statement('CREATE SCHEMA '.$this->schema);
        config(['database.connections.pgsql.search_path' => $this->schema]);
        DB::purge();
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        DB::purge();
        config(['database.connections.pgsql.search_path' => $this->previousPath]);
        DB::statement('DROP SCHEMA '.$this->schema.' CASCADE');
        foreach (glob($this->directory.'/*') as $file) {
            unlink($file);
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    private function fixture(): array
    {
        $org = Organization::factory()->create(['type' => 'company']);
        $buyer = User::factory()->create();

        return app(TenantContext::class)->run($org->id, function () use ($org, $buyer) {
            OrganizationMembership::factory()->create(['user_id' => $buyer->id, 'role' => 'owner']);
            $tender = Tender::factory()->create(['type' => 'products', 'status' => 'published', 'created_by' => $buyer->id]);
            $item = TenderItem::factory()->create(['tender_id' => $tender->id, 'quantity' => '100.000']);
            $lines = [];
            for ($i = 0; $i < 2; $i++) {
                $supplier = Supplier::factory()->create(['type' => 'vendor']);
                OrganizationSupplier::factory()->create(['supplier_id' => $supplier->id]);
                $actor = SupplierUser::factory()->create(['supplier_id' => $supplier->id]);
                $inv = TenderInvitation::factory()->create(['tender_id' => $tender->id, 'supplier_id' => $supplier->id]);
                $action = app(SaveSupplierBid::class);
                $action->execute($actor, $inv->id, ['lines' => [['tender_item_id' => $item->id, 'quantity' => '80.000', 'unit_price' => '10.00']]]);
                $lines[] = $action->execute($actor, $inv->id, [], true)->items->first()->id;
            }
            $tender->update(['status' => 'closed']);

            return compact('org', 'buyer', 'tender', 'lines');
        });
    }

    private function race(bool $sameKey): array
    {
        $f = $this->fixture();
        DB::disconnect(); // Never share a live libpq socket across fork.
        $pids = [];
        for ($i = 0; $i < 2; $i++) {
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid);
            if ($pid === 0) {
                DB::purge();
                DB::statement("SET application_name='".$this->schema.'_worker_'.$i."'");
                try {
                    $result = app(TenantContext::class)->run($f['org']->id, function () use ($f, $i, $sameKey) {
                        $payload = ['request_key' => $sameKey ? 'same-key' : 'operation-'.$i, 'reason' => 'Concurrent test', 'lines' => [['bid_item_id' => $f['lines'][$sameKey ? 0 : $i], 'quantity' => '60.000', 'unit_price' => '10.00']]];

                        return DB::transaction(function () use ($f, $i, $payload) {
                            if ($i === 0) {
                                Tender::whereKey($f['tender']->id)->lockForUpdate()->firstOrFail();
                                file_put_contents($this->directory.'/locked', '1');
                                $until = microtime(true) + 10;
                                while (! file_exists($this->directory.'/release') && microtime(true) < $until) {
                                    usleep(10000);
                                }
                            }

                            return app(AllocateAward::class)->execute($f['tender'], $f['buyer'], $payload)->id;
                        });
                    });
                    $output = ['status' => 200, 'id' => $result];
                } catch (ValidationException $e) {
                    $output = ['status' => 422];
                } catch (\Throwable $e) {
                    $output = ['status' => 500, 'error' => get_class($e).': '.$e->getMessage()];
                }
                file_put_contents($this->directory.'/result-'.$i, json_encode($output));
                DB::disconnect();
                exit(0);
            }
            $pids[] = $pid;
            if ($i === 0) {
                $until = microtime(true) + 5;
                while (! file_exists($this->directory.'/locked') && microtime(true) < $until) {
                    usleep(10000);
                }
                $this->assertFileExists($this->directory.'/locked');
            }
        }
        $blocked = false;
        $until = microtime(true) + 5;
        do {
            $blocked = DB::selectOne('SELECT count(*) AS count FROM pg_stat_activity WHERE application_name=? AND wait_event_type=?', [$this->schema.'_worker_1', 'Lock'])->count > 0;
            if (! $blocked) {
                usleep(10000);
            }
        } while (! $blocked && microtime(true) < $until);
        file_put_contents($this->directory.'/release', '1');
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertSame(0, pcntl_wexitstatus($status));
        }
        $this->assertTrue($blocked, 'The second PostgreSQL connection must actually wait for the tender row lock.');
        $results = array_map(fn ($i) => json_decode(file_get_contents($this->directory.'/result-'.$i), true), [0, 1]);
        $this->assertSame(1, DB::table('awards')->count(), json_encode($results));
        $this->assertSame(1, DB::table('work_orders')->count());
        $this->assertSame('60.000', DB::table('award_lines')->sum('quantity'));

        return $results;
    }

    public function test_simultaneous_awards_cannot_exceed_requested_quantity(): void
    {
        $results = $this->race(false);
        $this->assertSame([200, 422], array_column($results, 'status'));
    }

    public function test_simultaneous_retries_create_one_award_and_one_order(): void
    {
        $results = $this->race(true);
        $this->assertSame([200, 200], array_column($results, 'status'), json_encode($results));
        $this->assertSame($results[0]['id'], $results[1]['id']);
    }

    public function test_upgrade_materializes_legacy_awards_and_orders_without_losing_data(): void
    {
        $files = glob(database_path('migrations/2026_11_06_2*.php'));
        foreach (array_reverse($files) as $file) {
            (require $file)->down();
        }
        $org = Organization::factory()->create();
        $buyer = User::factory()->create();
        app(TenantContext::class)->run($org->id, function () use ($buyer, $files) {
            $bid = Bid::factory()->create(['status' => 'accepted', 'total_amount' => '1000.00']);
            $line = BidItem::factory()->create(['bid_id' => $bid->id]);
            $awardId = strtolower((string) Str::ulid());
            DB::table('awards')->insert(['id' => $awardId, 'organization_id' => $bid->organization_id, 'tender_id' => $bid->tender_id, 'bid_id' => $bid->id, 'awarded_by' => $buyer->id, 'awarded_at' => now(), 'reason' => 'Legacy upgrade test', 'snapshot' => '{"legacy":true}', 'created_at' => now(), 'updated_at' => now()]);
            $order = WorkOrder::factory()->create(['award_id' => $awardId]);
            foreach ($files as $file) {
                (require $file)->up();
            }
            $this->assertDatabaseHas('awards', ['id' => $awardId, 'legacy' => true, 'total_amount' => '1000.00']);
            $this->assertDatabaseHas('work_orders', ['id' => $order->id, 'supplier_id' => $bid->supplier_id, 'tender_id' => $bid->tender_id]);
            $this->assertDatabaseHas('award_lines', ['award_id' => $awardId, 'bid_item_id' => $line->id, 'quantity' => '1.000']);
            $this->assertDatabaseCount('work_order_lines', 1);
            $this->assertNotNull($bid->fresh()->submitted_snapshot);
        });
    }
}
