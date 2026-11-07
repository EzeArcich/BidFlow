<?php

namespace Database\Factories;

use App\Domain\Awards\Models\Award;
use App\Domain\Bids\Models\Bid;
use App\Domain\Organizations\TenantContext;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Award> */
class AwardFactory extends Factory
{
    protected $model = Award::class;

    public function definition(): array
    {
        return [
            'legacy' => true,
            'organization_id' => fn () => app(TenantContext::class)->id(),
            'bid_id' => Bid::factory()->state(['status' => 'accepted']),
            'tender_id' => fn (array $a) => Bid::findOrFail($a['bid_id'])->tender_id,
            'awarded_by' => User::factory(),
            'awarded_at' => now(),
            'reason' => 'DEMO adjudicación ficticia',
            'snapshot' => ['currency' => 'ARS', 'total_amount' => '0.00', 'is_demo' => true],
        ];
    }
}
