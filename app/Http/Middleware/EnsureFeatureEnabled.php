<?php

namespace App\Http\Middleware;

use App\Support\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `feature:{name}` route middleware: a disabled feature behaves as if its routes do not exist (404).
 */
class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless(Features::enabled($feature), 404);

        return $next($request);
    }
}
