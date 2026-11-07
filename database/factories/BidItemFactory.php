<?php

namespace Database\Factories;

use App\Domain\Bids\Models\Bid;
use App\Domain\Bids\Models\BidItem;
use App\Domain\Organizations\TenantContext;
use App\Domain\Tenders\Models\TenderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BidItem> */
class BidItemFactory extends Factory
{
    protected $model = BidItem::class;

    public function definition(): array
    {
        return [
            'organization_id' => fn () => app(TenantContext::class)->id(),
            'bid_id' => Bid::factory(),
            'tender_id' => fn (array $a) => Bid::findOrFail($a['bid_id'])->tender_id,
            'tender_item_id' => fn (array $a) => TenderItem::factory()->create([
                'tender_id' => $a['tender_id'],
                'position' => 1 + (int) TenderItem::where('tender_id', $a['tender_id'])->max('position'),
            ])->id,
            'description' => 'DEMO Oferta ficticia',
            'quantity' => '1.000',
            'unit_price' => '1000.00',
            'subtotal' => '1000.00',
        ];
    }
}
