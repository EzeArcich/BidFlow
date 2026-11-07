<?php

namespace App\Http\Controllers\Web;

use App\Domain\Tenders\Actions\PublishTender;
use App\Domain\Tenders\Actions\SaveTender;
use App\Domain\Tenders\Models\Tender;
use App\Domain\Tenders\Queries\ListTenders;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListRecordsRequest;
use App\Http\Requests\Web\PublishRequest;
use App\Http\Requests\Web\SaveTenderRequest;
use Illuminate\Support\Facades\Gate;

class TenderController extends Controller
{
    public function index(ListRecordsRequest $request, ListTenders $query)
    {
        Gate::authorize('viewAny', Tender::class);

        return view('tenders.index', ['tenders' => $query->execute($request->validated())]);
    }

    public function create()
    {
        Gate::authorize('create', Tender::class);

        return view('tenders.form', ['tender' => null]);
    }

    public function store(SaveTenderRequest $request, SaveTender $action)
    {
        $tender = $action->create($request->user(), $request->domainData());

        return redirect()->route('tenders.show', $tender)->with('success', 'Licitación creada. Agregá los ítems y elegí a tus proveedores.');
    }

    public function show(Tender $tender)
    {
        Gate::authorize('view', $tender);

        return view('tenders.show', ['tender' => $tender->load(['items' => fn ($q) => $q->orderBy('position'), 'invitations.supplier', 'invitations.contact'])]);
    }

    public function edit(Tender $tender)
    {
        Gate::authorize('update', $tender);

        return view('tenders.form', compact('tender'));
    }

    public function update(SaveTenderRequest $request, Tender $tender, SaveTender $action)
    {
        $action->update($tender, $request->user(), $request->domainData());

        return redirect()->route('tenders.show', $tender)->with('success', 'Cambios guardados.');
    }

    public function confirm(Tender $tender)
    {
        Gate::authorize('update', $tender);

        return view('tenders.publish', ['tender' => $tender->load(['items', 'invitations.supplier'])]);
    }

    public function publish(PublishRequest $request, Tender $tender, PublishTender $action)
    {
        Gate::authorize('publish', $tender);
        $action->execute($tender, $request->user());

        return redirect()->route('tenders.show', $tender)->with('success', 'Licitación publicada. Ya está disponible para el proceso de cotización; no se enviaron correos.');
    }
}
