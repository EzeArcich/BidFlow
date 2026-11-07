<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tenders\Actions\ManageTenderItem;
use App\Domain\Tenders\Models\Tender;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SaveItemRequest;
use App\Http\Resources\Api\V1\ItemResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ItemController extends Controller
{
    public function index(Tender $tender)
    {
        Gate::authorize('view', $tender);

        return ItemResource::collection($tender->items()->orderBy('position')->get());
    }

    public function store(SaveItemRequest $request, Tender $tender, ManageTenderItem $action)
    {
        return (new ItemResource($action->execute($tender, $request->user(), $request->validated())))->response()->setStatusCode(201);
    }

    public function update(SaveItemRequest $request, Tender $tender, string $item, ManageTenderItem $action)
    {
        return new ItemResource($action->execute($tender, $request->user(), $request->validated(), $item));
    }

    public function destroy(Request $request, Tender $tender, string $item, ManageTenderItem $action)
    {
        $action->execute($tender, $request->user(), [], $item, true);

        return response()->noContent();
    }
}
