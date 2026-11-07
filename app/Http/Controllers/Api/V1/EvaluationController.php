<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Awards\Actions\AllocateAward;
use App\Domain\Tenders\Actions\CloseTender;
use App\Domain\Tenders\Models\Tender;
use App\Domain\Tenders\Queries\EvaluateTender;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PartialAwardRequest;
use App\Http\Resources\Api\V1\AwardResource;
use App\Http\Resources\Api\V1\TenderResource;
use App\Http\Resources\Api\V1\WorkOrderResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EvaluationController extends Controller
{
    public function show(Request $request, Tender $tender, EvaluateTender $query)
    {
        return response()->json(['data' => $query->execute($tender, $request->user())]);
    }

    public function close(Request $request, Tender $tender, CloseTender $action)
    {
        return new TenderResource($action->execute($tender, $request->user()));
    }

    public function allocate(PartialAwardRequest $request, Tender $tender, AllocateAward $action)
    {
        return new AwardResource($action->execute($tender, $request->user(), $request->validated()));
    }

    public function history(Tender $tender)
    {
        Gate::authorize('view', $tender);

        return AwardResource::collection($tender->awards()->with(['lines', 'workOrders.lines'])->orderBy('awarded_at')->get());
    }

    public function orders(Tender $tender)
    {
        Gate::authorize('view', $tender);

        return WorkOrderResource::collection($tender->workOrders()->with('lines')->get());
    }

    public function order(WorkOrder $workOrder)
    {
        Gate::authorize('view', $workOrder);

        return new WorkOrderResource($workOrder->load('lines'));
    }
}
