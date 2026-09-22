<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureFinancialIdempotency
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = $request->header('X-Idempotency-Key')
            ?: $request->input('_idempotency_key')
            ?: $request->input('idempotency_key');

        if (blank($idempotencyKey)) {
            return $next($request);
        }

        $idempotencyKey = substr(trim((string) $idempotencyKey), 0, 64);
        $requestHash = $this->calculateRequestHash($request);
        $userId = $request->user()?->id;

        // Atomically claim the idempotency key
        try {
            DB::table('idempotency_keys')->insert([
                'key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'status' => 'processing',
                'user_id' => $userId,
                'expires_at' => now()->addHours(24),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $isOriginalClaim = true;
        } catch (\Throwable $e) {
            $isOriginalClaim = false;
        }

        if (! $isOriginalClaim) {
            $record = DB::table('idempotency_keys')->where('key', $idempotencyKey)->first();

            if (! $record) {
                return $next($request);
            }

            // Check payload conflict
            if ($record->request_hash !== $requestHash) {
                return $this->conflictResponse($request);
            }

            // If processing, wait briefly for concurrent execution
            if ($record->status === 'processing') {
                $record = $this->waitForCompletion($idempotencyKey);
                if (! $record || $record->status !== 'completed') {
                    if ($record && $record->request_hash !== $requestHash) {
                        return $this->conflictResponse($request);
                    }

                    return $this->conflictResponse($request, 'A duplicate financial request is currently processing. Please wait.');
                }
            }

            if ($record->status === 'completed') {
                return $this->reconstructResponse($record);
            }

            // If failed, allow retry
            DB::table('idempotency_keys')
                ->where('key', $idempotencyKey)
                ->update([
                    'status' => 'processing',
                    'updated_at' => now(),
                ]);
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            DB::table('idempotency_keys')
                ->where('key', $idempotencyKey)
                ->update([
                    'status' => 'failed',
                    'updated_at' => now(),
                ]);

            throw $e;
        }

        if ($response->getStatusCode() < 400) {
            $this->storeCompletedOutcome($idempotencyKey, $response, $request);
        } else {
            DB::table('idempotency_keys')
                ->where('key', $idempotencyKey)
                ->update([
                    'status' => 'failed',
                    'response_code' => $response->getStatusCode(),
                    'updated_at' => now(),
                ]);
        }

        return $response;
    }

    private function calculateRequestHash(Request $request): string
    {
        $payload = $request->except(['_token', '_idempotency_key', 'idempotency_key']);
        ksort($payload);

        return hash('sha256', $request->method().'|'.$request->path().'|'.json_encode($payload));
    }

    private function waitForCompletion(string $key): ?object
    {
        $retries = 25; // 25 * 100ms = 2.5s
        while ($retries > 0) {
            usleep(100000);
            $record = DB::table('idempotency_keys')->where('key', $key)->first();
            if ($record && $record->status !== 'processing') {
                return $record;
            }
            $retries--;
        }

        return DB::table('idempotency_keys')->where('key', $key)->first();
    }

    private function conflictResponse(Request $request, string $message = 'Idempotency key conflict: request payload does not match original request.'): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'conflict',
                'message' => $message,
            ], 409);
        }

        return response($message, 409);
    }

    private function storeCompletedOutcome(string $key, Response $response, Request $request): void
    {
        $data = [
            'status' => 'completed',
            'response_code' => $response->getStatusCode(),
            'updated_at' => now(),
        ];

        if ($response instanceof RedirectResponse) {
            $data['redirect_url'] = $response->getTargetUrl();
            if ($request->hasSession()) {
                $flashed = [
                    'success' => $request->session()->get('success'),
                    'warning' => $request->session()->get('warning'),
                    'error' => $request->session()->get('error'),
                    'info' => $request->session()->get('info'),
                ];
                $data['session_flash'] = json_encode(array_filter($flashed));
            }
        } elseif ($response instanceof JsonResponse) {
            $data['response_body'] = $response->getContent();
        }

        DB::table('idempotency_keys')->where('key', $key)->update($data);
    }

    private function reconstructResponse(object $record): Response
    {
        if (filled($record->redirect_url)) {
            $redirect = redirect()->to($record->redirect_url, $record->response_code ?: 302);
            if (filled($record->session_flash)) {
                $flashed = json_decode($record->session_flash, true);
                if (is_array($flashed)) {
                    foreach ($flashed as $k => $v) {
                        $redirect->with($k, $v);
                    }
                }
            }

            return $redirect;
        }

        if (filled($record->response_body)) {
            return response($record->response_body, $record->response_code ?: 200)
                ->header('Content-Type', 'application/json');
        }

        return response('', $record->response_code ?: 200);
    }
}
