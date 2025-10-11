<?php /** @noinspection PhpUnusedParameterInspection */

namespace Timeax\FortiPlugin\Http\Controllers;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use JsonException;
use Random\RandomException;
use RuntimeException;
use SodiumException;
use Timeax\FortiPlugin\Models\Author;
use Timeax\FortiPlugin\Models\PluginPlaceholder;
use Timeax\FortiPlugin\Models\PluginSignature;
use Timeax\FortiPlugin\Models\PluginToken;
use Timeax\FortiPlugin\Services\HostKeyService;
use Timeax\FortiPlugin\Services\PolicyService;
use Timeax\FortiPlugin\Services\SigningService;
use Timeax\FortiPlugin\Services\ValidatorService;
use Timeax\FortiPlugin\Support\Encryption;
use Timeax\FortiPlugin\Support\FortiGates;
use ZipArchive;

final class PackagerController extends Controller
{
    public function __construct(
        private readonly PolicyService  $policy,
        private readonly HostKeyService $keys,
    )
    {
    }

    /**
     * Handshake: return policy snapshot, verify key, and a host-generated signature block
     * used by the CLI to scaffold .internal/Config.php.
     *
     * @throws FileNotFoundException
     * @throws JsonException
     */
    public function handshake(Request $request): JsonResponse
    {
        Gate::authorize(FortiGates::PACKAGER_FETCH_POLICY);

        $snapshot = $this->policy->snapshot();
        $verify = $this->keys->currentVerifyKey();

        // If middleware set an author, enrich the signature block
        $authorId = $request->attributes->get('forti.author_id');
        $author = $authorId ? Author::find($authorId) : null;

        $signatureBlock = SigningService::makeSignature(
            author: [
                'name' => $author?->name,
                'email' => $author?->email,
                'website' => $author?->website,
            ],
            hostDomain: parse_url(config('app.url'), PHP_URL_HOST),
            policy: $snapshot,
            pluginInfo: [
                'name' => null,
                'slug' => null,
            ]
        );

        return response()->json([
            'ok' => true,
            'policy_version' => $snapshot['version'] ?? '1',
            'policy' => $snapshot,
            'host' => [
                'domain' => parse_url(config('app.url'), PHP_URL_HOST),
                'verify' => $verify,
            ],
            'signature_block' => $signatureBlock,
            'time' => now()->toIso8601String(),
        ]);
    }

    /**
     * First bootstrap (used by make/scaffold): create placeholder + issue placeholder token.
     *
     */
    public function init(Request $request): JsonResponse
    {
        Gate::authorize(FortiGates::PLACEHOLDER_CREATE);
        Gate::authorize(FortiGates::TOKEN_ISSUE_PLACEHOLDER);

        $data = $request->validate([
            'slug' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        // Resolve current author
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

        // Issue placeholder token
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
            'psr4_root' => config('fortiplugin.psr4_root', 'Plugins'),
            'placeholder' => [
                'id' => $placeholder->id,
                'slug' => $placeholder->slug,
                'name' => $placeholder->name,
                'key' => $placeholder->unique_key,
            ],
            'token' => $raw, // raw once; client stores securely
            'expires_at' => now()->addDays(7)->toIso8601String(),
        ]);
    }

    /**
     * Legacy simple sign endpoint (kept for compatibility).
     * @throws JsonException
     */
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

    /**
     * STEP 1 — prepare (give CLI encryption nonce/key, exclude rules & validator config)
     *
     * @throws FileNotFoundException
     * @throws JsonException
     * @throws RandomException
     */
    public function packHandshake(Request $request): JsonResponse
    {
        Gate::authorize(FortiGates::PACKAGER_FETCH_POLICY);

        $snapshot = $this->policy->snapshot();
        $verify = $this->keys->currentVerifyKey();

        // ephemeral encryption key bound to a nonce
        $nonce = 'up_' . Str::random(24);
        $encKey = base64_encode(random_bytes(32));
        Cache::put("forti:enc:$nonce", $encKey, now()->addMinutes(40));

        $exclude = [
            'vendor/**', 'node_modules/**', 'tests/**', '.git/**', 'logs/**',
            'resources/inertia/ts/**', 'resources/embed/ts/**', 'resources/shared/ts/**',
            '*.ts', '*.tsx', 'vite.config.*', 'vite.input.*', 'tsconfig.json',
        ];

        $validatorConfig = [
            'headline' => [
                'composer_json' => 'composer.json',
                'forti_schema' => base_path('vendor/timeax/fortiplugin/schemas/fortiplugin.schema.json'),
                'host_config' => $snapshot['host_config'] ?? [],
                'permission_manifest' => '.internal/permissions.json',
                'route_files' => [],
            ],
            'scan' => [
                'token_list' => Arr::wrap($snapshot['forbidden_functions'] ?? []),
            ],
            'fail_policy' => [
                'types_blocklist' => ['always_forbidden_function', 'always_forbidden_reflection', 'ast.exception'],
                'total_error_limit' => null,
                'per_type_limits' => [],
                'file_gates' => [],
            ],
        ];

        return response()->json([
            'ok' => true,
            'policy_version' => $snapshot['version'] ?? '1',
            'host' => [
                'domain' => parse_url(config('app.url'), PHP_URL_HOST),
                'verify' => $verify,
            ],
            'exclude' => $exclude,
            'validator_config' => $validatorConfig,
            'limits' => [
                'max_upload_mb' => 50,
            ],
            'encryption' => [
                'nonce' => $nonce,
                'algorithm' => 'AES-256-GCM (app-level wrapper)',
            ],
            'time' => now()->toIso8601String(),
        ]);
    }

