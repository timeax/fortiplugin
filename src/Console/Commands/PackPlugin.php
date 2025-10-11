<?php /** @noinspection PhpUnusedParameterInspection */
/** @noinspection PhpUnused */
/** @noinspection PhpUnhandledExceptionInspection */

namespace Timeax\FortiPlugin\Console\Commands;

use Closure;
use FilesystemIterator;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Process\Process;
use Throwable;
use Timeax\FortiPlugin\Services\PolicyService;
use Timeax\FortiPlugin\Services\ValidatorService;
use Timeax\FortiPlugin\Support\CliSessionManager;
use Timeax\FortiPlugin\Support\Encryption;
use Timeax\FortiPlugin\Traits\AuthenticateSession;
use ZipArchive;

class PackPlugin extends Command
{
    use AuthenticateSession;

    protected $signature = 'forti:pack
        {name : Plugin directory name, e.g., OrdersPlugin}
        {--output= : Output path for zip}
        {--force : Overwrite if zip exists}
        {--quiet : Suppress validation progress output}';

    protected $description = 'Validate and pack a plugin for upload to the host.';

    /**
     * Main entry.
     */
    public function handle(): int
    {
        // Session / API
        $session = $this->auth();
        if (!$session) return self::FAILURE;

        $name = $this->argument('name');
        $plugin = base_path("Plugins/$name");
        if (!is_dir($plugin)) {
            $this->error("Plugin not found: $plugin");
            return self::FAILURE;
        }

        // 0) Copy working dir to temp, applying ignores
        $tempPath = $this->copyToTempWithIgnores($plugin);

        try {
            // 1) Load or generate publish.json
            $this->assertOutDirUnchanged($tempPath);
            $publishPath = $plugin . '/publish.json';
            $publish = $this->ensurePublishJson($plugin, $publishPath);
            if (!$publish) {
                $this->fail("Could not load or create publish.json.");
            }

            $host = $publish['host'];
            $pluginSlug = $publish['plugin_slug'];
            $pluginKey = $publish['plugin_key'];

            // Optional: read placeholder token created by `forti:make`
            $placeholderToken = $this->readPlaceholderToken($plugin);

            // 2) First handshake — fetch policy snapshot / host verify (author bearer)
            $hs = $this->getHttp()?->get('/forti/pack/handshake');
            $handshake = $this->safeJson($hs);
            if (!($handshake['ok'] ?? false)) {
                $this->fail('Handshake failed.');
            }
            $policyVersion = (string)($handshake['policy_version'] ?? '1');

            // 3) Validation (local)
            // Build PluginPolicy via service
            /** @var PolicyService $policySvc */
            $policySvc = app(PolicyService::class);
            $policy = $policySvc->makePolicy();

            $validator = new ValidatorService($policy, [
                // plug in extras if you want (schema path, host_config, etc.)
            ]);

            $emit = $this->option('quiet') ? null : $this->makeEmitCallback();
            $summary = $validator->run($tempPath, $emit);

            // If host wants to gate on should_fail, you can optionally stop here
            if ($summary['should_fail'] ?? false) {
                $this->warn("Validation indicates failure according to fail_policy. Aborting pack.");
                $this->deleteDirectory($tempPath);
                return self::FAILURE;
            }

            // 4) Second handshake — send validation summary (use placeholder token header if present)
            $second = $placeholderToken
                ? $this->httpWithPlaceholderToken($placeholderToken)?->post('/forti/pack/manifest', [
                    'placeholder' => $pluginSlug,
                    'plugin_key' => $pluginKey,
                    'owner_host' => parse_url($host, PHP_URL_HOST) ?: $host,
                    'policy_version' => $policyVersion,
                    'report' => $summary,
                ])
                : $this->getHttp()?->post('/forti/pack', [
                    'placeholder' => $pluginSlug,
                    'plugin_key' => $pluginKey,
                    'owner_host' => parse_url($host, PHP_URL_HOST) ?: $host,
                    'policy_version' => $policyVersion,
                    'report' => $summary,
                ]);

            $secondHs = $this->safeJson($second);
            if (!($secondHs['ok'] ?? false)) {
                $this->deleteDirectory($tempPath);
                $this->fail('Second handshake (/forti/pack) failed.');
            }

            // Optional values returned by server
            $uploadTicket = $secondHs['ticket'] ?? null;          // ephemeral upload ticket (if implemented)
            $signatureBlock = $secondHs['signature']['value'] ?? null; // signature over plugin_key (if returned)
            $encryptionKey = $secondHs['encryption_key'] ?? null;  // if host provides symmetric key

            // 5) Version bump if needed (compare with host-provided current_version if present)
            $cfgPath = $tempPath . '/fortiplugin.json';
            if (file_exists($cfgPath)) {
                $cfg = json_decode((string)file_get_contents($cfgPath), true, 512, JSON_THROW_ON_ERROR);
                $localVersion = (string)($cfg['version'] ?? '0.1.0');
                $currentRemote = (string)($secondHs['current_version'] ?? '');
                if ($currentRemote !== '' && version_compare($localVersion, $currentRemote, '<=')) {
                    $this->warn("Host has version $currentRemote; local is $localVersion.");
                    $next = $this->ask(
                        "Enter a new version greater than $currentRemote:",
                        $this->suggestNextVersion($currentRemote)
                    );
                    if (version_compare($next, $currentRemote, '<=')) {
                        throw new RuntimeException("Version must be greater than $currentRemote.");
                    }
                    $cfg['version'] = $next;
                    file_put_contents($cfgPath, json_encode($cfg, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    $this->info("fortiplugin.json updated to version $next");
                }
            }

            // 6) Build assets (if any)
            $this->runNpmBuild($tempPath);

            // 7) Collect files and write manifest
            $excludeList = (array)($secondHs['exclude'] ?? []); // host-provided extra excludes (optional)
            $files = $this->collectPluginFiles($tempPath, $excludeList);

            $manifest = [
                'plugin' => [
                    'slug' => $pluginSlug,
                    'key' => $pluginKey,
                    'policy_version' => $policyVersion,
                ],
                'validation' => [
                    'files_scanned' => $summary['files_scanned'] ?? 0,
                    'total_issues' => $summary['total_issues'] ?? 0,
                    'should_fail' => $summary['should_fail'] ?? false,
                ],
                'signature' => $secondHs['signature'] ?? null,
                'time' => now()->toIso8601String(),
            ];

            $manifestPath = $tempPath . '/.internal/manifest.json';
            if (!is_dir(dirname($manifestPath)) && !mkdir(dirname($manifestPath), 0755, true) && !is_dir(dirname($manifestPath))) {
                throw new RuntimeException('Unable to create .internal directory.');
            }
            file_put_contents($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // 8) Zip (and encrypt if key present)
            $zipPath = $this->option('output') ?: base_path("Plugins/$name-" . date('Ymd-His') . ".zip");
            if (file_exists($zipPath) && !$this->option('force')) {
                throw new RuntimeException("Zip already exists: $zipPath (use --force to overwrite)");
            }
            $this->makeZipFromFiles($files, $manifestPath, $zipPath);

            $payloadPath = $zipPath;
            if ($encryptionKey) {
                $encrypted = $zipPath . '.enc';
                Encryption::encryptFile($zipPath, $encrypted);
                $payloadPath = $encrypted;
                @unlink($zipPath); // keep only encrypted payload
                $this->info("Encrypted package: $encrypted");
            } else {
                $this->warn("No encryption_key from host; uploading raw zip (TLS in transit).");
            }

            // 9) Upload (prefer handshake ticket header if provided)
            $uReq = $uploadTicket
                ? $this->httpWithHandshakeTicket((string)$uploadTicket)
                : $this->getHttp();

            $response = $uReq?->attach(
                $encryptionKey ? 'enc_zip' : 'zip',
                fopen($payloadPath, 'rb'),
                basename($payloadPath)
            )->post('/forti/upload', [
                'placeholder' => $pluginSlug,
                'plugin_key' => $pluginKey,
                'version' => $cfg['version'] ?? null,
                // include signature in body if host expects it there
                'signature' => $signatureBlock,
            ]);

            $upload = $this->safeJson($response);
            if (!($upload['ok'] ?? false)) {
                throw new RuntimeException('Upload failed: ' . ($upload['error'] ?? 'Unknown'));
            }

            // 10) Cleanup temp
            $this->deleteDirectory($tempPath);

            $this->info("✅ Plugin packed and uploaded.");
            return self::SUCCESS;

        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->deleteDirectory($tempPath);
            return self::FAILURE;
        }
    }

    /* ────────────────────────────── Helpers ────────────────────────────── */

    protected function readPlaceholderToken(string $pluginRoot): ?string
    {
        $p = rtrim($pluginRoot, '/\\') . '/.internal/placeholder.token.json';
        if (!is_file($p)) return null;
        try {
            $d = json_decode((string)file_get_contents($p), true, 512, JSON_THROW_ON_ERROR);
            return is_array($d) ? ($d['token'] ?? null) : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function ensurePublishJson(string $pluginRoot, string $publishPath): ?array
    {
        if (file_exists($publishPath)) {
            try {
                return json_decode((string)file_get_contents($publishPath), true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                // fallthrough to regenerate
            }
        }

        $hosts = CliSessionManager::listHosts();
        if (empty($hosts)) {
            $this->error("No saved hosts found. Please login first.");
            return null;
        }

        $options = [];
        foreach ($hosts as $alias => $info) {
            $options[$alias] = "$alias ({$info['host']})";
        }
        $chosen = $this->choice("Select host for publish.json", $options, array_key_first($options));
        $session = $hosts[$chosen] ?? null;
        if (!$session) {
            $this->error("Host not found in sessions.");
            return null;
        }

        $host = $session['host'];
        $cfg = $this->readFortiConfig($pluginRoot);
        $alias = $cfg['alias'] ?? basename($pluginRoot);
        $slug = $this->ask("Plugin slug", Str::kebab($alias));

        // Try fetch plugin key (placeholder)
        $pluginKey = $this->ask('Plugin key (from your host placeholder page)');

        $author = [
            'name' => $session['name'] ?? $this->ask('Author name'),
            'email' => $session['email'] ?? $this->ask('Author email'),
        ];

        $out = [
            'host' => $host,
            'plugin_slug' => $slug,
            'plugin_key' => $pluginKey,
            'author' => $author,
        ];

        file_put_contents($publishPath, json_encode($out, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $out;
    }

    protected function readFortiConfig(string $pluginRoot): array
    {
        $p = rtrim($pluginRoot, '/\\') . '/fortiplugin.json';
        if (!is_file($p)) return [];
        try {
            return json_decode((string)file_get_contents($p), true, 512, JSON_THROW_ON_ERROR) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    protected function makeEmitCallback(): Closure
    {
        /** @var mixed $output */
        $output = $this->output;
        if (method_exists($output, 'getOutput')) {
            $output = $output->getOutput();
        }
        $supportsSections = $output instanceof ConsoleOutputInterface;
        $sections = [];
        $progress = null;
        $filesStarted = false;

        return function (array $e) use (&$sections, &$progress, &$filesStarted, $output, $supportsSections) {
            $title = (string)($e['title'] ?? 'Scan');
            $desc = (string)($e['description'] ?? '');
            $file = (string)($e['stats']['filePath'] ?? '');
            // $size = $e['stats']['size'] ?? null;

            // Light-weight UI: one-liners per phase + progress bar during files
            if ($title === 'Scan: File') {
                if (!$filesStarted) {
                    if ($supportsSections) {
                        if (!isset($sections['progress'])) {
                            $sections['progress'] = $output->section();
                        }
                        $progress = new ProgressBar($sections['progress'], 0);
                    } else {
                        $progress = $this->output->createProgressBar();
                    }
                    $progress->start();
                    $filesStarted = true;
                }
                if ($supportsSections) {
                    if (!isset($sections['files'])) {
                        $sections['files'] = $output->section();
                    }
                    $sections['files']->overwrite("Scanning: <info>" . basename($file) . "</info>");
                } else {
                    $this->line("Scanning: " . basename($file));
                }
                if ($progress) $progress->advance();
                return;
            }

            $msg = $desc ?: $title;
            if ($supportsSections) {
                if (!isset($sections[$title])) {
                    $sections[$title] = $output->section();
                }
                $sections[$title]->overwrite($msg);
            } else {
                $this->line($msg);
            }
        };
    }

    protected function collectPluginFiles(string $basePath, array $excludeList = []): array
    {
        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS)
        );
        $files = [];
        foreach ($rii as $file) {
            $relPath = ltrim(str_replace($basePath, '', $file->getPathname()), '/\\');
            if ($this->isExcluded($relPath, $basePath)) continue;
            // apply host excludes
            $skip = false;
            foreach ($excludeList as $rule) {
                if (fnmatch($rule, $relPath)) {
                    $skip = true;
                    break;
                }
            }
            if (!$skip && is_file($file->getPathname())) {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }

    protected function makeZipFromFiles(array $files, string $manifestPath, string $zipPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create ZIP: $zipPath");
        }
        $root = dirname($manifestPath, 2);
        foreach ($files as $file) {
            $relPath = ltrim(str_replace($root, '', $file), '/\\');
            $zip->addFile($file, $relPath);
        }
        $zip->addFile($manifestPath, '.internal/manifest.json');
        $zip->close();
    }

    protected function runNpmBuild(string $dir): void
    {
        // If no package.json, skip silently
        if (!is_file($dir . '/package.json')) return;

        $proc = new Process(['npm', 'run', 'build'], $dir);
        $proc->setTimeout(600);
        $proc->run(function ($type, $buffer) {
            echo $buffer;
        });
        if (!$proc->isSuccessful()) {
            throw new RuntimeException("Build failed: " . $proc->getErrorOutput());
        }
    }

    protected function assertOutDirUnchanged(string $tempPath): void
    {
        $viteConfig = $tempPath . '/vite.config.js';
        if (!file_exists($viteConfig)) return;
        $code = (string)file_get_contents($viteConfig);
        if (preg_match("/outDir\s*:\s*['\"]([^'\"]+)['\"]/", $code, $m)) {
            $outDir = $m[1];
            if ($outDir !== 'public/build') {
                throw new RuntimeException(
                    "Packaging aborted: 'build.outDir' in vite.config.js must be 'public/build', found '$outDir'."
                );
            }
        }
    }

    protected function copyToTempWithIgnores(string $src): string
    {
        $tmp = storage_path("app/forti_pack_" . uniqid('', true));
        if (!mkdir($tmp, 0755, true) && !is_dir($tmp)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $tmp));
        }
        $patterns = array_merge($this->getDefaultIgnores(), $this->loadIgnoreConfig($src));
        $this->recursiveCopyFiltered($src, $tmp, $patterns, $src);
        return $tmp;
    }

    protected function recursiveCopyFiltered(string $from, string $to, array $patterns, string $root): void
    {
        if (is_file($from)) {
            copy($from, $to);
            return;
        }
        if (!is_dir($to) && !mkdir($to, 0755, true) && !is_dir($to)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $to));
        }
        foreach (scandir($from) ?: [] as $item) {
            if ($item === '.' || $item === '..') continue;
            $srcPath = "$from/$item";
            $dstPath = "$to/$item";
            $relPath = ltrim(str_replace($root, '', $srcPath), '/\\');
            if ($this->isExcluded($relPath, $root)) continue;
            $this->recursiveCopyFiltered($srcPath, $dstPath, $patterns, $root);
        }
    }

    protected function loadIgnoreConfig(string $pluginPath): array
    {
        $ignoreFile = rtrim($pluginPath, '/\\') . '/.scplignore';
        $patterns = [];
        if (file_exists($ignoreFile)) {
            $lines = file($ignoreFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) continue;
                // keep literal regex like "/\.env$/i"
                if (preg_match('/^\/.+\/[a-z]*$/i', $line)) {
                    $patterns[] = $line;
                } else {
                    // convert glob-ish pattern to regex
                    $rx = '/' . str_replace(['\*', '\?'], ['.*', '.'], preg_quote($line, '/')) . '/i';
                    $patterns[] = $rx;
                }
            }
        }
        return $patterns;
    }

    protected function isExcluded(string $path, ?string $pluginPath = null): bool
    {
        $rxs = $this->getDefaultIgnores();
        if ($pluginPath) {
            $rxs = array_merge($rxs, $this->loadIgnoreConfig($pluginPath));
        }
        foreach ($rxs as $rx) {
            if (@preg_match($rx, $path) && preg_match($rx, $path)) return true;
        }
        return false;
    }

    protected function getDefaultIgnores(): array
    {
        return [
            // '/\/vendor($|\/)/',
            '/\/node_modules($|\/)/',
            '/\/tests($|\/)/',
            '/\/\.git($|\/)/',
            '/\/logs($|\/)/',
            '/\/resources\/inertia\/ts($|\/)/',
            '/\/resources\/embed\/ts($|\/)/',
            '/\/resources\/shared\/ts($|\/)/',
            '/vite\.config\.(js|ts)$/',
            '/vite\.input\.(js|ts)$/',
            '/tsconfig\.json$/',
            '/\.(ts|tsx)$/',
        ];
    }

    protected function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $path = "$dir/$item";
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    protected function suggestNextVersion(string $ver): string
    {
        if (!preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $ver, $m)) {
            return $ver . '.1';
        }
        $major = (int)$m[1];
        $minor = (int)$m[2];
        $patch = (int)$m[3] + 1;
        return "$major.$minor.$patch";
    }

    /** Decode Laravel HTTP response or return ['ok'=>false] */
    private function safeJson($response): array
    {
        try {
            if (!$response) return ['ok' => false];
            $code = $response->status();
            $arr = $response->json() ?? [];
            if ($code < 200 || $code >= 300) {
                $this->error("Host API error ($code): " . ($response->body() ?? ''));
                return ['ok' => false] + (is_array($arr) ? $arr : []);
            }
            return is_array($arr) ? $arr : ['ok' => false];
        } catch (Throwable) {
            return ['ok' => false];
        }
    }
}