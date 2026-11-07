<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetWebLocale
{
    public function handle(Request $request, Closure $next)
    {
        $previous = app()->getLocale();
        app()->setLocale('es');
        try {
            return $next($request);
        } finally {
            app()->setLocale($previous);
        }
    }
}