    /**
     * STEP 2 — sign canonical manifest and issue an upload token
     *
     * @throws JsonException
     */
    public function packManifest(Request $request): JsonResponse
    {
        Gate::authorize(FortiGates::PACKAGER_REGISTER_FINGERPRINT);

        $data = $request->validate([
            'placeholder' => ['required', 'string', 'max:255'],
            'plugin_key' => ['required', 'string', 'max:1024'],
            'nonce' => ['required', 'string', 'max:128'],
            'manifest' => ['required', 'array'],
        ]);

        $placeholder = PluginPlaceholder::query()
            ->where('slug', $data['placeholder'])
            ->orWhere('unique_key', $data['placeholder'])
            ->firstOrFail();

        $encKey = Cache::get("forti:enc:{$data['nonce']}");
        if (!$encKey) {
            return response()->json(['ok' => false, 'error' => 'handshake_expired'], 400);
        }

        $canonical = $this->canonicalJson($data['manifest']);
        $sig = $this->keys->sign($canonical);

        $uploadToken = 'upload_' . Str::random(40);
        Cache::put("forti:upload:$uploadToken", [
            'placeholder_id' => $placeholder->id,
            'plugin_key' => $data['plugin_key'],
            'nonce' => $data['nonce'],
            'enc_key' => $encKey,
            'manifest_json' => $canonical,
            'manifest' => $data['manifest'],
            'issued_at' => now()->toIso8601String(),
        ], now()->addMinutes(40));

        return response()->json([
            'ok' => true,
            'signature' => [
                'algorithm' => $sig['alg'],
                'fingerprint' => $sig['fingerprint'],
                'value' => $sig['signature_b64'],
            ],
            'upload' => [
                'token' => $uploadToken,
                'url' => route('forti.pack.upload'),
            ],
        ]);
    }

    /**
     * STEP 3 — receive encrypted artifact, decrypt, expand, validate server-side
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function packUpload(Request $request): JsonResponse
    {
        Gate::authorize(FortiGates::PLUGIN_UPLOAD);

        $data = $request->validate([
            'token' => ['required', 'string', 'max:128'],
            'enc_zip' => ['required', 'file'],
            'placeholder' => ['required', 'string', 'max:255'],
            'plugin_key' => ['required', 'string', 'max:1024'],
        ]);

        $state = Cache::get("forti:upload:{$data['token']}");
        if (!$state) {
            return response()->json(['ok' => false, 'error' => 'upload_token_invalid_or_expired'], 400);
        }

        if ($state['plugin_key'] !== $data['plugin_key']) {
            return response()->json(['ok' => false, 'error' => 'plugin_key_mismatch'], 400);
        }

        /** @var UploadedFile $file */
        $file = $request->file('enc_zip');
        $stored = $file->storeAs('forti/uploads', Str::uuid()->toString() . '.zip.enc');
        $encPath = storage_path('app/' . $stored);

        // TODO: Save to DB as PluginZip + PluginSignature/Release records as needed

        return response()->json([
            'ok' => true,
        ]);
    }


    /* ─────────── helpers ─────────── */

    /**
     * @throws JsonException
     */
    private function canonicalJson(array $data): string
    {
        $sort = static function (&$v) use (&$sort) {
            if (is_array($v)) {
                ksort($v);
                foreach ($v as &$x) {
                    $sort($x);
                }
            }
        };
        $copy = $data;
        $sort($copy);
        return json_encode($copy, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) ?: [] as $i) {
            if ($i === '.' || $i === '..') continue;
            $p = $dir . DIRECTORY_SEPARATOR . $i;
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}