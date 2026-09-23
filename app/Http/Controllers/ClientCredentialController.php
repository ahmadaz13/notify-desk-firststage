<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientSystemCredential;
use App\Models\Product;
use App\Services\ClientCredentialService;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Client system credentials (§18.3). Every action checks the permission first, then that the credential
 * belongs to the client in the URL, so IDs cannot be swapped across clients.
 *
 * Form field names credential_secret / credential_note are in dontFlash (bootstrap/app.php), so a
 * validation failure never echoes them back into the page.
 */
class ClientCredentialController extends Controller
{
    public function __construct(private readonly ClientCredentialService $credentials)
    {
    }

    public function store(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize(Permissions::MANAGE_CLIENT_CREDENTIALS);
        Gate::authorize('update', $client);

        $data = $request->validateWithBag('credentials', [
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ] + $this->fieldRules(requireSecret: true));

        $product = Product::findOrFail($data['product_id']);
        $this->inCredentialsBag(fn () => $this->credentials->store($client, $product, $this->payload($data), $request->user(), $request));

        return back()->with('success', __('notify.credentials.saved', ['system' => $this->credentials->systemName($product)]));
    }

    public function update(Request $request, Client $client, ClientSystemCredential $credential): RedirectResponse
    {
        $this->authorizeCredential(Permissions::MANAGE_CLIENT_CREDENTIALS, $client, $credential);

        $data = $request->validateWithBag('credentials', $this->fieldRules(requireSecret: false) + [
            'clear_note' => ['nullable', 'boolean'],
        ]);

        $this->inCredentialsBag(fn () => $this->credentials->update($credential, $this->payload($data), $request->user(), $request));

        return back()->with('success', __('notify.credentials.saved', ['system' => $this->credentials->systemName($credential->product)]));
    }

    public function destroy(Request $request, Client $client, ClientSystemCredential $credential): RedirectResponse
    {
        $this->authorizeCredential(Permissions::MANAGE_CLIENT_CREDENTIALS, $client, $credential);

        $this->credentials->delete($credential, $request->user(), $request);

        return back()->with('success', __('notify.credentials.deleted', ['system' => $this->credentials->systemName($credential->product)]));
    }

    public function reveal(Request $request, Client $client, ClientSystemCredential $credential): JsonResponse
    {
        $this->authorizeCredential(Permissions::REVEAL_CLIENT_CREDENTIALS, $client, $credential);

        return $this->noStore(response()->json($this->credentials->reveal($credential, $request->user(), $request)));
    }

    public function copied(Request $request, Client $client, ClientSystemCredential $credential): JsonResponse
    {
        $this->authorizeCredential(Permissions::REVEAL_CLIENT_CREDENTIALS, $client, $credential);

        $this->credentials->recordCopied($credential, $request->user(), $request);

        return $this->noStore(response()->json(['ok' => true]));
    }

    public function send(Request $request, Client $client, ClientSystemCredential $credential): JsonResponse
    {
        $this->authorizeCredential(Permissions::REVEAL_CLIENT_CREDENTIALS, $client, $credential);

        $data = $request->validate([
            'recipient' => ['required', 'string', 'max:50'],
        ]);

        $url = $this->credentials->send($credential, $data['recipient'], $request->user(), $request);

        return $this->noStore(response()->json(['url' => $url]));
    }

    private function authorizeCredential(string $permission, Client $client, ClientSystemCredential $credential): void
    {
        Gate::authorize($permission);
        Gate::authorize('update', $client);
        abort_unless($credential->belongsToClient($client), 404);
    }

    private function fieldRules(bool $requireSecret): array
    {
        return [
            'login_url' => ['nullable', 'string', 'max:500', 'url:http,https'],
            // Flexible: some Systems use plain usernames, so no email syntax is forced.
            'username' => ['nullable', 'string', 'max:255'],
            'credential_secret' => [$requireSecret ? 'required' : 'nullable', 'string', 'max:1000'],
            'credential_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function payload(array $data): array
    {
        return [
            'login_url' => $data['login_url'] ?? null,
            'username' => $data['username'] ?? null,
            'secret' => $data['credential_secret'] ?? null,
            'note' => $data['credential_note'] ?? null,
            'clear_note' => (bool) ($data['clear_note'] ?? false),
        ];
    }

    /** Domain validation errors land in the same bag the credentials panel renders. */
    private function inCredentialsBag(callable $action): mixed
    {
        try {
            return $action();
        } catch (ValidationException $exception) {
            throw $exception->errorBag('credentials');
        }
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'no-store, private');
    }
}
