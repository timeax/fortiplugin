# Index 

Included Source Files (6)
- [src/Console/Commands/MakePlugin.php](#1)
- [src/Support/CliSessionManager.php](#2)
- [src/Support/Encryption.php](#3)
- [src/Traits/AuthenticateSession.php](#4)
- [src/Traits/ClientSession.php](#5)
- [src/Traits/Stubber.php](#6)

---
---
#### 1


` File: src/Console/Commands/MakePlugin.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection HtmlUnknownAttribute */

/** @noinspection NpmUsedModulesInstalled */

namespace Timeax\FortiPlugin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use JsonException;
use stdClass;
use Symfony\Component\Process\Process;
use Throwable;
use Timeax\FortiPlugin\Traits\AuthenticateSession;
use Timeax\FortiPlugin\Traits\Stubber;

class MakePlugin extends Command
{
    use AuthenticateSession, Stubber;

    protected $signature = 'fp:make
        {name : StudlyCase plugin name}
        {alias : StudlyCase plugin alias}
        {--force : Overwrite if plugin folder exists}
        {--view  : Scaffold TypeScript Inertia + Embed resource folders}
        {--no-npm : Skip npm install (CI / offline)}';

    protected $description = 'Scaffold a new plugin folder under the Plugins directory';

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    /**
     * @throws Throwable
     * @throws JsonException
     */
    public function handle(): int
    {
        // 1) Ensure session (host + bearer token)
        $session = $this->auth();
        if (!$session) return self::FAILURE;

        // 2) Validate plugin name/alias
        $studly = Str::studly($this->argument('alias'));
        $kebab = Str::kebab($studly);

        if (!preg_match('/^[a-z0-9\-_]{3,40}$/', $kebab)) {
            $this->error("Plugin alias must be 3-40 characters, lowercase a-z, 0-9, dash or underscore only.");
            return self::FAILURE;
        }

        $client = $this->getHttp();
        if (!$client) {
            $this->error('Could not create API client from your session.');
            return self::FAILURE;
        }
        $structure = $client->get('/forti/structure');
        // 3) Prepare the local path
        $base = $structure['directory'] ?? 'Plugins';
        $path = $base . DIRECTORY_SEPARATOR . $studly;

        if ($this->files->exists($path) && !$this->option('force')) {
            $this->error("Plugin '$studly' already exists locally (use --force to overwrite).");
            return self::FAILURE;
        }
        $this->files->deleteDirectory($path);
        $this->files->makeDirectory($path, 0755, true);

        // 4) Contact host: handshake (policy + verify + signature block)
        $handshake = $this->getJson($client->get('/forti/handshake'));
        if (!($handshake['ok'] ?? false)) {
            $this->error('Handshake failed.');
            return self::FAILURE;
        }

        $signatureBlock = $handshake['signature_block'] ?? null;
        if (!$signatureBlock) {
            $this->warn('Host did not return a signature_block. Continuing without it.');
        }

        // 5) Ask/derive author info (prefer from session)
        $author = [
            'name' => $session['name'] ?? $this->ask('Author name'),
            'email' => $session['email'] ?? $this->ask('Author email'),
        ];

        // 6) Init placeholder (+ placeholder token)
        $init = $this->getJson($client->post('/forti/handshake/init', [
            'slug' => $kebab,
            'name' => $studly,
        ]));

        if (!($init['ok'] ?? false)) {
            $this->error('Failed to create placeholder on host.');
            return self::FAILURE;
        }

        $placeholder = $init['placeholder'] ?? [];
        $placeholderId = $placeholder['id'] ?? null;
        $pluginKey = $placeholder['key'] ?? null;  // unique_key from server
        $phToken = $init['token'] ?? null;

        if (!$pluginKey) {
            $this->error('Host did not return a plugin key for the placeholder.');
            return self::FAILURE;
        }

        // 7) Write fortiplugin.json
        $this->files->put(
            "$path/fortiplugin.json",
            json_encode($this->defaultJson($studly, $kebab), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        // 7b) composer.json via stub
        $psr4Root = $init['psr4_root'] ?? 'Plugins'; // e.g. "Plugins"
        $this->files->put(
            "$path/composer.json",
            $this->renderStub('composer', [
                'PLUGIN_SLUG' => $kebab,
                'AUTHOR_NAME' => $author['name'] ?? '',
                'AUTHOR_EMAIL' => $author['email'] ?? '',
                'PLUGIN_ROOT_FOLDER' => $psr4Root,
                'PLUGIN_NAME' => $studly,
            ])
        );

        // 8) Write .internal files
        $internalDir = "$path/.internal";
        $this->files->makeDirectory($internalDir, 0755, true);

        // 8a) Host-provided signature block into Config.php (via stub or direct)
        // 8a) Host-provided signature block into Config.php via stub
        $this->files->put(
            "$internalDir/Config.php",
            $this->renderStub('config-dev', [
                'PLUGIN_STUDLY' => $studly,
                'PLUGIN_ALIAS' => $kebab,
                "PLUGIN_NAMESPACE" => $psr4Root,
                "PLUGIN_ID" => $init['placeholder']['id'] ?? 1,
                // host returns this:
                'SIGNATURE_BLOCK' => $signatureBlock ?? "// (signature block not returned by host)",
            ])
        );

        // 8b) Store placeholder token (single-use raw) — advise .gitignore
        if ($phToken) {
            $this->files->put(
                "$internalDir/placeholder.token.json",
                json_encode(['token' => $phToken, 'placeholder_id' => $placeholderId, 'slug' => $kebab], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
            );
        }

        // 9) Create directories
        $src = "$path/src";
        foreach (
            [
                "$src/Providers",
                "$src/Models",
                "$src/Support",
                "$src/Http/Controllers",
                "$src/Http/Middleware",
                "$path/database/migrations",
                "$path/database/factories",
                "$path/routes",
                "$path/config",
                "$path/public",
                "$path/public/index.php",
                "$path/resources/shared/ts",
            ] as $dir
        ) {
            $this->files->ensureDirectoryExists($dir);
        }

        // 10) Optional TS/Vite scaffold
        if ($this->option('view')) {
            $this->scaffoldViewAssets($path);
            if (!$this->option('no-npm')) {
                $this->runNpmInstall($path);
                $this->runTailwindInit($path);
            }
        }

        // 11) composer dump-autoload (host project)
        if ($this->files->exists(base_path('composer.json'))) {
            $this->line('> composer dump-autoload');
            (new Process(['composer', 'dump-autoload']))->run(fn($t, $b) => $this->output->write($b));
        }

        // 12) publish.json
        $publishPath = $path . "/publish.json";
        if ($this->files->exists($publishPath) && $this->confirm("publish.json already exists. Overwrite?")) {
            $this->info("Skipping publish.json overwrite.");
            return self::SUCCESS;
        }

        $this->files->put($publishPath, json_encode([
            'host' => $session['host'],
            'plugin_slug' => $kebab,
            'plugin_key' => $pluginKey,
            'author' => $author,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("Plugin '$studly' scaffolded.");
        return self::SUCCESS;
    }

    /* ───────────────────────── Helpers ───────────────────────── */

    protected function defaultJson(string $studly, string $kebab): array
    {
        return [
            '$schema' => 'https://github.com/timeax/fortiplugin/blob/main/schema/fortiplugin.schema.json',
            'name' => $studly,
            'alias' => $kebab,
            'description' => '',
            'version' => '0.1.0',
            'providers' => [],

            // Map<string, DependencySpec> → must be an object
            'dependencies' => new stdClass(),

            // Array<HostConfig>
            'hostConfig' => [],

            // { items: UiItem[] }
            'uiConfig' => [
                'items' => [],
            ],

            // { dir: string; glob?: string }
            'routes' => [
                'dir' => 'routes',
                'glob' => '**/*.routes.json',
            ],

            // Record<Slug, ExportDefinition> → must be an object
            'exports' => new stdClass(),
        ];
    }

    /**
     * Tiny wrapper to safely decode Guzzle responses.
     * @throws JsonException
     */
    protected function getJson($response): array
    {
        $code = $response->getStatusCode();
        $body = (string)$response->getBody();
        if ($code < 200 || $code >= 300) {
            $this->error("Host API error ($code): " . ($body ?: ''));
            return ['ok' => false];
        }
        return json_decode($body, true, 512, JSON_THROW_ON_ERROR) ?? [];
    }

    protected function scaffoldViewAssets(string $pluginPath): void
    {
        // Inertia entry + sample page
        $this->files->ensureDirectoryExists("$pluginPath/resources/inertia/ts/Pages");
        $this->files->put(
            "$pluginPath/resources/inertia/ts/app.tsx",
            <<<TS
import React from 'react';
import { createInertiaApp } from '@inertiajs/react';

createInertiaApp({
  resolve: (name) => import(\`./Pages/\${name}.tsx\`),
  setup({ el, App, props }) {
    return <App {...props} />;
  },
});
TS
        );
        $this->files->put(
            "$pluginPath/resources/inertia/ts/Pages/Welcome.tsx",
            "export default () => <h1 className='text-2xl font-bold'>Welcome from {$this->argument('name')}</h1>;"
        );

        // Embed sample component
        $this->files->ensureDirectoryExists("$pluginPath/resources/embed/ts/pages");
        $this->files->ensureDirectoryExists("$pluginPath/resources/embed/ts/addons");
        $this->files->put(
            "$pluginPath/resources/embed/ts/Hello.tsx",
            "export default () => <div className='p-2'>Embedded Hello!</div>;"
        );

        // vite input map
        $this->files->put(
            "$pluginPath/resources/embed/vite.input.js",
            $this->renderStub("viteInputGen")
        );

        // vite.config.js
        $this->files->put(
            "$pluginPath/vite.config.js",
            $this->renderStub("viteConfig")
        );

        // tsconfig.json
        $this->files->put(
            "$pluginPath/tsconfig.json",
            $this->renderStub("tsconfig")
        );

        // package.json (bare)
        $this->files->put(
            "$pluginPath/package.json",
            <<<JSON
{
  "name": "{$this->argument('name')}",
  "private": true,
  "type": "module",
  "scripts": {
    "dev": "vite dev",
    "build": "vite build",
    "type-check": "tsc --noEmit"
  }
}
JSON
        );
    }

    protected function runNpmInstall(string $cwd): void
    {
        $this->info('Running npm install…');
        $cmd = [
            'npm', 'install', '-D',
            'vite', 'typescript', '@vitejs/plugin-react',
            '@types/react', '@types/react-dom',
            'tailwindcss'
        ];
        (new Process($cmd, $cwd))->setTimeout(600)->run(fn($t, $b) => $this->output->write($b));
    }

    protected function runTailwindInit(string $cwd): void
    {
        $this->line('Initializing Tailwind config…');
        (new Process(['npx', 'tailwindcss', 'init', '-p'], $cwd))->run();
    }
}
```

---
#### 2


` File: src/Support/CliSessionManager.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection SpellCheckingInspection */

/** @noinspection GrazieInspection */

namespace Timeax\FortiPlugin\Support;

use JsonException;
use RuntimeException;
use SodiumException;

class CliSessionManager
{
    // Timeax\FortiPlugin\Support\CliSessionManager

    protected static function sessionsPath(): string
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE');
        return $home . DIRECTORY_SEPARATOR . '.fortiplugin' . DIRECTORY_SEPARATOR . 'sessions.json';
    }

    /**
     * @throws SodiumException
     * @throws JsonException
     */
    public static function loadSessions(): array
    {
        if (!file_exists(self::sessionsPath())) {
            return ['current' => null, 'hosts' => []];
        }
        // Read and decrypt the sessions file
        $encrypted = file_get_contents(self::sessionsPath());
        $plaintext = Encryption::decrypt($encrypted);
        if (!$plaintext) {
            return ['current' => null, 'hosts' => []];
        }
        // Decode JSON, handle errors
        $sessions = json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($sessions)) {
            return ['current' => null, 'hosts' => []];
        }

        // Remove expired hosts
        $now = time();
        foreach ($sessions['hosts'] as $alias => $info) {
            if (isset($info['expires_at']) && strtotime($info['expires_at']) < $now) {
                unset($sessions['hosts'][$alias]);
            }
        }

        // If current is expired or missing, reset to first valid
        if (empty($sessions['hosts'])) {
            $sessions['current'] = null;
        } elseif (!isset($sessions['hosts'][$sessions['current']])) {
            $sessions['current'] = array_key_first($sessions['hosts']);
        }

        // Optionally: write back cleaned file
        self::writeSessions($sessions);

        return $sessions;
    }

    /**
     * Save a session with alias (user-defined name), host, token, expiresAt
     * @throws JsonException
     * @throws SodiumException
     */
    public static function saveSession($alias, $host, $token, $expiresAt, $author): void
    {
        $sessions = self::loadSessions();
        $sessions['hosts'][$alias] = [
            'alias' => $alias,
            'host' => $host,
            'token' => $token,
            'name' => $author['name'],
            "email" => $author['email'],
            'expires_at' => $expiresAt
        ];
        $sessions['current'] = $alias;
        self::writeSessions($sessions);
    }

    /**
     * Set the current active alias (by alias or host)
     * @throws JsonException
     * @throws SodiumException
     */
    public static function setCurrent($aliasOrHost): bool
    {
        $sessions = self::loadSessions();
        foreach ($sessions['hosts'] as $alias => $info) {
            if ($alias === $aliasOrHost || $info['host'] === $aliasOrHost) {
                $sessions['current'] = $alias;
                self::writeSessions($sessions);
                return true;
            }
        }
        return false;
    }

    /**
     * Get the current session info (alias, host, token, expires_at)
     * @throws SodiumException|JsonException
     */
    public static function getCurrentSession()
    {
        $sessions = self::loadSessions();
        $current = $sessions['current'];
        return $current && isset($sessions['hosts'][$current]) ? $sessions['hosts'][$current] : null;
    }

    /**
     * @throws JsonException
     * @throws SodiumException
     */
    public static function getToken()
    {
        $session = self::getCurrentSession();
        if (!$session) {
            return null;
        }
        if (isset($session['expires_at']) && strtotime($session['expires_at']) < time()) {
            self::removeHost($session['alias']);
            return null;
        }
        return $session['token'];
    }

    /**
     * @throws SodiumException
     * @throws JsonException
     */
    public static function getHost()
    {
        $session = self::getCurrentSession();
        return $session['host'] ?? null;
    }

    /**
     * @throws SodiumException
     * @throws JsonException
     */
    public static function getSession($hostOrAlias)
    {
        $sessions = self::loadSessions();
        if (empty($sessions['hosts'])) {
            return null;
        }

        // Check by alias first
        if (isset($sessions['hosts'][$hostOrAlias])) {
            return $sessions['hosts'][$hostOrAlias];
        }

        // Otherwise, try by host domain value
        /** @noinspection PhpUnusedLocalVariableInspection */
        foreach ($sessions['hosts'] as $alias => $session) {
            if (($session['host'] ?? null) === $hostOrAlias) {
                return $session;
            }
        }

        return null;
    }

    /**
     * @throws JsonException
     * @throws SodiumException
     */
    public static function getAlias()
    {
        $session = self::getCurrentSession();
        return $session['alias'] ?? null;
    }

    /**
     * List all saved sessions, returns [alias => info]
     * @throws SodiumException|JsonException
     */
    public static function listHosts()
    {
        $sessions = self::loadSessions();
        return $sessions['hosts'];
    }

    /**
     * Remove a session (by alias or host)
     * @throws JsonException
     * @throws SodiumException
     */
    public static function removeHost($aliasOrHost): bool
    {
        $sessions = self::loadSessions();
        foreach ($sessions['hosts'] as $alias => $info) {
            if ($alias === $aliasOrHost || $info['host'] === $aliasOrHost) {
                unset($sessions['hosts'][$alias]);
                if ($sessions['current'] === $alias) {
                    $sessions['current'] = count($sessions['hosts']) ? array_key_first($sessions['hosts']) : null;
                }
                self::writeSessions($sessions);
                return true;
            }
        }
        return false;
    }

    /**
     * @throws JsonException
     */
    protected static function writeSessions($sessions): void
    {
        $dir = dirname(self::sessionsPath());
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }
        $plaintext = json_encode($sessions, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        $encrypted = Encryption::encrypt($plaintext);
        file_put_contents(self::sessionsPath(), $encrypted);
    }

    /**
     * @throws JsonException
     * @throws SodiumException
     */
    public static function autoAlias($domain): array|string
    {
        // e.g., plugins.examplehost.com => examplehost
        $parts = explode('.', $domain);
        $base = (count($parts) >= 2) ? $parts[count($parts) - 2] : str_replace(['.', '-'], '_', $domain);
        $alias = $base;
        $sessions = self::loadSessions();
        $i = 2;
        while (isset($sessions['hosts'][$alias])) {
            $alias = $base . $i; // examplehost2, examplehost3, etc.
            $i++;
        }
        return $alias;
    }
}
```

---
#### 3


` File: src/Support/Encryption.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Support;


use JsonException;
use SodiumException;

class Encryption
{
    /**
     * @throws
     */
    public static function encrypt(string $plaintext, int $numShards = 7): string
    {
        $encryptionKey = sodium_bin2base64(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES), SODIUM_BASE64_VARIANT_URLSAFE);
        $shards = self::splitKeyIntoShards($encryptionKey, $numShards);

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, sodium_base642bin($encryptionKey, SODIUM_BASE64_VARIANT_URLSAFE));
        $payload = [
            'nonce' => base64_encode($nonce),
            'ciphertext' => base64_encode($ciphertext),
        ];
        $payloadEncoded = base64_encode(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        // Embed key shards as labeled markers in pseudo-random positions in payload
        $block = $payloadEncoded;
        $positions = self::calculateShardPositions($block, $numShards);
        foreach ($positions as $i => $pos) {
            $block = substr($block, 0, $pos) . "[KEY$i=$shards[$i]]" . substr($block, $pos);
        }
        // Encode the map as a hidden suffix
        $block .= "\n--KEYMAP:" . base64_encode(json_encode(['count' => $numShards], JSON_THROW_ON_ERROR)) . "--";
        return $block;
    }

    /**
     * @throws SodiumException|JsonException
     */
    public static function decrypt(string $encrypted): ?string
    {
        // Find keymap (required for number of shards)
        if (!preg_match('/--KEYMAP:([A-Za-z0-9+\/=_-]+)--/', $encrypted, $m)) {
            return null;
        }
        $keymap = json_decode(base64_decode($m[1]), true, 512, JSON_THROW_ON_ERROR);
        $numShards = $keymap['count'] ?? 7;

        // Remove keymap for base64 decode
        $main = preg_replace('/--KEYMAP:([A-Za-z0-9+\/=_-]+)--/', '', $encrypted);

        // Extract shards in order
        $shards = [];
        for ($i = 0; $i < $numShards; $i++) {
            if (preg_match("/\[KEY$i=([A-Za-z0-9+\/=_-]+)]/", $main, $matches)) {
                $shards[$i] = $matches[1];
                // Remove marker from main so it doesn't mess up offsets for next
                $main = str_replace($matches[0], '', $main);
            }
        }
        ksort($shards);
        $key = implode('', $shards);

        // Now base64-decode, then decrypt
        $payload = json_decode(base64_decode($main), true, 512, JSON_THROW_ON_ERROR);
        if (!$payload || !isset($payload['nonce'], $payload['ciphertext'])) {
            return null;
        }

        $nonce = base64_decode($payload['nonce']);
        $ciphertext = base64_decode($payload['ciphertext']);
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, sodium_base642bin($key, SODIUM_BASE64_VARIANT_URLSAFE));
        return $plaintext === false ? null : $plaintext;
    }

    // Helper: split key into N shards
    private static function splitKeyIntoShards(string $key, int $numShards): array
    {
        $len = strlen($key);
        $shardSize = (int)ceil($len / $numShards);
        $shards = [];
        for ($i = 0; $i < $numShards; $i++) {
            $shards[] = substr($key, $i * $shardSize, $shardSize);
        }
        return $shards;
    }

    // Helper: find pseudo-random insert positions for markers
    private static function calculateShardPositions(string $block, int $numShards): array
    {
        $positions = [];
        $len = strlen($block);
        for ($i = 0; $i < $numShards; $i++) {
            // Example: offset is (block len / (numShards+1)) * (i+1), +i to scramble a bit
            $positions[] = min(
                (int)(($len / ($numShards + 1)) * ($i + 1)) + $i,
                $len - 1
            );
        }
        arsort($positions); // Insert from the end for stable offsets
        return array_values($positions);
    }

    /**
     * @throws JsonException
     */
    public static function encryptFile($inputFile, $outputFile): void
    {
        $data = file_get_contents($inputFile);
        $encrypted = self::encrypt($data); // Pass the key explicitly
        file_put_contents($outputFile, $encrypted);
    }

    /**
     * @throws SodiumException
     * @throws JsonException
     */
    public static function decryptFile($inputFile, $outputFile): void
    {
        $data = file_get_contents($inputFile);
        $encrypted = self::decrypt($data); // Pass the key explicitly
        file_put_contents($outputFile, $encrypted);
    }
}
```

---
#### 4


` File: src/Traits/AuthenticateSession.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Traits;

use JsonException;
use SodiumException;
use Throwable;
use Timeax\FortiPlugin\Support\CliSessionManager;

trait AuthenticateSession
{
    use ClientSession;

    /**
     * Ensure we have a valid host session, or guide the user to create/switch one.
     * Returns the active session array: ['alias','host','token','expires_at',...]
     *
     * @throws Throwable
     */
    protected function auth(): ?array
    {
        $session = CliSessionManager::getCurrentSession();

        if ($session) {
            $proceed = $this->confirm("You are logged in to host '{$session['host']}'. Proceed?", true);
            if ($proceed) return $session;

            $action = $this->choice(
                'Choose an action',
                ['Switch Host', 'Login to New Host', 'Abort'],
                0
            );

            if ($action === 'Switch Host') {
                $session = $this->switchHost();
                if ($session) return $session;
                $this->error('No session selected. Aborted.');
                return null;
            }

            if ($action === 'Login to New Host') {
                $session = $this->loginToNewHost();
                if ($session) return $session;
                $this->error('Login failed. Aborted.');
                return null;
            }

            $this->info('Aborted at user request.');
            return null;
        }

        // Not logged in at all
        $this->warn('You are not logged in to any host.');
        if ($this->confirm('Do you want to login now?', true)) {
            $session = $this->loginToNewHost();
            if ($session) return $session;
            $this->error('Login failed. Aborted.');
            return null;
        }

        $this->error('You must be logged in to scaffold a plugin.');
        return null;
    }

    /**
     * Let the user select another saved host and switch.
     *
     * @throws JsonException
     * @throws SodiumException
     */
    protected function switchHost(): ?array
    {
        $hosts = CliSessionManager::listHosts();
        if (empty($hosts)) {
            $this->warn('No saved hosts found.');
            return null;
        }

        $options = [];
        foreach ($hosts as $alias => $info) {
            $options[] = "$alias ({$info['host']})";
        }

        $picked = $this->choice('Select a host to switch to', $options, 0);
        $spacePos = strpos($picked, ' ');
        $alias    = $spacePos === false ? $picked : substr($picked, 0, $spacePos);

        if (CliSessionManager::setCurrent($alias)) {
            $session = CliSessionManager::getCurrentSession();
            $this->info("Switched to host '{$session['host']}'.");
            return $session;
        }

        $this->warn('Failed to switch to selected host.');
        return null;
    }

    /**
     * Initiates the login process by calling your forti:login command.
     *
     * @throws JsonException
     * @throws SodiumException
     */
    protected function loginToNewHost(): ?array
    {
        $raw  = trim($this->ask('Enter host (domain or full URL)'));
        $host = $this->normalizeBaseUri($raw);

        $this->call('forti:login', ['--host' => $host]);

        $session = CliSessionManager::getCurrentSession();
        if ($session) {
            $this->info('Login successful. Proceeding…');
            return $session;
        }

        $this->warn('Login failed or not completed.');
        return null;
    }

    /** Resolve plugin path under configured directory. */
    public function getPath(string $relative): string
    {
        $pluginDir = rtrim(config('fortiplugin.directory', base_path('Plugins')), DIRECTORY_SEPARATOR);
        return $pluginDir . DIRECTORY_SEPARATOR . ltrim($relative, DIRECTORY_SEPARATOR);
    }
}
```

---
#### 5


` File: src/Traits/ClientSession.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Traits;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use JsonException;
use SodiumException;
use Timeax\FortiPlugin\Support\CliSessionManager;

trait ClientSession
{
    /**
     * Get an auth'd Laravel HTTP client for the *current* saved session.
     * Uses the main login bearer token (long-lived).
     * @throws JsonException|SodiumException
     */
    public function getHttp(): ?PendingRequest
    {
        return $this->makeHttp($this->getSession());
    }

    /**
     * Get an auth'd Laravel HTTP client for a specific host (alias or host).
     * @throws JsonException|SodiumException
     */
    public function getHttpByHost(string $hostOrAlias): ?PendingRequest
    {
        return $this->makeHttp($this->getSession($hostOrAlias));
    }

    /**
     * Build a PendingRequest from a raw session array (adds Authorization Bearer).
     */
    public function makeHttp(?array $session): ?PendingRequest
    {
        if (!$session || empty($session['host']) || empty($session['token'])) {
            return null;
        }

        return Http::baseUrl($this->normalizeBaseUri((string)$session['host']))
            ->timeout(20)
            ->connectTimeout(5)
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'FortiPlugin-CLI'])
            // Use ONLY the main login bearer here.
            ->withToken($session['token']);
    }

    /**
     * Build a client that uses a short-lived *placeholder token* (NOT a bearer).
     * Use this for pack/placeholder-scoped endpoints.
     * @throws JsonException|SodiumException
     */
    public function httpWithPlaceholderToken(string $placeholderToken, ?array $session = null): ?PendingRequest
    {
        $session ??= $this->getSession();
        if (!$session || empty($session['host'])) return null;

        return Http::baseUrl($this->normalizeBaseUri((string)$session['host']))
            ->timeout(20)
            ->connectTimeout(5)
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => 'FortiPlugin-CLI',
                'X-Forti-Placeholder' => $placeholderToken,
            ]);
    }

    /**
     * Build a client that uses an ephemeral *handshake ticket* (NOT a bearer).
     * Use this for the second handshake / pack verification window.
     * @throws JsonException|SodiumException
     */
    public function httpWithHandshakeTicket(string $ticket, ?array $session = null): ?PendingRequest
    {
        $session ??= $this->getSession();
        if (!$session || empty($session['host'])) return null;

        return Http::baseUrl($this->normalizeBaseUri((string)$session['host']))
            ->timeout(20)
            ->connectTimeout(5)
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => 'FortiPlugin-CLI',
                'X-Forti-Handshake' => $ticket,
            ]);
    }

    /**
     * Fetch a session:
     *  - null → current session (or null if none)
     *  - string → lookup by alias OR host
     * @throws JsonException|SodiumException
     */
    public function getSession(?string $hostOrAlias = null): ?array
    {
        return $hostOrAlias === null
            ? CliSessionManager::getCurrentSession()
            : CliSessionManager::getSession($hostOrAlias);
    }

    /* ───────────────────────────────────────────────────── */

    /** Reuse ClientSession normalizer to keep a consistent base URI format. */
    protected function normalizeBaseUri(string $host): string
    {
        $h = trim($host);
        if (!preg_match('~^https?://~i', $h)) {
            $h = 'https://' . $h;
        }
        return rtrim($h, '/');
    }
}
```

---
#### 6


` File: src/Traits/Stubber.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Traits;

use RuntimeException;
use Throwable;

trait Stubber
{
    protected function renderStub(string $name, array $params = []): string
    {
        try {
            $stubDir = dirname(__DIR__) . '/../stubs';
            $path = str_ends_with($name, '.stub') ? "$stubDir/$name" : "$stubDir/$name.stub";
            if (!file_exists($path)) {
                throw new RuntimeException("Stub not found: $path");
            }

            $contents = file_get_contents($path);
            $contents = preg_replace('/IGNORE;[ \t]*\R?/', '', $contents);
            extract($params, EXTR_SKIP);

            return preg_replace_callback('/#\{(.+?)}/s', static function ($m) use (&$params) {
                return (static function () use ($m, $params) {
                    extract($params, EXTR_SKIP);

                    $expr = trim($m[1]);

                    // If it's a bare identifier like `foo`, turn it into `$foo`
                    if ($expr !== '' && $expr[0] !== '$' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $expr)) {
                        $expr = '$' . $expr;
                    }

                    return eval('return ' . $expr . ';');
                })();
            }, $contents);
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to render stub: {$e->getMessage()}, stub: $name");
        }
    }
}
```


---
*Generated with [Prodex](https://github.com/emxhive/prodex) — Codebase decoded.*
<!-- PRODEx v1.4.7 | 2025-12-02T11:22:55.124Z -->