<?php

namespace App\Http\Controllers\Web;

use App\Domain\Suppliers\Models\Supplier;
use App\Domain\Suppliers\Queries\AvailableSuppliers;
use App\Domain\Tenders\Actions\InviteSuppliers;
use App\Domain\Tenders\Models\Tender;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\InviteSuppliersRequest;
use App\Http\Requests\Api\V1\ListRecordsRequest;
use Illuminate\Support\Facades\Gate;

class SupplierController extends Controller
{
    public function index(ListRecordsRequest $request, AvailableSuppliers $query, ?Tender $tender = null)
    {
        Gate::authorize('viewAny', Supplier::class);
        if ($tender) {
            Gate::authorize('update', $tender);
        }

        return view('suppliers.index', [
            'suppliers' => $query->execute($request->validated()), 'tender' => $tender,
            'invited' => $tender?->invitations()->pluck('supplier_id')->all() ?? [],
        ]);
    }

    public function store(InviteSuppliersRequest $request, Tender $tender, InviteSuppliers $action)
    {
        $action->execute($tender, $request->user(), $request->validated('invitations'));

        return redirect()->route('tenders.show', $tender)->with('success', 'Invitaciones guardadas. Todavía no se envían correos.');
    }
}
