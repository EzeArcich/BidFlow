<?php

namespace Database\Factories;

use App\Domain\Bids\Models\Bid;
use App\Domain\Organizations\TenantContext;
use App\Domain\Tenders\Models\TenderInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Bid> */
class BidFactory extends Factory
{
    protected $model = Bid::class;

    public function definition(): array
    {
        return [
            'organization_id' => fn () => app(TenantContext::class)->id(),
            'invitation_id' => TenderInvitation::factory(),
            'tender_id' => fn (array $a) => TenderInvitation::findOrFail($a['invitation_id'])->tender_id,
            'supplier_id' => fn (array $a) => TenderInvitation::findOrFail($a['invitation_id'])->supplier_id,
            'status' => 'draft',
            'currency' => 'ARS',
            'total_amount' => '0.00',
            'delivery_days' => 5,
        ];
    }
}
