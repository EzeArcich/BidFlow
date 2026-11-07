<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Awards\Models\AwardLine;
use App\Domain\Bids\Actions\SaveSupplierBid;
use App\Domain\Bids\Models\Bid;
use App\Domain\Organizations\TenantContext;
use App\Domain\Suppliers\Models\SupplierUser;
use App\Domain\Suppliers\Queries\PortalAccess;
use App\Domain\Tenders\Models\TenderInvitation;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\SaveBidRequest;
use App\Http\Resources\Api\V1\BidResource;
use App\Http\Resources\Api\V1\WorkOrderResource;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function home(Request $request)
    {
        $user = $request->user();
        abort_unless($user instanceof SupplierUser && $user->active, 403);
        $supplier = $user->supplier()->where('status', 'active')->first();
        abort_unless($supplier, 403);
        $organizations = $supplier->organizations()->where('organizations.status', 'active')->wherePivot('status', 'active')->get();
        $invitations = collect();
        foreach ($organizations as $org) {
            app(TenantContext::class)->run($org->id, function () use ($org, $user, $invitations) {
                foreach (TenderInvitation::where('supplier_id', $user->supplier_id)->where('status', '!=', 'revoked')->whereHas('tender', fn ($q) => $q->where('status', '!=', 'draft'))->with('tender')->get() as $inv) {
                    $invitations->push(['organization_id' => $org->id, 'organization_name' => $org->name, 'id' => $inv->id, 'status' => $inv->status->value, 'tender_title' => $inv->tender->title, 'tender_status' => $inv->tender->status->value, 'deadline_at' => $inv->tender->deadline_at->toIso8601String()]);
                }
            });
        }

        return $request->is('api/*') ? response()->json(['data' => $invitations]) : view('portal.home', compact('invitations'));
    }

    public function show(Request $request, string $organization, string $invitation, PortalAccess $access)
    {
        $inv = $access->invitation($request->user(), $invitation);
        $inv->load('tender.items');
        $bid = Bid::where('invitation_id', $inv->id)->where('supplier_id', $request->user()->supplier_id)->with('items')->first();
        $awards = AwardLine::where('tender_id', $inv->tender_id)->where('supplier_id', $request->user()->supplier_id)->with('award')->get();
        $orders = WorkOrder::where('tender_id', $inv->tender_id)->where('supplier_id', $request->user()->supplier_id)->with('lines')->get();
        if ($request->is('api/*')) {
            return response()->json(['data' => [
                'invitation_id' => $inv->id, 'tender_id' => $inv->tender_id, 'title' => $inv->tender->title, 'description' => $inv->tender->description, 'conditions' => $inv->tender->conditions, 'currency' => $inv->tender->currency, 'status' => $inv->tender->status->value, 'deadline_at' => $inv->tender->deadline_at->toIso8601String(),
                'items' => $inv->tender->items->map(fn ($item) => $item->only(['id', 'description', 'quantity', 'unit', 'attributes', 'notes'])),
                'bid' => $bid ? new BidResource($bid) : null,
                'awards' => $awards->map(fn ($l) => $l->only(['id', 'award_id', 'tender_item_id', 'quantity', 'unit_price', 'subtotal', 'currency', 'snapshot'])),
                'orders' => WorkOrderResource::collection($orders),
            ]]);
        }

        return view('portal.invitation', ['invitation' => $inv, 'tender' => $inv->tender, 'bid' => $bid, 'awards' => $awards, 'orders' => $orders, 'organization' => $organization]);
    }

    public function save(SaveBidRequest $request, string $organization, string $invitation, SaveSupplierBid $action)
    {
        $bid = $action->execute($request->user(), $invitation, $request->validated());

        return $request->is('api/*') ? new BidResource($bid) : redirect()->route('portal.invitation', [$organization, $invitation])->with('success', 'Oferta guardada en borrador.');
    }

    public function submit(Request $request, string $organization, string $invitation, SaveSupplierBid $action)
    {
        if (! $request->is('api/*')) {
            $request->validate(['confirmation' => 'accepted']);
        }
        $bid = $action->execute($request->user(), $invitation, [], true);

        return $request->is('api/*') ? new BidResource($bid) : redirect()->route('portal.invitation', [$organization, $invitation])->with('success', 'Oferta presentada. Sus condiciones ya no pueden modificarse.');
    }
}
