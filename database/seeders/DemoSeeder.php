<?php

namespace Database\Seeders;

use App\Domain\Awards\Models\Award;
use App\Domain\Awards\Models\AwardLine;
use App\Domain\Bids\Models\Bid;
use App\Domain\Bids\Models\BidItem;
use App\Domain\Integrations\Models\AuditLog;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\OrganizationMembership;
use App\Domain\Organizations\TenantContext;
use App\Domain\Suppliers\Models\OrganizationSupplier;
use App\Domain\Suppliers\Models\Supplier;
use App\Domain\Suppliers\Models\SupplierCapability;
use App\Domain\Suppliers\Models\SupplierContact;
use App\Domain\Suppliers\Models\SupplierLocation;
use App\Domain\Tenders\Models\RepairScope;
use App\Domain\Tenders\Models\Tender;
use App\Domain\Tenders\Models\TenderInvitation;
use App\Domain\Tenders\Models\TenderItem;
use App\Domain\Tenders\Models\TenderStatusHistory;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Domain\WorkOrders\Models\WorkOrderLine;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoSeeder extends Seeder
{
    public function run(string $profile = 'demo'): void
    {
        if (! app()->environment(['local', 'testing']) || ! config('bidding.demo_enabled')) {
            throw new RuntimeException('Demo seeding requires local/testing and BIDDING_DEMO_ENABLED=true.');
        }
        $size = config("bidding.profiles.{$profile}");
        if (! is_array($size)) {
            throw new RuntimeException('Unknown demo profile.');
        }
        if (Organization::where('name', 'like', 'DEMO Bidflow %')->exists()) {
            throw new RuntimeException('Demo already exists. Use a fresh local database to repeat it.');
        }

        fake()->seed(20261106);
        fake()->unique(true);

        DB::transaction(function () use ($size): void {
            $suppliers = collect();
            for ($i = 0; $i < $size['suppliers']; $i++) {
                $type = ['parts', 'workshop', 'mixed'][$i % 3];
                $supplier = Supplier::factory()->create([
                    'type' => $type,
                    'legal_name' => 'DEMO Proveedor '.($i + 1).' SA',
                    'trade_name' => 'DEMO Proveedor '.($i + 1),
                ]);
                SupplierContact::factory()->create(['supplier_id' => $supplier->id, 'email' => 'proveedor'.($i + 1).'@example.test']);
                SupplierLocation::factory()->create(['supplier_id' => $supplier->id]);
                foreach ($type === 'mixed' ? ['parts', 'bodywork', 'painting'] : ($type === 'parts' ? ['parts'] : ['bodywork', 'painting']) as $capability) {
                    SupplierCapability::factory()->create(['supplier_id' => $supplier->id, 'capability' => $capability]);
                }
                $suppliers->push($supplier);
            }

            $organizations = collect();
            for ($i = 0; $i < 2; $i++) {
                $organization = Organization::factory()->create(['name' => 'DEMO Bidflow '.($i + 1), 'type' => $i === 0 ? 'study' : 'insurer']);
                $user = User::factory()->create(['name' => 'DEMO Operador '.($i + 1), 'email' => 'operador'.($i + 1).'@example.test', 'password' => bin2hex(random_bytes(32))]);
                app(TenantContext::class)->run($organization->id, function () use ($user, $suppliers): void {
                    OrganizationMembership::factory()->create(['user_id' => $user->id, 'role' => 'owner']);
                    foreach ($suppliers as $supplier) {
                        OrganizationSupplier::factory()->create(['supplier_id' => $supplier->id]);
                    }
                });
                $organizations->push([$organization, $user]);
            }

            for ($i = 0; $i < $size['tenders']; $i++) {
                [$organization, $user] = $organizations[$i % 2];
                // Alternate independently of organization, so both tenants receive both types.
                $type = intdiv($i, 2) % 2 === 0 ? 'parts' : 'repair';
                $status = ['draft', 'published', 'closed', 'awarded', 'expired'][$i % 5];
                app(TenantContext::class)->run($organization->id, function () use ($i, $type, $status, $user, $suppliers, $size): void {
                    $eligible = $suppliers->filter(fn (Supplier $supplier) => in_array($supplier->type->value, $type === 'parts' ? ['parts', 'mixed'] : ['workshop', 'mixed']))->take($size['bids_per_tender']);
                    $this->createTender($i, $type, $status, $user, $eligible);
                });
            }
        });
    }

    private function createTender(int $index, string $type, string $status, User $user, $suppliers): void
    {
        $anchor = now()->startOfDay();
        $deadline = in_array($status, ['draft', 'published']) ? $anchor->addDays(7) : $anchor->subDays(1);
        $tender = Tender::factory()->create([
            'type' => $type, 'status' => $status,
            'title' => 'DEMO '.($type === 'parts' ? 'Repuestos' : 'Reparación').' #'.($index + 1),
            'deadline_at' => $deadline, 'created_by' => $user->id,
            'source_system' => 'demo', 'external_claim_id' => (string) ($index + 1),
            'external_reference' => 'DEMO-SIN-'.str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT),
        ]);
        $items = collect();
        foreach ($type === 'parts' ? ['Paragolpes delantero', 'Óptica delantera'] : ['Reparación y pintura de paragolpes', 'Alineación y control de frente'] as $position => $description) {
            $items->push(TenderItem::factory()->create([
                'tender_id' => $tender->id, 'position' => $position + 1,
                'description' => 'DEMO '.$description, 'unit' => $type === 'parts' ? 'unit' : 'service',
                'reference_code' => $type === 'parts' ? 'DEMO-'.($position + 1) : null,
                'attributes' => ['is_demo' => true, 'compatibility_verified' => false],
            ]));
        }
        if ($type === 'repair') {
            RepairScope::factory()->create(['tender_id' => $tender->id]);
        }

        $path = match ($status) {
            'draft' => ['draft'],
            'published' => ['draft', 'published'],
            'closed' => ['draft', 'published', 'closed'],
            'awarded' => ['draft', 'published', 'closed', 'awarded'],
            'expired' => ['draft', 'published', 'expired'],
        };
        $previous = null;
        foreach ($path as $step => $state) {
            TenderStatusHistory::factory()->create([
                'tender_id' => $tender->id, 'actor_id' => $user->id,
                'from_status' => $previous, 'to_status' => $state,
                'reason' => 'Estado precargado de simulación',
                'occurred_at' => match ($state) {
                    'draft' => $anchor->subDays(5),
                    'published' => $anchor->subDays(4),
                    'closed' => $anchor->subDays(2),
                    'awarded' => $anchor->subDays(2)->addHour(),
                    'expired' => $deadline->addHour(),
                },
                'metadata' => ['is_demo' => true],
            ]);
            $previous = $state;
        }
        AuditLog::factory()->create([
            'actor_id' => $user->id, 'action' => 'demo.seeded',
            'entity_type' => 'tender', 'entity_id' => $tender->id,
            'after' => ['status' => $status, 'is_demo' => true],
        ]);
        if ($status === 'draft') {
            return;
        }

        $winningBid = null;
        foreach ($suppliers->values() as $supplierIndex => $supplier) {
            $contact = SupplierContact::where('supplier_id', $supplier->id)->firstOrFail();
            $invitation = TenderInvitation::factory()->create([
                'tender_id' => $tender->id, 'supplier_id' => $supplier->id, 'contact_id' => $contact->id,
                'status' => 'responded', 'expires_at' => $deadline,
                'sent_at' => $anchor->subDays(4)->addHour(), 'responded_at' => $anchor->subDays(3),
            ]);
            $bid = Bid::factory()->create([
                'invitation_id' => $invitation->id,
                'status' => $status === 'awarded' ? ($supplierIndex === 0 ? 'accepted' : 'rejected') : 'submitted',
                'submitted_at' => $anchor->subDays(3), 'delivery_days' => 3 + $supplierIndex,
                'warranty' => 'DEMO garantía ficticia de 30 días',
                'notes' => 'SIMULACIÓN: montos ficticios finales para comparación interna; impuestos pendientes de definición.',
            ]);
            $total = '0.00';
            foreach ($items as $itemIndex => $item) {
                $price = (string) (25000 + $index * 100 + $supplierIndex * 2500 + $itemIndex * 10000);
                $subtotal = bcmul($item->quantity, $price, 2);
                BidItem::factory()->create([
                    'bid_id' => $bid->id, 'tender_id' => $tender->id, 'tender_item_id' => $item->id,
                    'description' => $item->description, 'quantity' => $item->quantity,
                    'unit_price' => $price, 'subtotal' => $subtotal,
                ]);
                $total = bcadd($total, $subtotal, 2);
            }
            $bid->update(['total_amount' => $total, 'submitted_snapshot' => ['legacy' => true, 'currency' => $bid->currency, 'total_amount' => $total, 'lines' => $bid->items()->get()->toArray()]]);
            if ($supplierIndex === 0) {
                $winningBid = $bid;
            }
        }
        if ($status === 'awarded') {
            $award = Award::factory()->create([
                'bid_id' => $winningBid->id, 'tender_id' => $tender->id, 'awarded_by' => $user->id,
                'awarded_at' => $anchor->subDays(2)->addHour(),
                'snapshot' => ['currency' => 'ARS', 'total_amount' => $winningBid->total_amount, 'delivery_days' => $winningBid->delivery_days, 'warranty' => $winningBid->warranty, 'is_demo' => true],
            ]);
            $award->update(['total_amount' => $winningBid->total_amount]);
            foreach ($winningBid->items()->get() as $line) {
                AwardLine::create(['award_id' => $award->id, 'tender_id' => $tender->id, 'bid_id' => $winningBid->id, 'bid_item_id' => $line->id, 'tender_item_id' => $line->tender_item_id, 'supplier_id' => $winningBid->supplier_id, 'currency' => 'ARS', 'quantity' => $line->quantity, 'unit_price' => $line->unit_price, 'subtotal' => $line->subtotal, 'snapshot' => ['legacy' => true, 'description' => $line->description]]);
            }
            $order = WorkOrder::factory()->create([
                'supplier_id' => $winningBid->supplier_id, 'tender_id' => $tender->id,
                'award_id' => $award->id, 'number' => 'DEMO-'.$anchor->year.'-'.str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT),
                'expected_at' => $anchor->addDays($winningBid->delivery_days),
            ]);
            foreach ($award->lines()->get() as $line) {
                WorkOrderLine::create(['work_order_id' => $order->id, 'award_id' => $award->id, 'supplier_id' => $line->supplier_id, 'award_line_id' => $line->id, 'quantity' => $line->quantity, 'unit_price' => $line->unit_price, 'subtotal' => $line->subtotal, 'currency' => 'ARS', 'snapshot' => $line->snapshot]);
            }
        }
    }
}
