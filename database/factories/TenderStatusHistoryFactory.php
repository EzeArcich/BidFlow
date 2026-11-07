<?php

namespace Database\Factories;

use App\Domain\Organizations\TenantContext;
use App\Domain\Tenders\Models\Tender;
use App\Domain\Tenders\Models\TenderStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TenderStatusHistory> */
class TenderStatusHistoryFactory extends Factory
{
    protected $model = TenderStatusHistory::class;

    public function definition(): array
    {
        return [
            'organization_id' => fn () => app(TenantContext::class)->id(),
            'tender_id' => Tender::factory(),
            'to_status' => 'draft',
            'occurred_at' => now(),
        ];
    }
}
