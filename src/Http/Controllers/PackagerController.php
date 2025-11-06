<?php /** @noinspection NestedTernaryOperatorInspection */

/** @noinspection PhpUnusedParameterInspection */

namespace Timeax\FortiPlugin\Http\Controllers;

use FilesystemIterator;
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
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Timeax\FortiPlugin\Enums\KeyPurpose;
use Timeax\FortiPlugin\Models\Author;
use Timeax\FortiPlugin\Models\PluginPlaceholder;
use Timeax\FortiPlugin\Models\PluginSignature;
use Timeax\FortiPlugin\Models\PluginToken;
use Timeax\FortiPlugin\Models\PluginZip;
use Timeax\FortiPlugin\Enums\PluginStatus;
use Timeax\FortiPlugin\Enums\ValidationStatus;
use Timeax\FortiPlugin\Services\HostKeyService;
use Timeax\FortiPlugin\Services\PolicyService;
use Timeax\FortiPlugin\Services\SigningService;
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
        $verify = $this->keys->currentVerifyKey(KeyPurpose::packager_sign);

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
            'slug' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
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
            'token' => $raw, // raw once; the client stores securely
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
        $verify = $this->keys->currentVerifyKey("plugin_packer");

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
            'enc_zip' => ['required_without:zip', 'file'],
            'zip' => ['sometimes', 'file'],
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

        // Accept either enc_zip (preferred) or zip; for now, treat enc_zip as raw zip per minimal contract
        /** @var UploadedFile|null $file */
        $file = $request->file('enc_zip') ?: $request->file('zip');
        if (!$file) {
            return response()->json(['ok' => false, 'error' => 'no_file'], 400);
        }

        $stored = $file->storeAs('forti/uploads', Str::uuid()->toString() . '.zip');
        $zipPath = storage_path('app/' . $stored);

        // Expand the zip into a temp directory
        $tmpDir = storage_path('app/forti/tmp/' . Str::uuid()->toString());
        if (!is_dir($tmpDir) && !mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
            throw new RuntimeException('Failed to create temp directory');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return response()->json(['ok' => false, 'error' => 'invalid_zip'], 400);
        }
        $zip->extractTo($tmpDir);
        $zip->close();

        // Integrity checks against cached manifest
        $manifest = $state['manifest'] ?? null;
        if (!is_array($manifest) || !isset($manifest['files']) || !is_array($manifest['files'])) {
            return response()->json(['ok' => false, 'error' => 'manifest_missing'], 400);
        }

        $integrityOk = true;

        // Build set of files from manifest
        $expected = [];
        foreach ($manifest['files'] as $f) {
            $expected[$f['path']] = $f;
        }

        // Verify each manifest file
        foreach ($expected as $rel => $info) {
            $abs = $tmpDir . DIRECTORY_SEPARATOR . $rel;
            if (!is_file($abs)) {
                $integrityOk = false;
                break;
            }
            $size = filesize($abs);
            $hash = hash_file('sha256', $abs);
            if ((int)$size !== (int)$info['size'] || strtolower($hash) !== strtolower($info['sha256'])) {
                $integrityOk = false;
                break;
            }
        }

        // Optionally enforce no extra files beyond manifest
        $noExtras = true;
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS));
        foreach ($rii as $fs) {
            $relPath = ltrim(str_replace($tmpDir, '', $fs->getPathname()), '\\/');
            $relPath = str_replace('\\', '/', $relPath);
            if (!isset($expected[$relPath]) && $fs->isFile()) {
                $noExtras = false;
                break;
            }
        }

        $allOk = $integrityOk && $noExtras;

        // Create receipt and cache it
        $receiptId = 'rcpt_' . Str::random(36);
        Cache::put("forti:receipt:$receiptId", [
            'placeholder_id' => $state['placeholder_id'] ?? null,
            'plugin_key' => $state['plugin_key'] ?? null,
            'tmp_dir' => $tmpDir,
            'zip_path' => $zipPath,
            'manifest' => $manifest,
            'issued_at' => now()->toIso8601String(),
            'integrity_ok' => $allOk,
        ], now()->addMinutes(40));

        return response()->json([
            'ok' => true,
            'receipt_id' => $receiptId,
        ]);
    }

    /**
     * STEP 4 — finalize upload based on receipt and action
     */
    public function packComplete(Request $request): JsonResponse
    {
        Gate::authorize(FortiGates::PLUGIN_VALIDATE);

        $data = $request->validate([
            'receipt_id' => ['required', 'string', 'max:128'],
            'action' => ['nullable', 'in:auto,accept,reject'],
        ]);
        $action = $data['action'] ?? 'auto';

        $receipt = Cache::get("forti:receipt:{$data['receipt_id']}");
        if (!$receipt) {
            return response()->json(['ok' => false, 'error' => 'receipt_not_found'], 400);
        }

        if ($action === 'accept') {
            $finalStatus = 'accepted';
        } elseif ($action === 'reject') {
            $finalStatus = 'rejected';
        } else { // auto
            $finalStatus = ($receipt['integrity_ok'] ?? false) ? 'accepted' : 'rejected';
        }

        $zipRecordId = null;
        $savedPath = null;

        // Persist PluginZip/metadata if accepted
        if ($finalStatus === 'accepted') {
            $placeholderId = (int)($receipt['placeholder_id'] ?? 0);
            if ($placeholderId <= 0) {
                return response()->json(['ok' => false, 'error' => 'placeholder_missing_on_receipt'], 400);
            }
            $placeholder = PluginPlaceholder::find($placeholderId);
            if (!$placeholder) {
                return response()->json(['ok' => false, 'error' => 'placeholder_not_found'], 404);
            }

            // Determine the destination path for permanent storage
            $slug = $placeholder->slug ?: ('ph-' . $placeholderId);
            $version = (string)($receipt['manifest']['plugin']['version'] ?? '0.0.0');
            $ts = now()->format('YmdHis');
            $destDir = storage_path('app/forti/packages/' . $slug);
            if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
                throw new RuntimeException('Failed to create packages directory');
            }
            $destPath = $destDir . DIRECTORY_SEPARATOR . $slug . '-' . $version . '-' . $ts . '.zip';

            // Move the uploaded zip to permanent location
            $src = $receipt['zip_path'] ?? null;
            if ($src && is_file($src)) {
                if (!@rename($src, $destPath)) {
                    // fallback to copy
                    if (!@copy($src, $destPath)) {
                        throw new RuntimeException('Failed to persist uploaded zip');
                    }
                    @unlink($src);
                }
                $savedPath = $destPath;
            }

            // Build meta payload
            $meta = [
                'plugin_key' => $receipt['plugin_key'] ?? null,
                'manifest' => $receipt['manifest'] ?? null,
                'stored_at' => now()->toIso8601String(),
                'filename' => basename((string)$savedPath),
            ];

            // Uploaded by author (if set by middleware)
            $authorId = $request->attributes->get('forti.author_id');

            $zip = PluginZip::create([
                'placeholder_id' => $placeholderId,
                'path' => $savedPath ?: ($src ?: ''),
                'meta' => $meta,
                'status' => PluginStatus::active,
                'validation_status' => ($receipt['integrity_ok'] ?? false) ? ValidationStatus::unverified : ValidationStatus::failed,
                'uploaded_by_author_id' => $authorId ?: null,
            ]);
            $zipRecordId = $zip->id;
        }

        // Cleanup temp
        if (!empty($receipt['tmp_dir'])) {
            $this->rrmdir($receipt['tmp_dir']);
        }
        if (!empty($receipt['zip_path']) && is_file($receipt['zip_path'])) {
            @unlink($receipt['zip_path']);
        }

        // Remove cached receipt
        Cache::forget("forti:receipt:{$data['receipt_id']}");

        return response()->json([
            'ok' => true,
            'status' => $finalStatus,
            'zip_id' => $zipRecordId,
            'path' => $savedPath,
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