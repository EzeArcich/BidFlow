<?php

namespace Database\Factories;

use App\Domain\Organizations\TenantContext;
use App\Domain\Suppliers\Models\OrganizationSupplier;
use App\Domain\Tenders\Models\Tender;
use App\Domain\Tenders\Models\TenderInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TenderInvitation> */
class TenderInvitationFactory extends Factory
{
    protected $model = TenderInvitation::class;

    public function definition(): array
    {
        return [
            'organization_id' => fn () => app(TenantContext::class)->id(),
            'tender_id' => Tender::factory(),
            'supplier_id' => fn () => OrganizationSupplier::factory()->create()->supplier_id,
            'status' => 'pending',
            'expires_at' => fn (array $a) => Tender::findOrFail($a['tender_id'])->deadline_at,
        ];
    }
}
