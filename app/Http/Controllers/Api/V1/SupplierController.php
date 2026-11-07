<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Suppliers\Models\Supplier;
use App\Domain\Suppliers\Queries\AvailableSuppliers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListRecordsRequest;
use App\Http\Resources\Api\V1\SupplierResource;
use Illuminate\Support\Facades\Gate;

class SupplierController extends Controller
{
    public function index(ListRecordsRequest $request)
    {
        Gate::authorize('viewAny', Supplier::class);

        return SupplierResource::collection(app(AvailableSuppliers::class)->execute($request->validated()));
    }
}
