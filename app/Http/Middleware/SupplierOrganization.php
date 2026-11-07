<?php

namespace App\Http\Middleware;

use App\Domain\Organizations\TenantContext;
use App\Domain\Shared\ActivityContext;
use App\Domain\Suppliers\Models\SupplierUser;
use Closure;
use Illuminate\Http\Request;

class SupplierOrganization
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user instanceof SupplierUser && $user->active, 403);
        $supplier = $user->supplier()->where('status', 'active')->first();
        abort_unless($supplier, 403);
        $organization = $supplier->organizations()->where('organizations.status', 'active')->wherePivot('status', 'active')->whereKey($request->route('organization'))->first();
        abort_unless($organization, 404);
        $request->attributes->set('supplierOrganization', $organization);

        return app(ActivityContext::class)->run('portal', fn () => app(TenantContext::class)->run($organization->id, fn () => $next($request)));
    }
}
