<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Today → Client Workspace → back to Today (P11).
 *
 * Workspace action sheets opened from a Today card carry `_return_to` (today|work) and `_return_scope`.
 * When the existing workflow controller finishes and redirects to the plain Client Workspace, the
 * user is sent back to the same Today view instead; the controller's success flash is kept.
 * Never applies when validation failed, and never overrides a redirect that deliberately leads to
 * a next step (a query/fragment the controller added itself). Destination is always the fixed
 * dashboard route, so this cannot become an open redirect.
 */
class ReturnToToday
{
    private const WORKSPACE_QUERY_KEYS = ['open', 'from', 'scope', 'appointment', 'follow_up'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $returnTo = $request->input('_return_to');
        if (! in_array($returnTo, ['today', 'work'], true) || ! $response instanceof RedirectResponse) {
            return $response;
        }

        if ($request->hasSession() && in_array('errors', (array) $request->session()->get('_flash.new', []), true)) {
            return $response;
        }

        if (! $this->isPlainWorkspace($response->getTargetUrl())) {
            return $response;
        }

        $response->setTargetUrl(route('dashboard', array_filter([
            'mode' => $returnTo === 'work' ? 'work' : null,
            'scope' => $request->input('_return_scope') === 'my' ? 'my' : null,
        ])));

        return $response;
    }

    /** The client workspace itself, or the Today deep link it was opened with (a `back()` target). */
    private function isPlainWorkspace(string $url): bool
    {
        $parts = parse_url($url);
        if (! preg_match('#/clients/\d+$#', $parts['path'] ?? '') || isset($parts['fragment'])) {
            return false;
        }

        if (! isset($parts['query']) || $parts['query'] === '') {
            return true;
        }

        parse_str($parts['query'], $query);

        return isset($query['from']) && array_diff(array_keys($query), self::WORKSPACE_QUERY_KEYS) === [];
    }
}
