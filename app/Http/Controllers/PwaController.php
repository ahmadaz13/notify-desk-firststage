<?php

namespace App\Http\Controllers;

use App\Support\Pwa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Public, non-personal PWA endpoints (P9.1): manifest, service worker and offline page.
 * None of them read the session user or any business data.
 */
class PwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        return response()
            ->json(Pwa::manifest(), 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function serviceWorker(): Response
    {
        return response()
            ->view('pwa.service-worker', [
                'cacheName' => Pwa::CACHE_PREFIX.Pwa::cacheVersion(),
                'cachePrefix' => Pwa::CACHE_PREFIX,
                'offlineUrl' => parse_url(route('pwa.offline'), PHP_URL_PATH),
                'precacheUrls' => Pwa::precacheUrls(),
                'staticPathPrefixes' => Pwa::staticPathPrefixes(),
            ])
            ->header('Content-Type', 'application/javascript; charset=UTF-8')
            // The browser must re-check the worker on every navigation so new builds are picked up.
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    public function offline(): Response
    {
        return response()->view('pwa.offline')->header('Cache-Control', 'no-cache');
    }
}
