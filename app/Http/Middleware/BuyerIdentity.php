<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class BuyerIdentity
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user() instanceof User, 403);

        return $next($request);
    }
}
