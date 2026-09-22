<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpFoundation\Response;

class EnsureFinancialIdempotency
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $rawKey = $request->header('X-Idempotency-Key')
            ?: $request->input('_idempotency_key')
            ?: $request->input('idempotency_key');

        $userId = $request->user()?->id;

        if (blank($rawKey)) {
            // Irreversible financial POST routes must not silently bypass idempotency.
            // Synthesize an authoritative deterministic key based on authenticated user, route, and payload.
            $payload = $this->cleanedPayload($request);
            $idempotencyKey = 'syn_'.substr(hash('sha256', ($userId ?? 'anon').'|'.$request->method().'|'.$request->path().'|'.json_encode($payload)), 0, 48);
        } else {
            if (str_starts_with(trim((string) $rawKey), 'syn_')) {
                return $this->conflictResponse($request, 'Idempotency keys beginning with syn_ are reserved.');
            }
            $idempotencyKey = substr(trim((string) $rawKey), 0, 64);
        }

        $requestHash = $this->calculateRequestHash($request);

        // Completed synthetic requests retain replay protection for one day. Never
        // release an uncertain processing or committed-error claim automatically.
        if (str_starts_with($idempotencyKey, 'syn_')) {
            DB::table('idempotency_keys')
                ->where('key', $idempotencyKey)
                ->whereIn('status', ['completed', 'failed'])
                ->where('expires_at', '<=', now())
                ->delete();
        }

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
        } catch (UniqueConstraintViolationException $e) {
            $isOriginalClaim = false;
        }

        if (! $isOriginalClaim) {
            $record = DB::table('idempotency_keys')->where('key', $idempotencyKey)->first();

            if (! $record) {
                return $this->conflictResponse($request, 'Financial request claim could not be verified. Please retry.');
            }

            // Verify key owner (prevent cross-user key collision, response leak, or authorization bypass)
            if ($record->user_id !== null && (int) $record->user_id !== (int) $userId) {
                return $this->conflictResponse($request, 'Idempotency key conflict: key belongs to another authenticated user.');
            }

            // Check payload & route conflict
            if ($record->request_hash !== $requestHash) {
                return $this->conflictResponse($request);
            }

            // If committed with downstream error in prior attempt, block re-execution to prevent duplicate business record
            if ($record->status === 'committed_error') {
                return $this->conflictResponse($request, 'Financial transaction was committed in a previous attempt. Re-execution blocked.');
            }

            // If processing, wait briefly for concurrent execution
            if ($record->status === 'processing') {
                $record = $this->waitForCompletion($idempotencyKey);
                if (! $record || $record->status !== 'completed') {
                    if ($record && $record->request_hash !== $requestHash) {
                        return $this->conflictResponse($request);
                    }
                    if ($record && $record->status === 'committed_error') {
                        return $this->conflictResponse($request, 'Financial transaction was committed in a previous attempt. Re-execution blocked.');
                    }

                    return $this->conflictResponse($request, 'A duplicate financial request is currently processing. Please wait.');
                }
            }

            if ($record->status === 'completed') {
                return $this->reconstructResponse($record);
            }

            // If failed without business commit, allow retry
            $claimed = DB::table('idempotency_keys')
                ->where('key', $idempotencyKey)
                ->where('status', 'failed')
                ->update([
                    'status' => 'processing',
                    'updated_at' => now(),
                ]);

            if ($claimed !== 1) {
                return $this->conflictResponse($request, 'A duplicate financial request is currently processing. Please wait.');
            }
        }

        $businessTransactionCommitted = false;
        $activeListener = true;
        Event::listen(TransactionCommitted::class, function () use (&$businessTransactionCommitted, &$activeListener) {
            if ($activeListener && DB::transactionLevel() === 0) {
                $businessTransactionCommitted = true;
            }
        });

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $activeListener = false;
            $finalStatus = $businessTransactionCommitted ? 'committed_error' : 'failed';
            DB::table('idempotency_keys')
                ->where('key', $idempotencyKey)
                ->update([
                    'status' => $finalStatus,
                    'updated_at' => now(),
                ]);

            throw $e;
        } finally {
            $activeListener = false;
        }

        $hasValidationErrors = $request->hasSession() && $request->session()->has('errors');

        if ($response->getStatusCode() < 400 && ! $hasValidationErrors) {
            $this->storeCompletedOutcome($idempotencyKey, $response, $request);
        } else {
            $finalStatus = $businessTransactionCommitted ? 'committed_error' : 'failed';
            DB::table('idempotency_keys')
                ->where('key', $idempotencyKey)
                ->update([
                    'status' => $finalStatus,
                    'response_code' => $response->getStatusCode(),
                    'updated_at' => now(),
                ]);
        }

        return $response;
    }



    private function calculateRequestHash(Request $request): string
    {
        $payload = $this->cleanedPayload($request);

        return hash('sha256', ($request->user()?->id ?? 'anon').'|'.$request->method().'|'.$request->path().'|'.json_encode($payload));
    }

    private function cleanedPayload(Request $request): array
    {
        $payload = $request->except(['_token', '_idempotency_key', 'idempotency_key']);
        ksort($payload);

        return $payload;
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
