<?php

namespace App\Http\Middleware;

use App\Domain\Organizations\TenantContext;
use App\Domain\Shared\ActivityContext;
use Closure;
use Illuminate\Http\Request;

class ResolveWebOrganization
{
    public function handle(Request $request, Closure $next)
    {
        $organizations = $request->user()->organizations()->where('organizations.status', 'active');
        $selected = $request->session()->get('active_organization_id');
        if ($selected) {
            $organization = $organizations->whereKey($selected)->first();
            if (! $organization) {
                $request->session()->forget('active_organization_id');

                return redirect()->route('organizations.index')->withErrors(['organization' => 'Tu acceso a la organización cambió. Seleccioná una organización disponible.']);
            }
        } else {
            $available = $organizations->limit(2)->get();
            if ($available->count() !== 1) {
                return redirect()->route('organizations.index');
            }
            $organization = $available->first();
            $request->session()->put('active_organization_id', $organization->id);
        }
        $request->attributes->set('activeOrganization', $organization);

        return app(ActivityContext::class)->run('web',
            fn () => app(TenantContext::class)->run($organization->id, fn () => $next($request)));
    }
}
