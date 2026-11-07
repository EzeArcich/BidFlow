<?php

namespace App\Http\Controllers\Web;

use App\Domain\Tenders\Models\Tender;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __invoke()
    {
        Gate::authorize('viewAny', Tender::class);
        $counts = Tender::query()->select('status')->selectRaw('count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('dashboard', ['counts' => $counts, 'recent' => Tender::orderByDesc('created_at')->orderByDesc('id')->limit(6)->get()]);
    }
}
