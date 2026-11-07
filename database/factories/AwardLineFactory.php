<?php

namespace Database\Factories;

use App\Domain\Awards\Models\Award;
use App\Domain\Awards\Models\AwardLine;
use App\Domain\Bids\Models\Bid;
use App\Domain\Bids\Models\BidItem;
use App\Domain\Organizations\TenantContext;
use App\Domain\Shared\Decimal;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AwardLine> */
class AwardLineFactory extends Factory
{
    protected $model = AwardLine::class;

    public function definition(): array
    {
        return [
            'organization_id' => fn () => app(TenantContext::class)->id(),
            'bid_item_id' => BidItem::factory(),
            'bid_id' => fn (array $a) => BidItem::findOrFail($a['bid_item_id'])->bid_id,
            'tender_id' => fn (array $a) => BidItem::findOrFail($a['bid_item_id'])->tender_id,
            'tender_item_id' => fn (array $a) => BidItem::findOrFail($a['bid_item_id'])->tender_item_id,
            'supplier_id' => fn (array $a) => Bid::findOrFail($a['bid_id'])->supplier_id,
            'award_id' => fn (array $a) => Award::factory()->create(['bid_id' => null, 'legacy' => false, 'tender_id' => $a['tender_id'], 'request_key' => fake()->uuid(), 'request_hash' => str_repeat('a', 64)])->id,
            'currency' => 'ARS', 'quantity' => '1.000', 'unit_price' => '1000.00',
            'subtotal' => fn (array $a) => Decimal::subtotal($a['quantity'], $a['unit_price']),
            'snapshot' => ['is_demo' => true],
        ];
    }
}
