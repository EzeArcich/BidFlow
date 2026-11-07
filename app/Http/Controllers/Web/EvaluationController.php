<?php

namespace App\Http\Controllers\Web;

use App\Domain\Awards\Actions\AllocateAward;
use App\Domain\Tenders\Actions\CloseTender;
use App\Domain\Tenders\Models\Tender;
use App\Domain\Tenders\Queries\EvaluateTender;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PartialAwardRequest;
use Illuminate\Http\Request;

class EvaluationController extends Controller
{
    public function show(Request $request, Tender $tender, EvaluateTender $query)
    {
        return view('tenders.evaluation', ['tender' => $tender, 'evaluation' => $query->execute($tender, $request->user())]);
    }

    public function close(Request $request, Tender $tender, CloseTender $action)
    {
        $request->validate(['confirmation' => 'accepted']);
        $action->execute($tender, $request->user());

        return back()->with('success', 'Recepción cerrada. Ya podés adjudicar cantidades.');
    }

    public function allocate(PartialAwardRequest $request, Tender $tender, AllocateAward $action)
    {
        $request->validate(['confirmation' => 'accepted']);
        $action->execute($tender, $request->user(), $request->validated());

        return redirect()->route('tenders.evaluation', $tender)->with('success', 'Adjudicación confirmada y órdenes emitidas por proveedor.');
    }
}
