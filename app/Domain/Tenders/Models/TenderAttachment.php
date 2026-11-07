<?php

namespace App\Domain\Tenders\Models;

use App\Domain\Organizations\Models\Organization;
use App\Domain\Shared\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenderAttachment extends TenantModel
{
    protected $table = 'tender_attachments';

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function tenderItem(): BelongsTo
    {
        return $this->belongsTo(TenderItem::class);
    }
}
