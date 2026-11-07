<?php

namespace Database\Factories;

use App\Domain\Organizations\TenantContext;
use App\Domain\Tenders\Models\Tender;
use App\Domain\Tenders\Models\TenderAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TenderAttachment> */
class TenderAttachmentFactory extends Factory
{
    protected $model = TenderAttachment::class;

    public function definition(): array
    {
        return [
            'organization_id' => fn () => app(TenantContext::class)->id(),
            'tender_id' => Tender::factory(),
            'disk' => 'local',
            'path' => 'demo/placeholder.txt',
            'original_name' => 'demo.txt',
            'mime_type' => 'text/plain',
            'size' => 0,
        ];
    }
}
