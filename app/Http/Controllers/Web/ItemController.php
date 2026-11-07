<?php

namespace App\Http\Controllers\Web;

use App\Domain\Tenders\Actions\ManageTenderItem;
use App\Domain\Tenders\Models\Tender;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\SaveItemRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ItemController extends Controller
{
    public function create(Tender $tender)
    {
        Gate::authorize('update', $tender);

        return view('items.form', ['tender' => $tender, 'item' => null]);
    }

    public function edit(Tender $tender, string $item)
    {
        Gate::authorize('update', $tender);

        return view('items.form', ['tender' => $tender, 'item' => $tender->items()->whereKey($item)->firstOrFail()]);
    }

    public function store(SaveItemRequest $request, Tender $tender, ManageTenderItem $action)
    {
        $action->execute($tender, $request->user(), $request->domainData());

        return redirect()->route('tenders.show', $tender)->with('success', 'Ítem agregado.');
    }

    public function update(SaveItemRequest $request, Tender $tender, string $item, ManageTenderItem $action)
    {
        $action->execute($tender, $request->user(), $request->domainData(), $item);

        return redirect()->route('tenders.show', $tender)->with('success', 'Ítem actualizado.');
    }

    public function destroy(Request $request, Tender $tender, string $item, ManageTenderItem $action)
    {
        $action->execute($tender, $request->user(), [], $item, true);

        return redirect()->route('tenders.show', $tender)->with('success', 'Ítem eliminado.');
    }
}
