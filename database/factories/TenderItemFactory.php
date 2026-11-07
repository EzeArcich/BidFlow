<?php

namespace Database\Factories;

use App\Domain\Organizations\TenantContext;
use App\Domain\Tenders\Models\Tender;
use App\Domain\Tenders\Models\TenderItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;

/** @extends Factory<TenderItem> */
class TenderItemFactory extends Factory
{
    protected $model = TenderItem::class;

    public function configure(): static
    {
        return $this->sequence(fn (Sequence $sequence) => ['position' => $sequence->index + 1]);
    }

    public function definition(): array
    {
        return [
            'organization_id' => fn () => app(TenantContext::class)->id(),
            'tender_id' => Tender::factory(),
            'position' => 1,
            'description' => 'DEMO Paragolpes — compatibilidad no verificada',
            'quantity' => '1.000',
            'unit' => 'unit',
            'reference_code' => 'DEMO-001',
        ];
    }
}
