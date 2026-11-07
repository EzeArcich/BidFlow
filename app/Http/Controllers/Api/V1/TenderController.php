<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tenders\Actions\PublishTender;
use App\Domain\Tenders\Actions\SaveTender;
use App\Domain\Tenders\Models\Tender;
use App\Domain\Tenders\Queries\ListTenders;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListRecordsRequest;
use App\Http\Requests\Api\V1\SaveTenderRequest;
use App\Http\Resources\Api\V1\TenderResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TenderController extends Controller
{
    public function index(ListRecordsRequest $request)
    {
        Gate::authorize('viewAny', Tender::class);

        return TenderResource::collection(app(ListTenders::class)->execute($request->validated()));
    }

    public function store(SaveTenderRequest $request, SaveTender $action)
    {
        return (new TenderResource($action->create($request->user(), $request->validated())->refresh()))->response()->setStatusCode(201);
    }

    public function show(Tender $tender)
    {
        Gate::authorize('view', $tender);

        return new TenderResource($tender->load(['items' => fn ($q) => $q->orderBy('position'), 'invitations']));
    }

    public function update(SaveTenderRequest $request, Tender $tender, SaveTender $action)
    {
        return new TenderResource($action->update($tender, $request->user(), $request->validated()));
    }

    public function publish(Request $request, Tender $tender, PublishTender $action)
    {
        Gate::authorize('publish', $tender);

        return new TenderResource($action->execute($tender, $request->user()));
    }
}
