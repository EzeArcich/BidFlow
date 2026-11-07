<?php

namespace App\Domain\Tenders\Actions;

use App\Domain\Tenders\Models\Tender;
use App\Domain\Tenders\Models\TenderItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ManageTenderItem
{
    public function __construct(private WithDraftTender $draft, private RecordTenderActivity $audit) {}

    public function execute(Tender $tender, User $actor, array $data, ?string $itemId = null, bool $delete = false): ?TenderItem
    {
        return $this->draft->execute($tender, $actor, function (Tender $locked) use ($actor, $data, $itemId, $delete): ?TenderItem {
            $item = $itemId ? $locked->items()->whereKey($itemId)->firstOrFail() : null;
            if ($delete) {
                abort_if($item->bidItems()->exists() || $locked->attachments()->where('tender_item_id', $item->id)->exists(), 409, 'This item has dependent records.');
                $item->delete();
                $this->audit->execute($locked, $actor, 'tender.item_deleted', ['item_id' => $item->id]);

                return null;
            }
            $position = $data['position'] ?? $item?->position ?? (1 + (int) $locked->items()->max('position'));
            if ($locked->items()->where('position', $position)->when($item, fn ($q) => $q->where('id', '!=', $item->id))->exists()) {
                throw ValidationException::withMessages(['position' => 'This position is already used in this tender.']);
            }
            if ($item) {
                $item->update([...$data, 'position' => $position]);
            } else {
                $item = TenderItem::create([...$data, 'tender_id' => $locked->id, 'position' => $position]);
            }
            $this->audit->execute($locked, $actor, 'tender.item_saved', null, ['item_id' => $item->id]);

            return $item;
        });
    }
}
