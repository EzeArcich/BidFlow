<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tenders\Actions\InviteSuppliers;
use App\Domain\Tenders\Models\Tender;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\InviteSuppliersRequest;
use App\Http\Resources\Api\V1\InvitationResource;
use Illuminate\Support\Facades\Gate;

class InvitationController extends Controller
{
    public function index(Tender $tender)
    {
        Gate::authorize('view', $tender);

        return InvitationResource::collection($tender->invitations()->orderBy('id')->get());
    }

    public function store(InviteSuppliersRequest $request, Tender $tender, InviteSuppliers $action)
    {
        // 200 on first creation and repetition; existing invitations are returned unchanged.
        return InvitationResource::collection($action->execute($tender, $request->user(), $request->validated('invitations')));
    }
}
