<?php /** @noinspection PhpUnusedParameterInspection */

namespace Timeax\FortiPlugin\Http\Controllers;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use JsonException;
use Timeax\FortiPlugin\Models\Author;
use Timeax\FortiPlugin\Models\PluginPlaceholder;
use Timeax\FortiPlugin\Models\PluginSignature;
use Timeax\FortiPlugin\Models\PluginToken;
use Timeax\FortiPlugin\Services\HostKeyService;
use Timeax\FortiPlugin\Services\PolicyService;
use Timeax\FortiPlugin\Services\SigningService;
use Timeax\FortiPlugin\Support\FortiGates;

final class PackagerController extends Controller
{
    public function __construct(
        private readonly PolicyService  $policy,
        private readonly HostKeyService $keys,
    )
    {
    }

    /**
     * @throws FileNotFoundException
     * @throws JsonException
     */
    public function handshake(Request $request): JsonResponse
    {
        Gate::authorize(FortiGates::PACKAGER_FETCH_POLICY);

        $snapshot = $this->policy->snapshot();
        $verify = $this->keys->currentVerifyKey();

        return response()->json([
            'ok' => true,
            'policy_version' => $snapshot['version'] ?? '1',
            'policy' => $snapshot,
            'host' => [
                'domain' => parse_url(config('app.url'), PHP_URL_HOST),
                'verify' => $verify,
            ],
            'time' => now()->toIso8601String(),
        ]);
    }

    /** First handshake (make command): create placeholder + issue placeholder token
     * @throws JsonException
     * @throws FileNotFoundException
     */
    public function init(Request $request): JsonResponse
    {
        Gate::authorize(FortiGates::PLACEHOLDER_CREATE);
        Gate::authorize(FortiGates::TOKEN_ISSUE_PLACEHOLDER);

        $data = $request->validate([
            'slug' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        // Resolve current author from middleware (optional)
        $authorId = $request->attributes->get('forti.author_id');
        $author = $authorId ? Author::find($authorId) : null;

        // Create placeholder
        $slug = $data['slug'] ?: Str::slug('pkg-' . Str::random(8));
        $name = $data['name'] ?: $slug;
        $uKey = (string)Str::uuid();

        $placeholder = PluginPlaceholder::create([
            'slug' => $slug,
            'name' => $name,
            'unique_key' => $uKey,
            'owner_ref' => $author?->slug, // hint only
            'meta' => ['created_by_author_id' => $author?->id],
        ]);

        // Issue placeholder-level token
        $raw = 'forti_' . Str::random(64);
        $hash = hash('sha256', $raw);

        PluginToken::create([
            'plugin_placeholder_id' => $placeholder->id,
            'author_id' => $author?->id,
            'token_hash' => $hash,
            'expires_at' => now()->addDays(7),
            'meta' => ['scopes' => [
                FortiGates::PACKAGER_FETCH_POLICY,
                FortiGates::PACKAGER_REGISTER_FINGERPRINT,
                FortiGates::PLUGIN_UPLOAD,
                FortiGates::PLUGIN_SCAN,
                FortiGates::PLUGIN_VALIDATE,
            ]],
        ]);

        return response()->json([
            'ok' => true,
            'placeholder' => [
                'id' => $placeholder->id,
                'slug' => $placeholder->slug,
                'name' => $placeholder->name,
                'key' => $placeholder->unique_key,
            ],
            'token' => $raw, // raw once; the client must store securely
            'expires_at' => now()->addDays(7)->toIso8601String(),
            'signature_block' => SigningService::makeSignature(
                author: [
                    'name' => $author?->name,
                    'email' => $author?->email,
                    'website' => $author?->website,
                ],
                hostDomain: $request->getHost() ?: parse_url($request->fullUrl(), PHP_URL_HOST),
                policy: $this->policy->snapshot(),
                pluginInfo: [
                    'name' => $name,
                    'slug' => $slug,
                ]
            )
        ]);
    }

    public function pack(Request $request): JsonResponse
    {
        Gate::authorize(FortiGates::PACKAGER_REGISTER_FINGERPRINT);

        $data = $request->validate([
            'placeholder' => ['required', 'string', 'max:255'],
            'plugin_key' => ['required', 'string', 'max:1024'],
            'owner_host' => ['nullable', 'string', 'max:255'],
            'policy_version' => ['nullable', 'string', 'max:64'],
            'report' => ['nullable', 'array'],
        ]);

        $placeholder = PluginPlaceholder::query()
            ->where('slug', $data['placeholder'])
            ->orWhere('unique_key', $data['placeholder'])
            ->firstOrFail();

        $sig = $this->keys->sign($data['plugin_key']);

        PluginSignature::query()->updateOrCreate(
            ['placeholder_id' => $placeholder->id, 'plugin_key' => $data['plugin_key']],
            [
                'host_domain' => parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost',
                'owner_host' => $data['owner_host'] ?? '',
                'signature' => $sig['signature_b64'],
            ]
        );

        return response()->json([
            'ok' => true,
            'signature' => [
                'algorithm' => $sig['alg'],
                'fingerprint' => $sig['fingerprint'],
                'value' => $sig['signature_b64'],
            ],
        ]);
    }
}