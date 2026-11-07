<?php

namespace App\Http\Middleware;

use App\Domain\Organizations\TenantContext;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ResolveOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user() instanceof User, 403);
        $organizations = $request->user()->organizations()->where('organizations.status', 'active');
        $selected = $request->header('X-Organization-ID');
        if ($selected !== null) {
            if (! Str::isUlid($selected)) {
                throw ValidationException::withMessages(['organization' => 'X-Organization-ID must be a ULID.']);
            }
            $organization = $organizations->whereKey($selected)->first();
            abort_unless($organization, 403, 'You do not belong to this active organization.');
        } else {
            $available = $organizations->limit(2)->get();
            abort_if($available->isEmpty(), 403, 'An active organization membership is required.');
            if ($available->count() !== 1) {
                throw ValidationException::withMessages(['organization' => 'Select an organization with X-Organization-ID.']);
            }
            $organization = $available->first();
        }

        return app(TenantContext::class)->run($organization->id, fn () => $next($request));
    }
}
