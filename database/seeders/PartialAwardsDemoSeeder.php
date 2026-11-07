<?php

namespace Database\Seeders;

use App\Domain\Awards\Actions\AllocateAward;
use App\Domain\Bids\Actions\SaveSupplierBid;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\OrganizationMembership;
use App\Domain\Organizations\TenantContext;
use App\Domain\Suppliers\Models\OrganizationSupplier;
use App\Domain\Suppliers\Models\Supplier;
use App\Domain\Suppliers\Models\SupplierUser;
use App\Domain\Tenders\Actions\CloseTender;
use App\Domain\Tenders\Models\Tender;
use App\Domain\Tenders\Models\TenderInvitation;
use App\Domain\Tenders\Models\TenderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Additive, fictitious, local-only demo; never modifies existing identities or passwords. */
class PartialAwardsDemoSeeder
{
    public function run(string $password): void
    {
        if (! app()->environment(['local', 'testing']) || ! config('bidding.demo_enabled')) {
            throw new RuntimeException('Requires local/testing and BIDDING_DEMO_ENABLED=true.');
        }
        if (strlen($password) < 12) {
            throw new RuntimeException('Choose a local password of at least 12 characters.');
        }
        if (Organization::where('name', 'DEMO Fase 3')->exists() || User::where('email', 'comprador.fase3@example.test')->exists() || SupplierUser::whereIn('email', ['portal1@example.test', 'portal2@example.test', 'portal3@example.test'])->exists()) {
            throw new RuntimeException('Phase 3 demo or its identities already exist; no data was changed.');
        }
        DB::transaction(function () use ($password) {
            $org = Organization::factory()->create(['name' => 'DEMO Fase 3', 'type' => 'company']);
            $buyer = User::factory()->create(['name' => 'DEMO Comprador', 'email' => 'comprador.fase3@example.test', 'password' => $password]);
            app(TenantContext::class)->run($org->id, function () use ($buyer, $password) {
                OrganizationMembership::factory()->create(['user_id' => $buyer->id, 'role' => 'owner']);
                $actors = [];
                for ($i = 1; $i <= 3; $i++) {
                    $supplier = Supplier::factory()->create(['type' => 'vendor', 'legal_name' => 'DEMO Fase 3 Proveedor '.$i, 'trade_name' => 'DEMO Proveedor '.$i]);
                    OrganizationSupplier::factory()->create(['supplier_id' => $supplier->id]);
                    $actors[] = SupplierUser::factory()->create(['supplier_id' => $supplier->id, 'name' => 'DEMO Portal '.$i, 'email' => 'portal'.$i.'@example.test', 'password' => $password]);
                }
                foreach (['published', 'closed', 'partially_awarded', 'awarded'] as $state) {
                    $tender = Tender::factory()->create(['type' => 'products', 'title' => 'DEMO Equipamiento — '.$state, 'status' => 'published', 'created_by' => $buyer->id, 'source_system' => 'demo_phase3', 'external_reference' => 'DEMO-F3-'.$state, 'conditions' => ['notes' => 'DEMO: datos ficticios, sin efectos externos']]);
                    $items = [TenderItem::factory()->create(['tender_id' => $tender->id, 'position' => 1, 'description' => 'DEMO Notebooks', 'unit' => 'unidad', 'quantity' => '100.000']), TenderItem::factory()->create(['tender_id' => $tender->id, 'position' => 2, 'description' => 'DEMO Monitores', 'unit' => 'unidad', 'quantity' => '50.000'])];
                    $bids = [];
                    foreach ($actors as $i => $actor) {
                        $inv = TenderInvitation::factory()->create(['tender_id' => $tender->id, 'supplier_id' => $actor->supplier_id]);
                        $lines = $i === 0 ? [['tender_item_id' => $items[0]->id, 'quantity' => '80.000', 'unit_price' => '1000.00'], ['tender_item_id' => $items[1]->id, 'quantity' => '20.000', 'unit_price' => '210.00']] : [['tender_item_id' => $items[$i === 1 ? 0 : 1]->id, 'quantity' => $i === 1 ? '70.000' : '30.000', 'unit_price' => $i === 1 ? '950.00' : '200.00']];
                        $action = app(SaveSupplierBid::class);
                        $bid = $action->execute($actor, $inv->id, ['lines' => $lines]);
                        $bids[] = $state === 'published' ? $bid : $action->execute($actor, $inv->id, [], true);
                    }
                    if ($state !== 'published') {
                        app(CloseTender::class)->execute($tender, $buyer);
                    }
                    if (in_array($state, ['partially_awarded', 'awarded'])) {
                        app(AllocateAward::class)->execute($tender, $buyer, ['request_key' => 'demo-first', 'reason' => 'DEMO Distribución por capacidad', 'lines' => [['bid_item_id' => $bids[0]->items[0]->id, 'quantity' => '60.000', 'unit_price' => '1000.00'], ['bid_item_id' => $bids[1]->items[0]->id, 'quantity' => '40.000', 'unit_price' => '950.00'], ['bid_item_id' => $bids[2]->items[0]->id, 'quantity' => '30.000', 'unit_price' => '200.00']]]);
                    }
                    if ($state === 'awarded') {
                        app(AllocateAward::class)->execute($tender, $buyer, ['request_key' => 'demo-second', 'reason' => 'DEMO Completar pendientes', 'lines' => [['bid_item_id' => $bids[0]->items[1]->id, 'quantity' => '20.000', 'unit_price' => '210.00']]]);
                    }
                }
            });
        });
    }
}
