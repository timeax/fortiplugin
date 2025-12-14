# Index 

Included Source Files (72)
- [src/Console/Commands/ChangeHostCommand.php](#1)
- [src/Console/Commands/CreateAuthorCommand.php](#2)
- [src/Console/Commands/GenerateHostKeyCommand.php](#3)
- [src/Console/Commands/ListHostsCommand.php](#4)
- [src/Console/Commands/LoginCommand.php](#5)
- [src/Console/Commands/LogoutCommand.php](#6)
- [src/Console/Commands/MakePlugin.php](#7)
- [src/Console/Commands/PackPlugin.php](#8)
- [src/Console/Commands/Shared.php](#9)
- [src/Console/Commands/ValidatePlugin.php](#10)
- [src/Contracts/ConfigInterface.php](#11)
- [src/Core/ChecksModulePermission.php](#12)
- [src/Core/Exceptions/DuplicateSettingIdException.php](#13)
- [src/Core/Exceptions/HostConfigException.php](#14)
- [src/Core/PluginPolicy.php](#15)
- [src/Core/Security/CallGraphAnalyzer.php](#16)
- [src/Core/Security/ComposerScan.php](#17)
- [src/Core/Security/Concerns/ResolvesNames.php](#18)
- [src/Core/Security/ConfigValidator.php](#19)
- [src/Core/Security/ContentValidator.php](#20)
- [src/Core/Security/FileScanner.php](#21)
- [src/Core/Security/HostConfigValidator.php](#22)
- [src/Core/Security/PermissionManifestValidator.php](#23)
- [src/Core/Security/PluginSecurityScanner.php](#24)
- [src/Core/Security/RouteFileValidator.php](#25)
- [src/Core/Security/RouteIdRegistry.php](#26)
- [src/Core/Security/TokenUsageAnalyzer.php](#27)
- [src/Enums/AuthorStatus.php](#28)
- [src/Enums/IssueStatus.php](#29)
- [src/Enums/KeyPurpose.php](#30)
- [src/Enums/PermissionType.php](#31)
- [src/Enums/PluginSettingValueType.php](#32)
- [src/Enums/PluginStatus.php](#33)
- [src/Enums/RoutePermissionStatus.php](#34)
- [src/Enums/ValidationStatus.php](#35)
- [src/Exceptions/DuplicateRouteIdException.php](#36)
- [src/Exceptions/PermissionDeniedException.php](#37)
- [src/Exceptions/PluginContextException.php](#38)
- [src/Lib/Obfuscator.php](#39)
- [src/Lib/Utils/ObfuscatorUtil.php](#40)
- [src/Models/AuditLog.php](#41)
- [src/Models/Author.php](#42)
- [src/Models/AuthorToken.php](#43)
- [src/Models/HostKey.php](#44)
- [src/Models/PermissionTag.php](#45)
- [src/Models/PermissionTagItem.php](#46)
- [src/Models/Plugin.php](#47)
- [src/Models/PluginAuditLog.php](#48)
- [src/Models/PluginIssue.php](#49)
- [src/Models/PluginIssueMessage.php](#50)
- [src/Models/PluginPermission.php](#51)
- [src/Models/PluginPermissionTag.php](#52)
- [src/Models/PluginPlaceholder.php](#53)
- [src/Models/PluginRoutePermission.php](#54)
- [src/Models/PluginSetting.php](#55)
- [src/Models/PluginSignature.php](#56)
- [src/Models/PluginToken.php](#57)
- [src/Models/PluginVersion.php](#58)
- [src/Models/PluginZip.php](#59)
- [src/Permissions/Evaluation/Dto/PermissionListResult.php](#60)
- [src/Permissions/Evaluation/Dto/PermissionListSummary.php](#61)
- [src/Permissions/Support/HostConfigNormalizer.php](#62)
- [src/Services/ErrorReaderService.php](#63)
- [src/Services/HostKeyService.php](#64)
- [src/Services/PolicyService.php](#65)
- [src/Services/ValidatorService.php](#66)
- [src/Support/CliSessionManager.php](#67)
- [src/Support/Encryption.php](#68)
- [src/Support/PluginContext.php](#69)
- [src/Traits/AuthenticateSession.php](#70)
- [src/Traits/ClientSession.php](#71)
- [src/Traits/Stubber.php](#72)

---
---
#### 1


` File: src/Console/Commands/ChangeHostCommand.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Console\Commands;

use Illuminate\Console\Command;
use JsonException;
use SodiumException;
use Timeax\FortiPlugin\Support\CliSessionManager;

class ChangeHostCommand extends Command
{
    protected $signature = 'fp:change {aliasOrHost?}';
    protected $description = 'Switch to a different saved host session (by alias or domain)';

    /**
     * @throws JsonException
     * @throws SodiumException
     */
    public function handle(): int
    {
        $sessions = CliSessionManager::listHosts();
        if (empty($sessions)) {
            $this->error('No saved hosts/sessions found. Please login to at least one host first.');
            return 1;
        }

        $aliasOrHost = $this->argument('aliasOrHost');

        // If no argument, or not found, show choices
        if (!$aliasOrHost || !self::resolveAlias($aliasOrHost, $sessions)) {
            $options = [];
            foreach ($sessions as $alias => $info) {
                $options[] = "$alias ({$info['host']})";
            }
            $picked = $this->choice('Select a session alias or host to activate', $options);
            // Parse alias back from "alias (host)"
            $aliasOrHost = explode(' ', $picked)[0];
        }

        $realAlias = self::resolveAlias($aliasOrHost, $sessions);

        if ($realAlias && CliSessionManager::setCurrent($realAlias)) {
            $session = $sessions[$realAlias];
            $this->info("[✓] Switched to alias: $realAlias ({$session['host']})");
            return 0;
        }

        $this->error("No session found for alias or host: $aliasOrHost");
        return 1;
    }

    /**
     * Given an alias or a host, find the corresponding alias in $sessions
     */
    protected static function resolveAlias($aliasOrHost, $sessions): int|string|null
    {
        foreach ($sessions as $alias => $info) {
            if ($alias === $aliasOrHost || $info['host'] === $aliasOrHost) {
                return $alias;
            }
        }
        return null;
    }
}
```

---
#### 2


` File: src/Console/Commands/CreateAuthorCommand.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;
use Timeax\FortiPlugin\Models\Author;

class CreateAuthorCommand extends Command
{
    /** @var string */
    protected $signature = 'fp:author 
        {--email= : Optional email for the author} 
        {--password= : Optional plaintext password; if omitted, a strong one is generated}';

    /** @var string */
    protected $description = 'Create a new FortiPlugin Author (with optional email/password).';

    public function handle(): int
    {
        try {
            // Resolve email (validate if provided; otherwise generate a unique one)
            $email = (string)($this->option('email') ?? '');
            if ($email !== '') {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->error('Invalid email format.');
                    return self::FAILURE;
                }
                if (Author::query()->where('email', $email)->exists()) {
                    $this->error("Email already exists: {$email}");
                    return self::FAILURE;
                }
            } else {
                $email = $this->generateUniqueEmail();
            }

            // Resolve password (use provided or generate)
            $plainPassword = (string)($this->option('password') ?? '');
            if ($plainPassword === '') {
                $plainPassword = $this->generatePassword();
            }

            // Derive slug/name (simple, deterministic defaults)
            $local = Str::before($email, '@');
            $name = $this->humanizeHandle($local);
            $slug = $this->uniqueSlug('author-' . Str::slug($local));

            $author = new Author();
            $author->email = $email;
            $author->password = Hash::make($plainPassword);
            $author->name = $name;
            $author->slug = $slug;
            // $author->status = 'active';          // uncomment if your schema requires it
            // $author->verified = false;           // uncomment if your schema requires it
            $author->save();

            $this->info('Author created successfully.');
            $this->line('');
            $this->line('== Credentials =======================');
            $this->line('Email:    ' . $email);
            $this->line('Password: ' . $plainPassword);
            $this->line('======================================');
            $this->line('');
            $this->line('Keep this password safe. (Only shown once.)');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Failed to create author: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function generateUniqueEmail(): string
    {
        // Example domain; adjust if you prefer using your app domain
        $domain = 'example.com';
        do {
            $local = 'author-' . Str::lower(Str::random(10));
            $email = "{$local}@{$domain}";
        } while (Author::query()->where('email', $email)->exists());

        return $email;
    }

    private function generatePassword(): string
    {
        // 20-char mixed password; customize to your password policy
        return Str::password(20);
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 1;
        while (Author::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }

    private function humanizeHandle(string $handle): string
    {
        // Turn "john-doe_123" into "John Doe 123"
        $h = preg_replace('/[-_]+/', ' ', $handle);
        return trim(Str::title((string)$h));
    }
}
```

---
#### 3


` File: src/Console/Commands/GenerateHostKeyCommand.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Throwable;
use Timeax\FortiPlugin\Services\HostKeyService;

class GenerateHostKeyCommand extends Command
{
    /** @var string */
    protected $signature = 'fp:generate
        {purpose? : Optional purpose/label for this host key}
        {--purpose= : Optional purpose/label (overrides the positional argument if set)}';

    /** @var string */
    protected $description = 'Generate a new host key pair using HostKeyService.';

    public function handle(): int
    {
        try {
            $purposeArg = $this->argument('purpose');
            $purposeOpt = $this->option('purpose');
            $purpose = $purposeOpt !== null && $purposeOpt !== '' ? $purposeOpt : ($purposeArg ?: null);

            /** @var HostKeyService $service */
            $service = $this->laravel->make(HostKeyService::class);

            // Call generate with optional purpose
            $result = $service->generate($purpose);

            // Prefer not to print private key material. Summarize safely.
            $payload = is_object($result) && method_exists($result, 'toArray')
                ? $result->toArray()
                : (is_array($result) ? $result : []);

            $id = Arr::get($payload, 'id') ?? Arr::get($payload, 'key_id') ?? Arr::get($payload, 'kid');
            $purposeOut = Arr::get($payload, 'purpose', $purpose);
            $fingerprint = Arr::get($payload, 'fingerprint') ?? Arr::get($payload, 'fp');
            $publicKey = Arr::get($payload, 'public_key') ?? Arr::get($payload, 'public') ?? Arr::get($payload, 'public_pem');

            $this->info('Host key generated successfully.');
            $this->line('');
            $this->line('== Host Key =======================================');
            if ($id !== null) {
                $this->line('ID:           ' . $id);
            }
            if ($purposeOut !== null) {
                $this->line('Purpose:      ' . $purposeOut);
            }
            if ($fingerprint) {
                $this->line('Fingerprint:  ' . $fingerprint);
            }
            if ($publicKey) {
                $this->line('Public Key:');
                $this->line($publicKey);
            }
            $this->line('===================================================');
            $this->line('');
            $this->line('Note: Private key material is stored securely and not printed here.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Failed to generate host key: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
```

---
#### 4


` File: src/Console/Commands/ListHostsCommand.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Console\Commands;

use Illuminate\Console\Command;
use JsonException;
use SodiumException;
use Timeax\FortiPlugin\Support\CliSessionManager;

class ListHostsCommand extends Command
{
    protected $signature = 'fp:hosts';
    protected $description = 'List all saved hosts and current session';

    /**
     * @throws SodiumException
     * @throws JsonException
     */
    public function handle(): void
    {
        $sessions = CliSessionManager::loadSessions();
        $current = $sessions['current'] ?? null;
        $this->info('Saved Hosts:');
        foreach ($sessions['hosts'] as $name => $details) {
            $this->line(($name === $current ? '[*]' : '[-]') . " $name: " . $details['host']);
        }
    }
}
```

---
#### 5


` File: src/Console/Commands/LoginCommand.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Console\Commands;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use JsonException;
use SodiumException;
use Timeax\FortiPlugin\Support\CliSessionManager;
use GuzzleHttp\Client;

class LoginCommand extends Command
{
    protected $signature = 'fp:login {--host=} {--alias=} {--remember}';
    protected $description = 'Log in to Secure Plugin host and save session token.';

    /**
     * @throws GuzzleException
     * @throws SodiumException
     * @throws JsonException
     */
    public function handle(): int
    {
        $host = $this->option('host') ?: $this->ask('Enter host domain (no https://)');
        $suggestedAlias = $this->option('alias') ?: CliSessionManager::autoAlias($host);

        // Prompt with auto-generated alias (dev can override)
        $alias = $this->ask(
            "Enter a session alias for this host (default: $suggestedAlias)",
            $suggestedAlias
        );

        $existingAliases = array_keys(CliSessionManager::listHosts());

        while (in_array($alias, $existingAliases, true)) {
            $this->warn("Alias '$alias' already exists for another session.");
            if ($this->confirm("Do you want to overwrite the existing session for alias '$alias'?")) {
                break; // Will overwrite below
            }

            $this->info("Existing aliases:");
            foreach ($existingAliases as $al) {
                $this->line(" - $al");
            }
            $alias = $this->ask('Enter a different session alias');
        }

        $email = $this->ask('Email');
        $password = $this->secret('Password');
        $remember = $this->option('remember');

        // Compose API base URI
        $apiBase = 'https://' . $host;

        $client = new Client(['base_uri' => $apiBase]);

        try {
            $res = $client->post('/forti/login', [
                'json' => [
                    'email' => $email,
                    'password' => $password,
                    'remember' => $remember,
                ]
            ]);
            $data = json_decode($res->getBody(), true, 512, JSON_THROW_ON_ERROR);

            if (empty($data['token'])) {
                $this->error('Login failed: Invalid response from server.');
                return 1;
            }

            $expires = $data['expires_at'] ?? (now()->addDays($remember ? 30 : 1)->toIso8601String());
            CliSessionManager::saveSession($alias, $host, $data['token'], $expires, $data['author']);

            $this->info("[✓] Login successful! Session saved as alias: $alias ($host)" . ($remember ? ' (30 days)' : ' (24 hours)') . '.');
            return 0;
        } catch (Exception $e) {
            $this->error('Login failed: ' . $e->getMessage());
            return 1;
        }
    }
}
```

---
#### 6


` File: src/Console/Commands/LogoutCommand.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Console\Commands;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use JsonException;
use SodiumException;
use Timeax\FortiPlugin\Support\CliSessionManager;
use GuzzleHttp\Client;

class LogoutCommand extends Command
{
    protected $signature = 'fp:logout {--host=}';
    protected $description = 'Clear saved CLI session and logout.';

    /**
     * @throws SodiumException
     * @throws JsonException
     * @throws GuzzleException
     */
    public function handle(): int
    {
        $selection = $this->option('host') ?: $this->choice('Select host to logout from', $this->getSavedHosts());

        // Resolve selection (could be alias or host) to a session
        $session = $selection ? CliSessionManager::getSession($selection) : null;
        if (!$session) {
            $this->error('Failed to find a saved session for the provided host/alias.');
            return 1;
        }

        $host = $session['host'] ?? null;
        $token = $session['token'] ?? null;
        if (!$host || !$token) {
            $this->error('Saved session is missing host or token.');
            return 1;
        }

        $apiBase = 'https://' . $host;
        $client = new Client(['base_uri' => $apiBase, 'http_errors' => false]);
        $response = $client->post('forti/logout', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ],
        ]);

        // Regardless of API response, clear the local session for the selected alias/host
        CliSessionManager::removeHost($selection);

        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300) {
            $this->info("[✓] Logged out via API and local session for $host cleared.");
            return 0;
        }

        $this->warn("API logout responded with status $status. Local session was cleared.");
        return 0;
    }

    // Helper to get all saved hosts (we list aliases for clarity)

    /**
     * @throws SodiumException
     * @throws JsonException
     */
    protected function getSavedHosts(): array
    {
        $sessions = CliSessionManager::loadSessions();
        return array_keys($sessions['hosts']);
    }
}
```

---
#### 7


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
#### 8


` File: src/Console/Commands/PackPlugin.php`  [↑ Back to top](#index)

```php
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
use Symfony\Component\Process\Process;
use Throwable;
use Timeax\FortiPlugin\Services\PolicyService;
use Timeax\FortiPlugin\Services\ValidatorService;
use Timeax\FortiPlugin\Support\CliSessionManager;
use Timeax\FortiPlugin\Traits\AuthenticateSession;
use ZipArchive;

class PackPlugin extends Command
{
    use AuthenticateSession, Shared;

    protected $signature = 'fp:pack
        {name : Plugin directory name, e.g., OrdersPlugin}
        {--output= : Output path for zip}
        {--force : Overwrite if zip exists}
        {--silent : Suppress validation progress output}
        {--ignore-verbose : Alias to mute validation emissions}';

    protected $description = 'Validate and pack a plugin for upload to the host.';

    /**
     * Main entry.
     */
    public function handle(): int
    {
        // Session / API
        $session = $this->auth();
        if (!$session) return self::FAILURE;

        $client = $this->getHttp();
        if (!$client) {
            $this->error('Could not create API client from your session.');
            return self::FAILURE;
        }

        $name = $this->argument('name');
        $root = ($client->get("forti/structure"))['directory'] ?? 'Plugins';

        $plugin = base_path("$root/$name");
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

            $pluginSlug = $publish['plugin_slug'];
            $pluginKey = $publish['plugin_key'];

            // Optional: read placeholder token created by `forti:make`
            $this->readPlaceholderToken($plugin);

            // 2) Pack handshake — fetch exclude rules, validator config, limits, encryption nonce
            $hs = $client->post('/forti/pack/handshake');
            $handshake = $this->safeJson($hs);
            if (!($handshake['ok'] ?? false)) {
                $this->fail('Handshake failed.');
            }
            $policyVersion = (string)($handshake['policy_version'] ?? '1');
            $excludeFromHost = (array)($handshake['exclude'] ?? []);
            $validatorConfig = (array)($handshake['validator_config'] ?? []);
            $encryptionNonce = (string)($handshake['encryption']['nonce'] ?? '');

            // 3) Build assets first (if any)
            $this->assertOutDirUnchanged($tempPath);
            $this->runNpmBuild($tempPath);

            // 4) Collect files honoring both local and host excludes
            $excludeList = $excludeFromHost; // server-provided extra excludes
            $files = $this->collectPluginFiles($tempPath, $excludeList);

            // 5) Determine version and allow bump if desired (optional, local only)
            $cfgPath = $tempPath . '/fortiplugin.json';
            $cfg = file_exists($cfgPath)
                ? json_decode((string)file_get_contents($cfgPath), true, 512, JSON_THROW_ON_ERROR)
                : [];
            $localVersion = (string)($cfg['version'] ?? '0.1.0');

            // 6) Generate manifest (files: path, sha256, size)
            $filesManifest = [];
            $root = $tempPath;
            foreach ($files as $abs) {
                $rel = ltrim(str_replace($root, '', $abs), '/\\');
                $filesManifest[] = [
                    'path' => $rel,
                    'sha256' => hash_file('sha256', $abs),
                    'size' => filesize($abs),
                ];
            }

            $manifest = [
                'plugin' => [
                    'slug' => $pluginSlug,
                    'key' => $pluginKey,
                    'version' => $localVersion,
                    'policy_version' => $policyVersion,
                ],
                'files' => $filesManifest,
                'created_at' => now()->toIso8601String(),
            ];

            // 7) Local validation (no server-side validation). Can be muted by --silent or --ignore-verbose
            /** @var PolicyService $policySvc */
            $policySvc = app(PolicyService::class);
            $policy = $policySvc->makePolicy();
            $validator = new ValidatorService($policy, $validatorConfig);
            $emit = ($this->option('silent') || $this->option('ignore-verbose')) ? null : $this->makeEmitCallback();
            $summary = $validator->run($tempPath, $emit);
            if ($summary['should_fail'] ?? false) {
                $this->warn('Validation indicates failure according to fail_policy. Aborting pack.');
                $this->deleteDirectory($tempPath);
                return self::FAILURE;
            }

            // 8) Ask server to sign manifest and issue upload token
            $manifestResp = $client->post('/forti/pack/manifest', [
                'placeholder' => $pluginSlug,
                'plugin_key' => $pluginKey,
                'nonce' => $encryptionNonce,
                'manifest' => $manifest,
            ]);
            $manifestAck = $this->safeJson($manifestResp);
            if (!($manifestAck['ok'] ?? false)) {
                $this->deleteDirectory($tempPath);
                $this->fail('Manifest signing failed.');
            }

            // 9) Persist manifest locally for distribution
            $manifestWithSig = $manifest;
            $manifestWithSig['signature'] = $manifestAck['signature'] ?? null;
            $manifestPath = $tempPath . '/.internal/manifest.json';
            if (!is_dir(dirname($manifestPath)) && !mkdir(dirname($manifestPath), 0755, true) && !is_dir(dirname($manifestPath))) {
                throw new RuntimeException('Unable to create .internal directory.');
            }
            file_put_contents($manifestPath, json_encode($manifestWithSig, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // 10) Zip working directory (raw zip; transport encryption handled by server expectation)
            $zipPath = $this->option('output') ?: base_path("Plugins/$name-" . date('Ymd-His') . ".zip");
            if (file_exists($zipPath) && !$this->option('force')) {
                throw new RuntimeException("Zip already exists: $zipPath (use --force to overwrite)");
            }
            $this->makeZipFromFiles($files, $manifestPath, $zipPath);

            // 11) Upload encrypted ZIP (client provides as enc_zip per contract; here we send raw zip under enc_zip)
            $uReq = $this->getHttp();
            $response = $uReq?->attach(
                'enc_zip',
                fopen($zipPath, 'rb'),
                basename($zipPath)
            )->post('/forti/pack/upload', [
                'token' => $manifestAck['upload']['token'] ?? null,
                'placeholder' => $pluginSlug,
                'plugin_key' => $pluginKey,
            ]);
            $upload = $this->safeJson($response);
            if (!($upload['ok'] ?? false)) {
                throw new RuntimeException('Upload failed: ' . ($upload['error'] ?? 'Unknown'));
            }

            // 12) Finalize
            $final = $client->post('/forti/pack/complete', [
                'receipt_id' => $upload['receipt_id'] ?? null,
                'action' => 'auto',
            ]);
            $complete = $this->safeJson($final);
            if (!($complete['ok'] ?? false)) {
                throw new RuntimeException('Finalize failed: ' . ($complete['error'] ?? 'Unknown'));
            }

            // 13) Cleanup temp
            $this->deleteDirectory($tempPath);

            $this->info("✅ Plugin packed and submitted ({$complete['status']}).");
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
        return $this->initializeShared();
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
```

---
#### 9


` File: src/Console/Commands/Shared.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Console\Commands;

use Closure;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

trait Shared
{
    protected function initializeShared(): Closure
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
}
```

---
#### 10


` File: src/Console/Commands/ValidatePlugin.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Console\Commands;

use Closure;
use Illuminate\Console\Command;
use Throwable;
use Timeax\FortiPlugin\Services\PolicyService;
use Timeax\FortiPlugin\Services\ValidatorService;
use Timeax\FortiPlugin\Traits\AuthenticateSession;

class ValidatePlugin extends Command
{
    use AuthenticateSession, Shared;

    protected $signature = 'fp:validate
        {name : Plugin directory name, e.g., OrdersPlugin}
        {--host-config : Fetch validator config from the connected host}
        {--silent : Suppress validation progress output}';

    protected $description = 'Run plugin validation only (no packaging).';

    public function handle(): int
    {
        try {
            $name = (string)$this->argument('name');
            $pluginPath = $this->getPath($name);
            if (!is_dir($pluginPath)) {
                $this->error("Plugin not found: $pluginPath");
                return self::FAILURE;
            }

            $validatorConfig = [];

            if ($this->option('host-config')) {
                // Ensure session and retrieve validator_config via pack/handshake
                $session = $this->auth();
                if (!$session) return self::FAILURE;

                $resp = $this->getHttp()?->post('/forti/pack/handshake');
                $handshake = $this->safeJson($resp);
                if (!($handshake['ok'] ?? false)) {
                    $this->error('Failed to retrieve host validator configuration.');
                    return self::FAILURE;
                }
                $validatorConfig = (array)($handshake['validator_config'] ?? []);
            }

            /** @var PolicyService $policySvc */
            $policySvc = app(PolicyService::class);
            $policy = $policySvc->makePolicy();

            $validator = new ValidatorService($policy, $validatorConfig);
            $emit = $this->option('silent') ? null : $this->makeEmitCallback();
            $summary = $validator->run($pluginPath, $emit);

            // Final output
            $issues = (int)($summary['total_issues'] ?? 0);
            $files = (int)($summary['files_scanned'] ?? 0);
            $shouldFail = (bool)($summary['should_fail'] ?? false);

            if (!$this->option('silent')) {
                $this->line("");
                $this->info("Validation finished. Files scanned: $files, Issues: $issues");
                if ($shouldFail) {
                    $this->warn('Fail policy triggered by validation results.');
                } else {
                    $this->info('Validation passed according to current fail policy.');
                }
            }

            return $shouldFail ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    protected function makeEmitCallback(): Closure
    {
        return $this->initializeShared();
    }

    /**
     * Tiny wrapper to safely decode Guzzle responses.
     */
    protected function safeJson($response): array
    {
        try {
            $code = $response?->getStatusCode();
            $body = (string)$response?->getBody();
            if (!$code || $code < 200 || $code >= 300) {
                return ['ok' => false, 'error' => $body ?: 'Request failed'];
            }
            return json_decode($body, true, 512, JSON_THROW_ON_ERROR) ?? [];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
```

---
#### 11


` File: src/Contracts/ConfigInterface.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection ALL */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Contracts;

use Timeax\FortiPlugin\Enums\PermissionType;
use Timeax\FortiPlugin\Permissions\Evaluation\Dto\PermissionListResult;

/**
 * Contract each plugin’s internal Config class must implement.
 *
 * This interface exposes:
 * - Static accessors for the plugin’s declared config/metadata.
 * - Read-only views of the plugin’s granted permissions (both required & extra).
 * - Two complementary permission helpers:
 *    • hasPermission(...) → boolean “can I do X on Y?”
 *    • getPermission(...) → returns the matched permission definition (or null)
 *
 * Implementations are typically thin facades over host services (e.g. the
 * PermissionService facade) and the plugin’s own config/manifest payload.
 */
interface ConfigInterface
{
    /**
     * Return the entire parsed plugin config.
     *
     * The array SHOULD include top-level keys you expose in your plugin’s
     * config/manifest (e.g. "name", "alias", "version", "ui", "host", ...).
     *
     * @return array<string, mixed>
     */
    public static function all(): array;

    /**
     * Get a config key or a default value when missing.
     *
     * Nested keys MAY be host-defined (e.g., "ui.theme"), but plain keys are recommended.
     *
     * @param string $key Config key to read.
     * @param mixed|null $default Fallback if the key is not present.
     * @return mixed               The stored value or $default.
     */
    public static function get(string $key, mixed $default = null): mixed;

    /**
     * Declared plugin name (from config/manifest).
     *
     * @return string Non-empty human-friendly name.
     */
    public static function getName(): string;

    /**
     * Declared plugin alias, used by the host to resolve runtime behaviors.
     *
     * @return string Non-empty machine-friendly slug/alias.
     */
    public static function getAlias(): string;

    /**
     * Declared plugin version (semantic version string recommended).
     *
     * @return string e.g. "1.2.3"
     */
    public static function getVersion(): string;

    /**
     * Optional plugin description.
     *
     * @return string|null A short human-readable summary, or null when not set.
     */
    public static function getDescription(): ?string;

    /**
     * Optional author display name (as declared in config/manifest).
     *
     * @return string|null
     */
    public static function getAuthor(): ?string;

    /**
     * UI config block (if defined by the plugin).
     *
     * Example shape (host-defined):
     * [
     *   'theme' => 'dark',
     *   'features' => ['dashboard' => true, 'betaFlag' => false],
     * ]
     *
     * @return array<string,mixed>|null
     */
    public static function getUiConfig(): ?array;

    /**
     * Host-facing (API) config block (if defined by the plugin).
     *
     * Example shape (host-defined):
     * [
     *   'webhooks' => ['onInstall' => '...'],
     *   'endpoints' => ['health' => '/internal/health'],
     * ]
     *
     * @return array<string,mixed>|null
     */
    public static function getHostConfig(): ?array;

    /**
     * Runtime install info for the plugin, set by the host at install time.
     *
     * Keys are host-defined but MUST include:
     *  - id:    The installed plugin's primary key (or null before install).
     *  - alias: The installed alias (may be present before id exists).
     *  - name:  The installed name (for convenience).
     *
     * @return array{id:int|null, alias:string|null, name:string}
     */
    public static function getInfo(): array;

    /**
     * Convenience accessor for the installed plugin’s database id.
     *
     * @return int|null The plugin id or null if not installed yet.
     */
    public static function getPluginId(): ?int;

    /**
     * Convenience accessor for the installed plugin’s alias.
     *
     * @return string|null The alias or null if not known yet.
     */
    public static function getInstalledAlias(): ?string;

    /**
     * Combined permission view for this plugin (required + extra).
     *
     * This returns the same DTO produced by PermissionService::listPermissions(),
     * which includes:
     *  - the flattened list of concrete permissions,
     *  - per-permission “required” flag and source (direct/tag),
     *  - summary counters (totals, required satisfied/pending, etc.).
     *
     * @return PermissionListResult
     * @see PermissionListResult::class for exact shape & accessors.
     *
     */
    public static function getPermissions(): PermissionListResult;

    /**
     * Check whether specific permissions are granted for a plugin asset.
     *
     * @param PermissionType|string $type Permission family: db|file|notification|module|network|codec
     * @param string $actionOrIntent
     *        Action/intent to verify:
     *        - db: select|insert|update|delete|truncate|grouped_queries
     *        - file: read|write|append|delete|mkdir|rmdir|list
     *        - notification: send|receive
     *        - module: call
     *        - network: request
     *        - codec: invoke
     * @param string|array|null $meta Type-specific selector describing the target:
     *        - db:      ['model'=>'User'] or ['table'=>'users','columns'=>['id','name']]
     *        - file:    ['baseDir'=>'/var/data','path'=>'reports/2024.csv']
     *        - notification: ['channel'=>'email','template'=>'welcome','recipient'=>'x@y']
     *        - module:  ['module'=>'analytics','api'=>'track']
     *        - network: ['method'=>'GET','url'=>'https://api.example.com/v1/...']
     *        - codec:   ['method'=>'json_encode','options'=>[...]]
     * @param array $context Optional runtime hints (e.g., ['guard'=>'api','env'=>'staging']).
     * @return bool True if the action/intent is allowed for the given selector.
     */
    public static function hasPermission(
        PermissionType|string $type,
        string                $actionOrIntent,
        string|array|null     $meta = null,
        array                 $context = []
    ): bool;

    /**
     * Fetch the granted permission definition that matches the given selector.
     *
     * Returns a host-defined associative array describing the concrete,
     * currently effective permission for the provided selector, or null if
     * no matching permission is found.
     *
     * Suggested keys (host may include more):
     *  [
     *    'type'     => 'network',              // one of db|file|notification|module|network|codec
     *    'meta'     => array<string,mixed>,    // normalized target selector (see hasPermission meta)
     *    'grants'   => string[],               // actions currently allowed (e.g., ['request'])
     *    'required' => bool,                   // came from manifest.required_permissions
     *    'source'   => 'direct'|'tag',         // optional provenance
     *  ]
     *
     * Examples:
     *  getPermission('module', ['module'=>'analytics','api'=>'track'])
     *  getPermission(PermissionType::db, ['table'=>'users','columns'=>['id']])
     *
     * @param PermissionType|string $type Permission family: db|file|notification|module|network|codec
     * @param string|array|null $meta Type-specific selector (same shapes accepted as in hasPermission)
     * @return array<string,mixed>|null    Matched definition or null when no match.
     */
    public static function getPermission(
        PermissionType|string $type,
        string|array|null     $meta = null
    ): ?array;

    /**
     * Read the raw content of .internal/Signed if present.
     *
     * This can be used for host verification or debugging. Implementations
     * SHOULD avoid expensive I/O by caching or delegating to the host.
     *
     * @return string|null The signature payload or null if the file is not present.
     */
    public static function getSignature(): ?string;

    /**
     * Convenience helper: whether ALL manifest “required_permissions” are satisfied.
     *
     * Implementations typically delegate to PermissionService::listPermissions()
     * and return summary.requiredPending === 0.
     *
     * @return bool True if there are no outstanding required permissions.
     */
    public static function hasRequiredPermissions(): bool;

    /**
     * Get a persisted host setting for this plugin (runtime, database-backed).
     *
     * Values are read from the `plugin_settings` table (unique per plugin_id + key) and
     * decoded according to `PluginSettingValueType`:
     *
     *  - string  → returned as string (exact)
     *  - number  → cast to int if it fits, otherwise float
     *  - boolean → cast to bool
     *  - json    → `json_decode`(value, true) as associative array (on decode error, returns $default)
     *  - file    → string path/identifier (host-defined semantics)
     *  - blob    → raw string/binary payload
     *
     * Behavior:
     *  - If no row exists for ($pluginId, $key), return $default.
     *  - If type is `json` and decoding fails, return $default.
     *  - Implementations SHOULD use the installed plugin id from `getPluginId()` and must never
     *    throw for “missing id”; instead, return $default when id is not available yet.
     *
     * Examples:
     *  - getHost('webhook.secret')                 // "s3cr3t"
     *  - getHost('flags.enable_payments', false)   // true|false
     *  - getHost('limits', ['maxRequests'=>100])   // ['maxRequests'=>500] (decoded from JSON)
     *
     * NOTE: This is distinct from {@see getHostConfig()} which returns the plugin-declared
     * static config block. `getHost()` reads the host-managed, mutable settings persisted
     * in the database.
     *
     * @param string $key Setting key (exact match against `plugin_settings.key`).
     * @param mixed|null $default Fallback value when not set/decoding fails.
     * @return mixed                The decoded value or $default.
     */
    public static function getHost(string $key, mixed $default = null): mixed;

    /**
     * Return all persisted host settings for this plugin as a key => decodedValue map.
     *
     * Suggested behavior:
     *  - Decodes each row by `type` (same rules as getHost()).
     *  - On invalid JSON rows, silently skips the row (or returns `null`) depending on your preference.
     *
     * @return array<string,mixed>
     */
    public static function getAllHost(): array;

    /**
     * Resolve and (optionally) autoload the class for the plugin’s main entry or a named export.
     *
     * Behavior:
     *  - When $export is null, resolve the plugin’s “main” entry and return its class-string.
     *  - When $export is a slug/key, resolve it from the config “exports” map and return its class-string.
     *  - If the target cannot be resolved or autoloaded, return null (implementations MUST NOT throw).
     *
     * Expectations for implementations:
     *  - Read from the plugin config’s `main` and `exports` definitions (PHP files) and map them to an FQCN
     *    using PSR-4/project autoloading conventions.
     *  - Attempt to make the class autoloadable (e.g., rely on Composer autoload or require the file) before returning.
     *  - If multiple classes are present in a file, use host conventions to pick the intended one.
     *
     * Examples:
     *  - Config::load()                         // → "Vendor\\Plugin\\MainEntry" | null
     *  - Config::load('dashboard-widget')       // → "Vendor\\Plugin\\Exports\\DashboardWidget" | null
     *
     * @param string|null $export Export slug/key from `exports` (null selects `main`).
     * @return class-string|null   Fully-qualified class name if resolved, or null when not found.
     */
    public static function load(?string $export = null): ?string;
}
```

---
#### 12


` File: src/Core/ChecksModulePermission.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Core;

 use Timeax\FortiPlugin\Contracts\ConfigInterface;
use Timeax\FortiPlugin\Models\PluginAuditLog;
use Timeax\FortiPlugin\Support\PluginContext;
use Timeax\FortiPlugin\Exceptions\PermissionDeniedException;
use Timeax\FortiPlugin\Exceptions\PluginContextException;
use Illuminate\Http\Request;

/**
 * Trait ChecksModulePermission
 *
 * Provides unified permission checking for plugin modules.
 * Requires $type and $target to be defined in using class.
 */
trait ChecksModulePermission
{
    /**
     * Cached config class FQCN for this module instance.
     * @var class-string|null
     */
    protected ?string $cachedConfigClass = null;

    /**
     * Checks if the plugin has permission for the current operation.
     *
     * @param string|string[]|null $permissions
     * @param string|null $type Override the module type (optional)
     * @param string|null $target Override the target (optional)
     * @param Request|null $request The original request (for exception context, optional)
     * @return void
     * @throws PermissionDeniedException|PluginContextException
     * @noinspection LaravelEloquentGuardedAttributeAssignmentInspection
     */
    protected function checkModulePermission(
        string|array|null $permissions = null,
        ?string           $type = null,
        ?string           $target = null,
        ?Request          $request = null
    ): void
    {
        $type = $type ?? ($this->type ?? null);
        $target = $target ?? ($this->target ?? null);

        if (!$type || !$target) {
            throw new PluginContextException("Module permission properties \$type and \$target must be set in the module class.");
        }

        // --- CACHE THE CONFIG CLASS PER INSTANCE ---
        $configClass = $this->getPluginConfigClass();

        $info = method_exists($configClass, 'getInfo') ? $configClass::getInfo() : [];
        $pluginName = $info['name'] ?? (method_exists($configClass, 'getName') ? $configClass::getName() : 'unknown_plugin');
        $pluginId = method_exists($configClass, 'getPluginId') ? $configClass::getPluginId() : null;
        $userId = auth()->id();

        // --- CHECK PERMISSION ---
        $allowed = $configClass::getPermission($type, $target, $permissions);

        // --- AUDIT LOGGING ---
        $context = [
            'permissions' => $permissions,
            'class' => static::class,
            'method' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? null,
            'request' => $request ? [
                'method' => $request->method(),
                'uri' => $request->getRequestUri(),
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
                'params' => $request->all(),
            ] : null,
        ];

        PluginAuditLog::create([
            'plugin_id' => $pluginId,
            'user_id' => $userId,
            'type' => $type,
            'action' => is_array($permissions) ? implode(',', $permissions) : ($permissions ?? 'access'),
            'resource' => $target,
            'context' => array_merge($context, [
                'granted' => $allowed,
                'plugin' => $pluginName,
            ]),
        ]);

        if (!$allowed) {
            throw new PermissionDeniedException(
                $type,
                $target,
                $permissions,
                $request
            );
        }
    }

    /**
     * Immediately deny permission for the given parameters.
     *
     * @param string $message
     * @param string|null $target
     * @param string|array|null $permissions
     * @param string|null $type
     * @return void
     * @throws PermissionDeniedException
     */
    protected function denyPermission(
        string            $message,
        string|null       $target,
        string|array|null $permissions,
        ?string           $type = null
    ): void
    {
        $type = $type ?? ($this->type ?? 'module');
        throw new PermissionDeniedException(
            $type,
            $target ?? $this->target,
            $permissions,
            request(),
            $message
        );
    }

    /**
     * @return class-string<ConfigInterface>
     */
    public function getPluginConfigClass(): string
    {
        if ($this->cachedConfigClass === null) {
            $configClass = PluginContext::getCurrentConfigClass();
            if (!$configClass || !method_exists($configClass, 'getPermission')) {
                throw new PluginContextException("Unable to resolve plugin config for permission checks.");
            }
            $this->cachedConfigClass = $configClass;
        }

        return $this->cachedConfigClass;
    }
}
```

---
#### 13


` File: src/Core/Exceptions/DuplicateSettingIdException.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Core\Exceptions;

use RuntimeException;

final class DuplicateSettingIdException extends RuntimeException
{
    public function __construct(string|int|float $id, string $where)
    {
        parent::__construct("Duplicate setting id '{$id}' detected {$where}.");
    }
}
```

---
#### 14


` File: src/Core/Exceptions/HostConfigException.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Core\Exceptions;

use RuntimeException;

class HostConfigException extends RuntimeException
{
}
```

---
#### 15


` File: src/Core/PluginPolicy.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection SpellCheckingInspection */
/** @noinspection PhpUnused */
/** @noinspection ClassConstantCanBeUsedInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Core;

/**
 * PluginPolicy
 *
 * Effective scanning policy for FortiPlugin:
 *   1) Start from Forti defaults (this class).
 *   2) Add host overlays from config (validator.*).
 *   3) Apply host "overrides" to ALLOW specific items otherwise blocked.
 *
 * Notes
 * - "Forbidden" => hard block (must not be used).
 * - "Unsupported" => flagged/risky (can be treated as warnings by scanner).
 * - Overrides are *surgical* ALLOWs. Prefer granting permissions via review.
 */
class PluginPolicy
{
    /* ---------------------------------------------------------------------
     |  Forti defaults (base deny lists)
     |---------------------------------------------------------------------*/

    /** @var array<int,string> */
    protected array $fileIoMethods = [
        // File & Directory Read
        'fopen', 'fread', 'file_get_contents', 'file', 'fgets', 'fgetc', 'fgetcsv',
        'readfile', 'stream_get_contents', 'stream_get_line', 'file_exists',
        'is_readable', 'stat', 'lstat', 'scandir', 'opendir', 'readdir',
        'parse_ini_file', 'parse_ini_string', 'glob', 'realpath',

        // File & Directory Write
        'fwrite', 'file_put_contents', 'fputcsv', 'fflush', 'ftruncate', 'flock',
        'rename', 'touch', 'chmod', 'chown', 'chgrp', 'move_uploaded_file',
        'stream_set_write_buffer', 'tempnam', 'tmpfile', 'mkdir', 'rmdir',

        // Copy/Move/Delete
        'copy', 'unlink', 'symlink', 'link',
    ];

    /** @var array<int,string> */
    protected array $streamFunctions = [
        'stream_context_create', 'stream_context_set_option', 'stream_context_get_options',
        'stream_context_set_params', 'stream_copy_to_stream', 'stream_filter_append',
        'stream_filter_prepend', 'stream_filter_remove', 'stream_get_contents',
        'stream_get_line', 'stream_get_meta_data', 'stream_get_transports',
        'stream_get_wrappers', 'stream_is_local', 'stream_register_wrapper',
        'stream_resolve_include_path', 'stream_select', 'stream_set_blocking',
        'stream_set_chunk_size', 'stream_set_read_buffer', 'stream_set_timeout',
        'stream_socket_accept', 'stream_socket_client', 'stream_socket_enable_crypto',
        'stream_socket_get_name', 'stream_socket_pair', 'stream_socket_recvfrom',
        'stream_socket_sendto', 'stream_socket_server', 'stream_wrapper_register',
        'stream_wrapper_restore', 'stream_wrapper_unregister',
    ];

    /** @var array<int,string> */
    protected array $curlMethods = [
        'curl_close', 'curl_copy_handle', 'curl_errno', 'curl_error', 'curl_escape', 'curl_exec', 'curl_getinfo', 'curl_init',
        'curl_multi_add_handle', 'curl_multi_close', 'curl_multi_errno', 'curl_multi_exec', 'curl_multi_getcontent',
        'curl_multi_info_read', 'curl_multi_init', 'curl_multi_remove_handle', 'curl_multi_select', 'curl_multi_setopt',
        'curl_multi_strerror', 'curl_pause', 'curl_reset', 'curl_setopt', 'curl_setopt_array', 'curl_share_close',
        'curl_share_errno', 'curl_share_init', 'curl_share_init_persistent', 'curl_share_setopt', 'curl_share_strerror',
        'curl_unescape', 'curl_upkeep', 'curl_version',
    ];

    /** @var array<int,string> */
    protected array $forbiddenNamespaceList = [
        'Illuminate\\Routing\\',           // Route
        'Illuminate\\Filesystem\\',        // File
        'Illuminate\\Support\\Facades\\File',
        'Illuminate\\Support\\Facades\\Storage',
        'Illuminate\\Contracts\\Filesystem\\',
        'Illuminate\\Http\\UploadedFile',
        'Symfony\\Component\\HttpFoundation\\File\\', // incl. FileBag etc.
        'Illuminate\\Support\\Facades\\Route',
        'Illuminate\\Support\\Facades\\Artisan',      // Command execution
        'Illuminate\\Support\\Facades\\Schema',       // Schema mutations
        'Illuminate\\Support\\Facades\\DB',           // DB facade directly
        'Illuminate\\Database\\',                     // Direct DB access
    ];

    /** @var array{
     *    functions:array<int,string>,
     *    reflectionPrefix:string,
     *    magicMethods:array<int,string>,
     *    wrappers:array<int,string>
     * }
     */
    protected array $alwaysForbidden = [
        'functions' => [
            'eval', 'assert', 'exec', 'shell_exec', 'passthru', 'system',
            'proc_open', 'popen', 'dl', 'create_function', 'unserialize',
            'register_shutdown_function', 'set_error_handler', 'set_exception_handler', 'register_tick_function',
            'putenv', 'ini_set', 'ini_restore',
        ],
        'reflectionPrefix' => 'Reflection',
        'magicMethods' => ['__call', '__callStatic', '__invoke', '__autoload'],
        'wrappers' => ['php://', 'data://', 'glob://', 'zip://', 'phar://'],
    ];

    /** @var array<int,string> */
    protected array $callbackFunctions = [
        'array_map', 'array_filter', 'array_walk', 'array_walk_recursive', 'usort', 'uasort', 'uksort', 'array_reduce',
        'register_shutdown_function', 'set_error_handler', 'set_exception_handler', 'register_tick_function',
    ];

    /** @var array<int,string> */
    protected array $envManipulationFunctions = [
        // Environment
        'putenv', 'getenv', 'apache_setenv', 'apache_getenv',
        // INI
        'ini_set', 'ini_alter', 'ini_restore', 'ini_get', 'ini_get_all', 'ini_parse_quantity',
        // Process / system
        'proc_open', 'proc_close', 'proc_terminate', 'proc_get_status', 'proc_nice',
        // CLI/Server process manipulation
        'pcntl_exec', 'pcntl_fork', 'pcntl_wait', 'pcntl_waitpid', 'pcntl_signal', 'pcntl_alarm',
        'pcntl_wexitstatus', 'pcntl_wifexited', 'pcntl_wifsignaled', 'pcntl_wifstopped',
        'pcntl_signal_dispatch', 'pcntl_get_last_error', 'pcntl_errno',
        // Limits / shutdown
        'set_time_limit', 'ignore_user_abort', 'fastcgi_finish_request',
    ];

    /** @var array<int,string> */
    protected array $diContainerMethods = [
        // Laravel/Illuminate
        'bind', 'singleton', 'instance', 'scoped', 'share', 'extend', 'when', 'tag', 'alias',
        'resolving', 'afterResolving', 'make',
        // Symfony/PSR
        'register', 'set', 'addArgument', 'addMethodCall', 'setShared', 'addTag',
        // Pimple / Interop
        'offsetSet', 'offsetGet', 'addService', 'addProvider', 'delegate', 'factory',
        // Zend / others
        'configure', 'define', 'protect',
        // CakePHP
        'load', 'unload',
        // Custom markers
        'service', 'handler', 'controller',
    ];

    /** @var array<int,string> */
    protected array $obfuscators = [
        // Encoders/decoders
        'base64_decode', 'base64_encode', 'gzinflate', 'gzdeflate', 'gzencode', 'gzdecode', 'gzcompress', 'gzuncompress',
        'str_rot13', 'rot13', 'bin2hex', 'hex2bin', 'chr', 'ord', 'pack', 'unpack',
        'urlencode', 'urldecode', 'rawurlencode', 'rawurldecode', 'convert_uuencode', 'convert_uudecode',
        'json_encode', 'json_decode', 'serialize', 'unserialize',
        // Misc
        'strrev', 'md5', 'sha1', 'sha256', 'hash', 'hash_hmac', 'openssl_encrypt', 'openssl_decrypt',
        'mcrypt_encrypt', 'mcrypt_decrypt', // legacy
        // Compression/encoding helpers
        'bzcompress', 'bzdecompress', 'zlib_encode', 'zlib_decode', 'deflate_add', 'inflate_add', 'inflate_init', 'deflate_init',
        // Transformations often chained
        'addslashes', 'stripslashes', 'quotemeta', 'strip_tags',
    ];

    /* ---------------------------------------------------------------------
     |  Host overlay & overrides (config-driven)
     |---------------------------------------------------------------------*/

    /** Raw host config (as passed in) */
    protected array $config = [];

    /** Additive risk sets from host (stricter) */
    protected array $unsupportedFunctions = []; // tokens + dangerous + env + obfuscators
    protected array $forbiddenNamespaces = []; // base + host
    protected array $forbiddenPackages = []; // host

    /**
     * Class method allowlist:
     * If a class is present, ONLY the listed methods are allowed; all others are blocked.
     * Merged with host 'blocklist' and then expanded via overrides['classes'].
     */
    protected mixed $blocklist;

    /** Overrides that ALLOW specific items otherwise blocked */
    protected array $overrides = [
        'functions' => [],
        'tokens' => [],
        'dangerous' => [],
        'namespaces' => [],
        'packages' => [],
        'wrappers' => [],
        'magic_methods' => [],
        'classes' => [], // ['ClassName' => ['method1','method2']]
    ];

    // Fast lookup sets for overrides
    protected array $allowFunctionSet = [];
    protected array $allowTokenSet = [];
    protected array $allowDangerSet = [];

    /* ---------------------------------------------------------------------
     |  Construction / normalization
     |---------------------------------------------------------------------*/

    public function __construct(array $config = [])
    {
        // Include stream functions as part of file I/O for stricter default posture
        $this->fileIoMethods = array_values(array_unique(array_merge($this->fileIoMethods, $this->streamFunctions)));

        // Store config reference
        $this->config = $config;

        // Compute "unsupported" = tokens (host) + dangerous (host) + env + obfuscators
        $this->unsupportedFunctions = array_values(array_unique(array_merge(
            $config['dangerous_functions'] ?? [],
            $config['tokens'] ?? [],
            $this->envManipulationFunctions,
            $this->obfuscators
        )));

        // Forbidden namespaces/packages (stricter by host)
        $this->forbiddenNamespaces = array_values(array_unique(array_merge(
            $config['forbidden_namespaces'] ?? [],
            $this->forbiddenNamespaceList
        )));
        $this->forbiddenPackages = array_values(array_unique($config['forbidden_packages'] ?? []));

        // Method allowlist per class (host can define)
        $this->blocklist = $config['allowed_class_methods'] ?? [];

        // Overrides (ALLOWS)
        $this->overrides = array_replace_recursive($this->overrides, $config['overrides'] ?? []);

        // Create lowercase lookup sets for function-name comparisons
        $fn = array_map('strtolower', $this->overrides['functions'] ?? []);
        $tokens = array_map('strtolower', $this->overrides['tokens'] ?? []);
        $danger = array_map('strtolower', $this->overrides['dangerous'] ?? []);

        $this->allowFunctionSet = array_fill_keys($fn, true);
        $this->allowTokenSet = array_fill_keys($tokens, true);
        $this->allowDangerSet = array_fill_keys($danger, true);

        // Subtract overrides from forbidden namespaces/packages
        if (!empty($this->overrides['namespaces'])) {
            $this->forbiddenNamespaces = array_values(array_diff(
                $this->forbiddenNamespaces,
                $this->overrides['namespaces']
            ));
        }
        if (!empty($this->overrides['packages'])) {
            $lowerForbidden = array_map('strtolower', $this->forbiddenPackages);
            $lowerAllowed = array_map('strtolower', $this->overrides['packages']);
            $this->forbiddenPackages = array_values(array_diff($lowerForbidden, $lowerAllowed));
        }

        // Expand class method allowlist using overrides['classes'] (adds allowed methods)
        foreach (($this->overrides['classes'] ?? []) as $class => $methods) {
            $methods = array_values(array_unique($methods));
            if (!isset($this->blocklist[$class])) {
                $this->blocklist[$class] = [];
            }
            $this->blocklist[$class] = array_values(array_unique(array_merge($this->blocklist[$class], $methods)));
        }
    }

    /* ---------------------------------------------------------------------
     |  Checks — Forbidden
     |---------------------------------------------------------------------*/

    public function isForbiddenNamespace(string $namespace): bool
    {
        foreach ($this->forbiddenNamespaces as $forbidden) {
            if (stripos($namespace, $forbidden) === 0) {
                return true;
            }
        }
        return false;
    }

    public function isForbiddenPackage(string $package): bool
    {
        $needle = strtolower($package);
        return in_array($needle, $this->forbiddenPackages, true);
    }

    /**
     * Forbidden functions: Forti defaults + curl + fileIO + alwaysForbidden,
     * then subtract *allowed* overrides.
     */
    public function isForbiddenFunction($name): bool
    {
        $n = strtolower((string)$name);

        // If specifically allowed, it's NOT forbidden
        if (isset($this->allowFunctionSet[$n]) || isset($this->allowTokenSet[$n]) || isset($this->allowDangerSet[$n])) {
            return false;
        }

        return in_array($n, $this->getForbiddenFunctions(), true);
    }

    /**
     * Methods blocked by class method-allowlist semantics.
     * If a class is present in blocklist, any method NOT explicitly listed is blocked.
     */
    public function isBlockedMethod($class, $method): bool
    {
        $class = $this->resolveClass((string)$class);
        if (!isset($this->blocklist[$class])) {
            // No allowlist for this class → not blocked by allowlist semantics
            return false;
        }
        return !in_array((string)$method, $this->blocklist[$class], true);
    }

    public function isForbiddenReflection($class): bool
    {
        // Null / non-string? we can't determine — treat as not-forbidden here.
        if ($class === null) {
            return false;
        }

        // Allow Stringable objects
        if (is_object($class) && method_exists($class, '__toString')) {
            $class = (string)$class;
        }

        if (!is_string($class) || $class === '') {
            return false;
        }

        // Normalize leading backslash
        $class = ltrim($class, '\\');

        // If namespace overrides explicitly ALLOW something, unblock it
        $namespaces = is_array($this->overrides['namespaces'] ?? null) ? $this->overrides['namespaces'] : [];
        foreach ($namespaces as $ns) {
            if (is_string($ns) && $ns !== '' && stripos($class, ltrim($ns, '\\')) === 0) {
                return false;
            }
        }

        // Default rule: anything starting with "Reflection"
        $prefix = $this->alwaysForbidden['reflectionPrefix'] ?? 'Reflection';
        return stripos($class, $prefix) === 0;
    }

    public function getForbiddenWrappers(): array
    {
        // Subtract overrides
        return array_values(array_diff($this->alwaysForbidden['wrappers'], $this->overrides['wrappers'] ?? []));
    }

    public function getForbiddenMagicMethods(): array
    {
        // Subtract overrides
        return array_values(array_diff($this->alwaysForbidden['magicMethods'], $this->overrides['magic_methods'] ?? []));
    }

    /**
     * Effective forbidden functions list (after subtracting allowed overrides).
     */
    public function getForbiddenFunctions(): array
    {
        $forbidden = array_map('strtolower', array_values(array_unique(array_merge(
            $this->alwaysForbidden['functions'],
            $this->fileIoMethods,
            $this->curlMethods
        ))));

        // Subtract overrides (functions/tokens/dangerous)
        $allow = array_keys($this->allowFunctionSet + $this->allowTokenSet + $this->allowDangerSet);
        if (!empty($allow)) {
            $forbidden = array_values(array_diff($forbidden, $allow));
        }

        return $forbidden;
    }

    public function getReflectionPrefix()
    {
        return $this->alwaysForbidden['reflectionPrefix'];
    }

    /* ---------------------------------------------------------------------
     |  Checks — Unsupported (warnings)
     |---------------------------------------------------------------------*/

    /**
     * Return effective unsupported set (after subtracting allowed overrides).
     */
    public function getUnsupportedFunctions(): array
    {
        $list = array_map('strtolower', $this->unsupportedFunctions);
        $allow = array_keys($this->allowFunctionSet + $this->allowTokenSet + $this->allowDangerSet);
        if (!empty($allow)) {
            $list = array_values(array_diff($list, $allow));
        }
        return $list;
    }

    public function isUnsupportedFunction($name): bool
    {
        $n = strtolower((string)$name);
        if (isset($this->allowFunctionSet[$n]) || isset($this->allowTokenSet[$n]) || isset($this->allowDangerSet[$n])) {
            return false;
        }
        return in_array($n, $this->getUnsupportedFunctions(), true);
    }

    /* ---------------------------------------------------------------------
     |  Accessors / Utilities
     |---------------------------------------------------------------------*/

    public function getFileFunctions(): array
    {
        return $this->fileIoMethods;
    }

    public function getObfuscators(): array
    {
        return $this->obfuscators;
    }

    public function getEnvMethods(): array
    {
        return $this->envManipulationFunctions;
    }

    /** Return the current (merged) class method allowlist map. */
    public function getBlocklist()
    {
        return $this->blocklist;
    }

    /** Namespaces currently considered forbidden (after overrides). */
    public function getForbiddenNamespaces(): array
    {
        return $this->forbiddenNamespaces;
    }

    /** Composer packages currently considered forbidden (after overrides). */
    public function getForbiddenPackages(): array
    {
        return $this->forbiddenPackages;
    }

    public function getCallbackFunctions(): array
    {
        return $this->callbackFunctions;
    }

    public function getStreamFunctions(): array
    {
        return $this->streamFunctions;
    }

    public function getDiContainerMethods(): array
    {
        return $this->diContainerMethods;
    }

    /** Raw config as provided to the policy */
    public function getConfig(): array
    {
        return $this->config;
    }

    public function resolveClass($class): string
    {
        // Hook for alias resolution if you track aliases; identity for now.
        return (string)$class;
    }
}
```

---
#### 16


` File: src/Core/Security/CallGraphAnalyzer.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection GrazieInspection */

namespace Timeax\FortiPlugin\Core\Security;

use PhpParser\Node;
use PhpParser\Node\Expr\Eval_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use Timeax\FortiPlugin\Core\PluginPolicy;
use Timeax\FortiPlugin\Core\Security\Concerns\ResolvesNames;

class CallGraphAnalyzer
{
    use ResolvesNames;

    protected PluginPolicy $policy;
    protected int $maxDepth;

    /** @var array<string, Node\Stmt\Function_>  function fqn (lc) => node */
    protected array $functionDefs = [];

    /** @var array<string, array<string, Node\Stmt\ClassMethod>> class fqn (lc) => [method (lc) => node] */
    protected array $methodDefs = [];

    public function __construct(PluginPolicy $policy, int $maxDepth = 7)
    {
        $this->policy = $policy;
        $this->maxDepth = $maxDepth;
    }

    /** Debug helper */
    public function getMethodDefs(?string $class = null): array
    {
        if ($class === null) return $this->methodDefs;
        $key = $this->normClass($class);
        return $this->methodDefs[$key] ?? [];
    }

    /**
     * Collect top-level function & class method definitions.
     * Run AFTER NameResolver (recommended), but works without it too.
     *
     * @param array<int,array<int,Node>> $asts
     */
    public function collect(array $asts): void
    {
        foreach ($asts as $stmts) {
            foreach ($stmts as $node) {
                if ($node instanceof Node\Stmt\Function_) {
                    $name = $this->declFuncName($node);
                    if ($name) {
                        $this->functionDefs[strtolower($name)] = $node;
                    }
                } elseif ($node instanceof Node\Stmt\Class_) {
                    $classFqn = $this->declFqcn($node);
                    if (!$classFqn) continue;
                    $classKey = strtolower($classFqn);

                    foreach ($node->getMethods() as $method) {
                        $m = strtolower($method->name->toString());
                        $this->methodDefs[$classKey][$m] = $method;
                        // Tag method with its resolved class (lc fqn) for convenience
                        $method->setAttribute('forti_class', $classKey);
                    }
                }
            }
        }
    }

    /* ==================== public queries ==================== */

    /** Does function (by name) return (directly/indirectly) a forbidden/unsupported surface? */
    public function hasForbiddenReturnChain(string $functionName, array $visited = [], int $depth = 0): bool
    {
        if ($depth > $this->maxDepth) return false;

        // Prefer fully-qualified; accept raw name too (best-effort)
        $fnKey = strtolower(ltrim($functionName, '\\'));
        if (in_array($fnKey, $visited, true)) return false;
        $visited[] = $fnKey;

        $fnNode = $this->functionDefs[$fnKey] ?? null;
        if (!$fnNode) return false;

        foreach ($fnNode->getStmts() as $stmt) {
            if (!$stmt instanceof Node\Stmt\Return_) continue;
            $expr = $stmt->expr;

            if ($this->isForbiddenReturn($expr)) {
                return true;
            }

            // return someOtherFunction(...)
            if ($expr instanceof FuncCall && $expr->name instanceof Name) {
                $called = $this->fqNameOf($expr->name);
                $ckey = $called ? strtolower($called) : null;
                if ($ckey && isset($this->functionDefs[$ckey]) &&
                    $this->hasForbiddenReturnChain($ckey, $visited, $depth + 1)) {
                    return true;
                }
            }

            // return 'exec';
            if ($expr instanceof String_) {
                $s = strtolower($expr->value);
                if ($this->policy->isForbiddenFunction($s) || $this->policy->isUnsupportedFunction($s)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Does class::method return (directly/indirectly) a forbidden/unsupported surface? */
    public function hasForbiddenMethodReturnChain(string $className, string $methodName, array $visited = [], int $depth = 0): bool
    {
        if ($depth > $this->maxDepth) return false;

        $classKey = strtolower($this->normClass($className));
        $methodKey = strtolower($methodName);
        $visitKey = $classKey . '::' . $methodKey;

        if (in_array($visitKey, $visited, true)) return false;
        $visited[] = $visitKey;

        $methNode = $this->methodDefs[$classKey][$methodKey] ?? null;
        if (!$methNode) return false;

        foreach ((array)$methNode->getStmts() as $stmt) {
            if (!$stmt instanceof Node\Stmt\Return_) continue;
            $expr = $stmt->expr;

            if ($this->isForbiddenReturn($expr)) {
                return true;
            }

            // return $this->foo();
            if ($expr instanceof MethodCall
                && $expr->var instanceof Node\Expr\Variable
                && $expr->var->name === 'this'
                && $expr->name instanceof Identifier) {

                $m2 = strtolower($expr->name->toString());
                if (isset($this->methodDefs[$classKey][$m2]) &&
                    $this->hasForbiddenMethodReturnChain($classKey, $m2, $visited, $depth + 1)) {
                    return true;
                }
            }

            // return self::foo() / static::foo() / parent::foo() / FQCN::foo()
            if ($expr instanceof StaticCall && $expr->name instanceof Identifier) {
                $targetClass = $this->resolveStaticClassRef($expr->class, $classKey);
                $m2 = strtolower($expr->name->toString());

                if ($targetClass && isset($this->methodDefs[$targetClass][$m2]) &&
                    $this->hasForbiddenMethodReturnChain($targetClass, $m2, $visited, $depth + 1)) {
                    return true;
                }
            }

            // return 'exec';
            if ($expr instanceof String_) {
                $s = strtolower($expr->value);
                if ($this->policy->isForbiddenFunction($s) || $this->policy->isUnsupportedFunction($s)) {
                    return true;
                }
            }
        }

        return false;
    }

    /* ==================== helpers ==================== */

    /**
     * Is a return-expression itself forbidden/unsupported?
     * eval/new/ClassName and function calls.
     */
    protected function isForbiddenReturn(?Node\Expr $expr): bool
    {
        if (!$expr) return false;

        if ($expr instanceof Eval_) return true;

        if ($expr instanceof New_) {
            $class = $this->fqNameOf($expr->class);
            return $class && (
                    $this->policy->isForbiddenNamespace($class) ||
                    $this->policy->isForbiddenReflection($class)
                );
        }

        if ($expr instanceof FuncCall) {
            $name = $expr->name instanceof Name ? $this->fqNameOf($expr->name) : null;
            $name = $name ? strtolower($name) : null;
            return $name && (
                    $this->policy->isForbiddenFunction($name) ||
                    $this->policy->isUnsupportedFunction($name)
                );
        }

        // (Optional) extend: closures returning forbidden expressions, etc.
        return false;
    }

    /**
     * Collect a simple left-deep call chain: f(g(h($x)))
     * Returns lowercased function names in order: ['f','g','h']
     */
    public function collectFuncCallChain($expr, int $maxDepth = 6): array
    {
        $out = [];
        $depth = 0;
        $cur = $expr;

        while ($cur instanceof FuncCall && $cur->name instanceof Name && $depth < $maxDepth) {
            $name = strtolower($this->fqNameOf($cur->name) ?? '');
            if ($name === '') break;
            $out[] = $name;
            if (empty($cur->args)) break;
            $cur = $cur->args[0]->value; // follow first arg (typical for obfuscators)
            $depth++;
        }

        return $out;
    }
}
```

---
#### 17


` File: src/Core/Security/ComposerScan.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection PhpUnused */

namespace Timeax\FortiPlugin\Core\Security;

use JsonException;
use Timeax\FortiPlugin\Core\PluginPolicy;

class ComposerScan
{
    protected PluginPolicy $policy;

    public function __construct(PluginPolicy $policy)
    {
        $this->policy = $policy;
    }

    /**
     * Scan a composer.json file for forbidden packages.
     * @param string $composerJsonPath
     * @return array List of violations.
     * @throws JsonException
     */
    public function scan(string $composerJsonPath): array
    {
        $violations = [];
        if (!is_file($composerJsonPath)) {
            return [
                [
                    'type' => 'composer_file_missing',
                    'file' => $composerJsonPath,
                    'issue' => 'composer.json not found'
                ]
            ];
        }

        $json = json_decode(file_get_contents($composerJsonPath), true, 512, JSON_THROW_ON_ERROR);
        if (!$json) {
            return [
                [
                    'type' => 'composer_file_invalid',
                    'file' => $composerJsonPath,
                    'issue' => 'Invalid JSON in composer.json'
                ]
            ];
        }

        $deps = array_merge(
            $json['require'] ?? [],
            $json['require-dev'] ?? []
        );

        foreach ($this->policy->getForbiddenPackages() as $forbidden) {
            foreach ($deps as $package => $version) {
                if (strtolower($package) === strtolower($forbidden)) {
                    $violations[] = [
                        'type' => 'forbidden_package_dependency',
                        'package' => $package,
                        'version' => $version,
                        'file' => $composerJsonPath,
                        'issue' => "Composer requires forbidden package: $package"
                    ];
                }
            }
        }

        return $violations;
    }
}
```

---
#### 18


` File: src/Core/Security/Concerns/ResolvesNames.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Core\Security\Concerns;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Identifier;

trait ResolvesNames
{
    /** Normalize: drop leading "\"; keep the case as-is (callers can strtolower). */
    protected function normClass(string $name): string
    {
        return ltrim($name, '\\');
    }

    /** Prefer NameResolver’s resolvedName/namespacedName when present. */
    protected function fqNameOf(mixed $node): ?string
    {
        if ($node instanceof Name) {
            $resolved = $node->getAttribute('resolvedName');
            if ($resolved instanceof Name) {
                return $this->normClass($resolved->toString());
            }
            // if replaceNodes=true, this is already FullyQualified
            return $this->normClass($node->toString());
        }
        if ($node instanceof Identifier) {
            return $this->normClass($node->toString());
        }
        if (is_string($node)) {
            return $this->normClass($node);
        }
        return null;
    }

    /** For class declarations (NameResolver sets ->namespacedName). */
    protected function declFqcn(Node\Stmt\Class_ $class): ?string
    {
        if (isset($class->namespacedName)) {
            return $this->normClass($class->namespacedName->toString());
        }
        return $class->name?->toString();
    }

    /** For function declarations (NameResolver sets ->namespacedName). */
    protected function declFuncName(Node\Stmt\Function_ $fn): ?string
    {
        if (isset($fn->namespacedName)) {
            return $this->normClass($fn->namespacedName->toString());
        }
        return $fn->name->toString();
    }

    /**
     * Resolve self/static/parent/FQCN to a class key.
     * Return lower-case key when you plan to use it as a map index.
     */
    protected function resolveStaticClassRef(Name|Identifier|string $classNode, string $currentClassKey): ?string
    {
        $raw = $this->fqNameOf($classNode);
        if ($raw === null) return null;

        $lc = strtolower($raw);
        if ($lc === 'self' || $lc === 'static' || $lc === 'parent') {
            return $currentClassKey;
        }
        return strtolower($raw);
    }
}
```

---
#### 19


` File: src/Core/Security/ConfigValidator.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Core\Security;

use JsonException;
use Opis\JsonSchema\Validator;

class ConfigValidator
{
    /**
     * @throws JsonException
     */
    public function validate(string $pluginRoot, string $schemaPath): array
    {
        $configFile = rtrim($pluginRoot, '/\\') . '/fortiplugin.json';
        if (!file_exists($configFile)) {
            return ['error' => 'fortiplugin.json not found'];
        }

        $json = file_get_contents($configFile);
        $data = json_decode($json, false, 512, JSON_THROW_ON_ERROR);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Invalid JSON in fortiplugin.json: ' . json_last_error_msg()];
        }

        $schema = json_decode(file_get_contents($schemaPath), false, 512, JSON_THROW_ON_ERROR); // <-- just the decoded schema object!
        $validator = new Validator();
        $error = $validator->schemaValidation($data, $schema);

        if ($error !== null) {
            $details = $this->extractErrors($error);
            return [
                'error' => 'Schema validation failed',
                'details' => $details,
            ];
        }

        return []; // Valid!
    }

    protected function extractErrors($error, $parentPointer = ''): array
    {
        if (!$error) {
            return [];
        }

        $pointer = $parentPointer . $error->data()->pointer();
        $message = $error->message();
        $keyword = $error->keyword();
        $args = $error->args();

        $result = [[
            'path' => $pointer,
            'message' => $message,
            'keyword' => $keyword,
            'args' => $args,
        ]];

        return array_reduce(
            $error->subErrors(),
            function ($carry, $sub) use ($pointer) {
                return [...$carry, ...$this->extractErrors($sub, $pointer)];
            },
            $result
        );
    }
}
```

---
#### 20


` File: src/Core/Security/ContentValidator.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection RegExpUnexpectedAnchor */

namespace Timeax\FortiPlugin\Core\Security;

use Timeax\FortiPlugin\Core\PluginPolicy;

class ContentValidator
{
    protected PluginPolicy $policy;
    protected ?string $root = null;

    public function __construct(PluginPolicy $policy)
    {
        $this->policy = $policy;
    }

    /**
     * Scan one PHP file and return violations.
     */
    public function scanFile(string $filePath): array
    {
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return [[
                'type' => 'read_error',
                'file' => $filePath,
                'line' => 0,
                'snippet' => '',
                'issue' => 'Unable to read file',
            ]];
        }

        return $this->scanSource($content, $filePath);
    }

    /**
     * Scan a raw PHP source string and return violations.
     */
    public function scanSource(string $content, string $filePath = '[source]'): array
    {
        $violations = [];
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];

        foreach ($lines as $i => $line) {
            $ln = $i + 1;

            $this->append($violations, $this->containsBlocklistTokens($line, $ln, $filePath));
            $this->append($violations, $this->containsForbiddenNamespaces($line, $ln, $filePath));
            $this->append($violations, $this->containsForbiddenFunctions($line, $ln, $filePath));
            $this->append($violations, $this->containsUnsupportedFunctions($line, $ln, $filePath));
        }

        return $violations;
    }

    /**
     * Append items to the target array without creating extra copies.
     */
    protected function append(array &$target, array $items): void
    {
        if (!$items) return;
        foreach ($items as $v) {
            $target[] = $v;
        }
    }


    /**
     * Detect use of blocklisted classes/facades and their methods.
     */
    protected function containsBlocklistTokens(string $line, int $lineNumber, string $filePath): array
    {
        $violations = [];
        $map = $this->policy->getBlocklist(); // effective allowlist after overrides

        foreach ($map as $class => $allowed) {
            $q = preg_quote($class, '/');

            if (preg_match("/new\s+$q\s*\(/", $line)) {
                $violations[] = [
                    'type' => 'blocklist_instantiation',
                    'token' => $class,
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'snippet' => trim($line),
                    'issue' => "Instantiation: new $class",
                ];
            }

            if (preg_match("/$q\s*::\s*__construct\s*\(/", $line)) {
                $violations[] = [
                    'type' => 'blocklist_constructor',
                    'token' => $class,
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'snippet' => trim($line),
                    'issue' => "Constructor: $class::__construct",
                ];
            }

            if (preg_match("/\b$q\s*::\s*class\b/", $line)) {
                $violations[] = [
                    'type' => 'blocklist_class_reference',
                    'token' => $class,
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'snippet' => trim($line),
                    'issue' => "Class reference: $class::class",
                ];
            }

            if (str_contains($line, "$class::") && !in_array('*', $allowed, true)) {
                preg_match_all("/\\b$q::([A-Za-z_][A-Za-z0-9_]*)/", $line, $m);
                foreach ($m[1] as $method) {
                    if (!in_array($method, $allowed, true)) {
                        $violations[] = [
                            'type' => 'blocklist_method',
                            'token' => $class,
                            'method' => $method,
                            'file' => $filePath,
                            'line' => $lineNumber,
                            'snippet' => trim($line),
                            'issue' => "Method: $class::$method",
                        ];
                    }
                }
            }
        }

        return $violations;
    }

    /**
     * Forbidden namespaces.
     */
    protected function containsForbiddenNamespaces(string $line, int $lineNumber, string $filePath): array
    {
        $violations = [];
        $namespaces = $this->policy->getForbiddenNamespaces();

        // use statements
        if (preg_match('/^use\s+([^;]+);/i', $line, $m)) {
            $ns = trim($m[1]);
            foreach ($namespaces as $forbidden) {
                if (stripos($ns, $forbidden) === 0) {
                    $violations[] = [
                        'type' => 'forbidden_namespace_import',
                        'namespace' => $forbidden,
                        'file' => $filePath,
                        'line' => $lineNumber,
                        'snippet' => trim($line),
                        'issue' => 'Import of forbidden namespace or child',
                    ];
                }
            }
        }

        foreach ($namespaces as $forbidden) {
            $q = preg_quote($forbidden, '/');

            // new/extends/implements/static/instanceof
            if (preg_match('/\b' . $q . '\\\\/', $line)) {
                $violations[] = [
                    'type' => 'forbidden_namespace_reference',
                    'namespace' => $forbidden,
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'snippet' => trim($line),
                    'issue' => 'Reference to forbidden namespace',
                ];
            }

            // string references
            if (preg_match('/[\'"]' . $q . '\\\\[^\'"]+[\'"]/', $line)) {
                $violations[] = [
                    'type' => 'forbidden_namespace_string',
                    'namespace' => $forbidden,
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'snippet' => trim($line),
                    'issue' => 'Forbidden namespace/class referenced as a string',
                ];
            }
        }

        return $violations;
    }

    /**
     * Hard-blocked functions (Forti defaults + curl + file I/O, minus overrides).
     */
    protected function containsForbiddenFunctions(string $line, int $lineNumber, string $filePath): array
    {
        $funcs = $this->policy->getForbiddenFunctions();
        if (!$funcs) return [];

        $alts = array_map(static fn($f) => preg_quote((string)$f, '/'), $funcs);
        $part = '(?<![A-Za-z0-9_])(' . implode('|', $alts) . ')(?![A-Za-z0-9_])';

        $out = [];

        if (preg_match("/$part\s*\(/i", $line, $m)) {
            $out[] = [
                'type' => 'forbidden_function',
                'function' => $m[1],
                'file' => $filePath,
                'line' => $lineNumber,
                'snippet' => trim($line),
                'issue' => 'Call to forbidden function',
            ];
        }

        if (preg_match("/(?:\$\w+|\$\w+\[.*?]|\w+::\$\w+|\$\w+->\w+)\s*=\s*$part\s*;/i", $line, $m)) {
            $out[] = [
                'type' => 'forbidden_function_assignment',
                'function' => $m[1],
                'file' => $filePath,
                'line' => $lineNumber,
                'snippet' => trim($line),
                'issue' => 'Assigned to variable/array/object/class property',
            ];
        }

        return $out;
    }

    /**
     * Unsupported/risky functions (warnings) after subtracting overrides.
     */
    protected function containsUnsupportedFunctions(string $line, int $lineNumber, string $filePath): array
    {
        $funcs = $this->policy->getUnsupportedFunctions();
        if (!$funcs) return [];

        $alts = array_map(static fn($f) => preg_quote((string)$f, '/'), $funcs);
        $part = '(?<![A-Za-z0-9_])(' . implode('|', $alts) . ')(?![A-Za-z0-9_])';

        $out = [];

        if (preg_match("/$part\s*\(/i", $line, $m)) {
            $out[] = [
                'type' => 'unsupported_function',
                'function' => $m[1],
                'file' => $filePath,
                'line' => $lineNumber,
                'snippet' => trim($line),
                'issue' => 'Call to unsupported/risky function',
            ];
        }

        return $out;
    }
}
```

---
#### 21


` File: src/Core/Security/FileScanner.php`  [↑ Back to top](#index)

```php
<?php

declare(strict_types=1);

namespace Timeax\FortiPlugin\Core\Security;

use Closure;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionFunction;
use SplFileInfo;
use Throwable;
use Timeax\FortiPlugin\Core\PluginPolicy;

/**
 * Class FileScanner
 *
 * Recursively scans a directory tree and invokes a callback for files that are
 * likely to contain PHP code — either by trusted extensions (php/phtml/…)
 * OR because the file CONTENT indicates a PHP payload (e.g. "<?php" in a .jpg).
 *
 * Security goals:
 *  - Detect PHP hidden in "unrelated" files (images, text, vendor assets).
 *  - Detect double-extension tricks and Unicode filename spoofing.
 *  - Prevent symlink escapes.
 *  - Respect host ignore rules, but (by default) DO NOT ignore files that
 *    actually contain PHP payloads.
 *
 * Runtime behavior:
 *  - Web requests: enforce size limits (policy-configurable) to guard memory.
 *  - CLI and background jobs/queue workers: no size limits (full scan).
 *
 * Policy config keys (all optional):
 *  - ignore: string[]
 *      Glob-style patterns; matched against both absolute and root-relative paths.
 *      Supports negation with a leading '!'. (See shouldIgnore()).
 *
 *  - php_extensions: string[]
 *      List of extensions considered PHP-like (default: ['php','phtml','phpt']).
 *
 *  - scan_size: array{string:int}
 *      Per-extension maximum file bytes when web context (e.g., ['php' => 50000]).
 *
 *  - max_web_file_bytes: int
 *      Hard cap (bytes) for any single file read/sniff in web context. If exceeded,
 *      file is skipped without reading content (default: 256 * 1024).
 *
 *  - strict_ignore_blocks_payload: bool
 *      If true, an ignore rule will still exclude a file even when a PHP payload
 *      is detected via content sniffing. Default: false (payloads bypass ignore).
 *
 *  - php_short_open_tag_enabled: bool
 *      If set, overrides auto-detection for short tags ('<?'). Default: autodetect via ini.
 *
 *  - scanner_emit_pre_flags: bool
 *      If true (default), the scanner will emit pre-flag "issue rows" for filename/content
 *      suspicions in addition to calling your callback.
 *
 * Usage:
 *  $results = (new FileScanner($policy))->scan($dir, function (string $path, array $meta = []) {
 *      // $meta['flags'] holds filename/content suspicion flags (if any)
 *      return MyAnalyzer::analyze($path);
 *  });
 *
 * @template T
 */
class FileScanner
{
    protected PluginPolicy $policy;

    /**
     * Absolute realpath of the scan root (set during scan()).
     * @var string|null
     */
    protected ?string $root = null;

    public function __construct(PluginPolicy $policy)
    {
        $this->policy = $policy;
    }

    /**
     * Recursively scans $directory and invokes $callback for each eligible file.
     *
     * A file is eligible if:
     *  - It is a regular file (not a dir), AND
     *  - Not a symlink, AND
     *  - (has a PHP-like extension) OR (its CONTENT sniff indicates PHP payload),
     *  - Not ignored by policy 'ignore' rules (unless payload detected and
     *    strict_ignore_blocks_payload=false), AND
     *  - (Web context only) does not exceed configured size limits.
     *
     * The callback may accept either (string $path) or (string $path, array $meta).
     * $meta will include ['flags' => array<array{type:string,hint:string}>].
     *
     * @template TResult
     * @param string $directory Directory to scan (absolute or relative).
     * @param Closure(string):TResult $callback
     * @return array<int,TResult|array<int,array<string,mixed>>>      Collected non-falsy callback results (and optional pre-flag issues).
     */
    public function scan(string $directory, Closure $callback, ?Closure $emit): array
    {
        $realRoot = realpath($directory);
        $this->root = $realRoot !== false ? $realRoot : $directory;

        $config = $this->policy->getConfig();
        $allowedExts = $this->resolvePhpExtensions($config);
        $scanLimits = (array)($config['scan_size'] ?? []);
        $webHardCap = (int)($config['max_web_file_bytes'] ?? (256 * 1024));
        $strictIgnore = (bool)($config['strict_ignore_blocks_payload'] ?? false);
        $shortOpenTags = $this->shortOpenTagEnabled($config);
        $emitPreFlags = (bool)($config['scanner_emit_pre_flags'] ?? true);
        $ignore_non_php = (bool)($config['ignore_non_php'] ?? false);

        $collected = [];

        $rdiFlags = FilesystemIterator::SKIP_DOTS; // intentionally do not follow symlinks
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, $rdiFlags)
        );

        if ($emit) {
            $emit([
                'count' => iterator_count($iter),
                'title' => 'Scanning files',
                'message' => 'Scanning files in ' . $directory
            ]);
        }

        /** @var SplFileInfo $info */
        foreach ($iter as $info) {
            // Must be a regular file; reject symlinks to avoid escapes.
            if (!$info->isFile() || $info->isLink()) {
                continue;
            }

            $absPath = $this->normalizeSeparators($info->getPathname());
            $basename = $info->getBasename();

            // Collect suspicion flags for this file
            $preFlags = [];

            // Filename-level Unicode spoofing (bidi controls / isolates)
            if ($this->hasSuspiciousUnicodeName($basename)) {
                $preFlags[] = [
                    'type' => 'suspicious_filename_unicode',
                    'hint' => 'Filename contains bidi control characters (possible extension spoofing)',
                ];
            }

            // Enforce size caps only in web runtime
            if ($this->isWebContext()) {
                if ($this->exceedsMaxSizeByExt($info, $scanLimits)) {
                    $emit && $emit([
                        'title' => 'File ignored',
                        'message' => 'File ignored due to policy rules',
                        'path' => $absPath,
                        'flags' => $preFlags,
                        'issue' => 'max_web_file_bytes'
                    ]);
                    continue;
                }
                // Apply global sniff cap to avoid reading giant binaries in web
                if ($webHardCap > 0 && ($info->getSize() ?: 0) > $webHardCap) {
                    $emit && $emit([
                        'title' => 'File ignored',
                        'message' => 'File ignored due to policy rules',
                        'path' => $absPath,
                        'flags' => $preFlags,
                        'issue' => 'max_web_file_bytes'
                    ]);
                    continue;
                }
            }

            // Decide eligibility:
            // 1) Extension says PHP-like OR filename double-extension trick suggests PHP
            $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
            $extLooksPhp = in_array($ext, $allowedExts, true);
            $doubleExtSusp = $this->isDoubleExtensionSuspicious($basename, $allowedExts);
            if ($doubleExtSusp) {
                $preFlags[] = [
                    'type' => 'suspicious_double_extension',
                    'hint' => 'Double-extension pattern detected (e.g., *.jpg.php or *.php.txt)',
                ];
            }

            // 2) Content sniff says there's PHP payload (<?php, <?=, <? if enabled, or shebang)
            $payload = $this->containsPhpPayload($absPath, $shortOpenTags);
            if ($payload && !$extLooksPhp) {
                $preFlags[] = [
                    'type' => 'php_payload_in_non_php',
                    'hint' => 'PHP payload found in a non-PHP file',
                ];
            }

            if (!($extLooksPhp || $doubleExtSusp || $payload) && !$ignore_non_php) {
                // Not interesting
                $emit && $emit(['title' => 'File ignored', 'message' => 'File ignored due to policy rules', 'path' => $absPath, 'flags' => $preFlags]);
                continue;
            }

            // Ignore rules:
            $ignored = $this->shouldIgnore($absPath);
            if ($ignored) {
                // If payload is detected, we default to BYPASS ignore (safer)
                if (!$payload || $strictIgnore) {
                    $emit && $emit(['title' => 'File ignored', 'message' => 'File ignored due to policy rules', 'path' => $absPath, 'flags' => $preFlags]);
                    continue;
                }
            }

            // Invoke the callback; pass meta if it accepts a second parameter
            $meta = ['flags' => $preFlags];
            $result = $this->invokeCallback($callback, $absPath, $meta);
            if ($result) {
                $collected[] = $result;
            }

            // Optionally emit pre-flag issues directly from the scanner
            if ($emitPreFlags && $preFlags) {
                $issues = $this->makeFlagIssues($absPath, $basename, $preFlags);
                if ($issues) {
                    $collected[] = $issues; // keep chunked; caller may flatten
                }
            }
        }

        return $collected;
    }

    // ───────────────────────────── Helpers ─────────────────────────────

    /**
     * Policy-driven extension list for PHP-like files.
     * Ensures 'php' is present; defaults to ['php','phtml','phpt'].
     *
     * @param array<string,mixed> $config
     * @return array<int,string>
     */
    protected function resolvePhpExtensions(array $config): array
    {
        $exts = $config['php_extensions'] ?? ['php', 'phtml', 'phpt'];
        $exts = array_values(array_unique(array_map(
            static fn($e) => strtolower((string)$e),
            (array)$exts
        )));
        if (!in_array('php', $exts, true)) {
            array_unshift($exts, 'php');
        }
        return $exts;
    }

    /**
     * Check if a file should be ignored by policy ('ignore' patterns).
     * Supports '!' negation to re-include paths.
     *
     * @param string $absolutePath Normalized absolute path.
     * @return bool   True if ignored.
     */
    protected function shouldIgnore(string $absolutePath): bool
    {
        $patterns = $this->policy->getConfig()['ignore'] ?? [];
        if (!$patterns) {
            return false;
        }

        $normalized = $this->normalizeSeparators($absolutePath);
        $rel = $this->root
            ? ltrim($this->normalizeSeparators(str_replace($this->root, '', $normalized)), DIRECTORY_SEPARATOR)
            : $normalized;

        $ignored = false;

        foreach ($patterns as $pattern) {
            $negated = false;
            $p = $pattern;

            if (is_string($p) && $p !== '' && $p[0] === '!') {
                $negated = true;
                $p = substr($p, 1);
            }

            if (!is_string($p) || $p === '') {
                continue;
            }

            $pNorm = $this->normalizeSeparators($p);
            $match = fnmatch($pNorm, $rel) || fnmatch($pNorm, $normalized);

            if ($match) {
                $ignored = !$negated;
            }
        }

        return $ignored;
    }

    /**
     * Returns true when short open tags are enabled.
     * Can be forced via policy 'php_short_open_tag_enabled'.
     *
     * @param array<string,mixed> $config
     * @return bool
     */
    protected function shortOpenTagEnabled(array $config): bool
    {
        if (array_key_exists('php_short_open_tag_enabled', $config)) {
            return (bool)$config['php_short_open_tag_enabled'];
        }
        // Safe default: respect runtime setting
        return (bool)ini_get('short_open_tag');
    }

    /**
     * Lightweight content sniff to detect PHP payload in ANY file.
     * Reads the first ~64KB (CLI/background) or up to policy 'max_web_file_bytes' (web).
     * Looks for:
     *  - "<?php"
     *  - "<?=" (short echo)
     *  - "<?" (if short_open_tag enabled)
     *  - Shebang "#!/usr/bin/php" at start
     *
     * @param string $absPath
     * @param bool $shortTags
     * @return bool
     */
    protected function containsPhpPayload(string $absPath, bool $shortTags): bool
    {
        // Read cap: larger in CLI/background, smaller in web
        $config = $this->policy->getConfig();
        $webSniffCap = (int)($config['max_web_file_bytes'] ?? (256 * 1024));
        $cap = $this->isWebContext() ? max(4096, $webSniffCap) : (64 * 1024);

        $h = @fopen($absPath, 'rb');
        if ($h === false) {
            return false;
        }
        $data = @fread($h, $cap);
        @fclose($h);

        if ($data === false || $data === '') {
            return false;
        }

        if (str_contains($data, '<?php') || str_contains($data, '<?=')) {
            return true;
        }
        // Avoid counting XML headers as PHP payload
        if ($shortTags && str_contains($data, '<?') && !str_starts_with(ltrim($data), '<?xml')) {
            return true;
        }
        // Shebang
        return str_starts_with($data, '#!/usr/bin/php') || str_starts_with($data, "#!/usr/bin/env php");
    }

    /**
     * Detect basic double-extension tricks like "image.jpg.php" or "file.php.txt".
     *
     * @param string $basename
     * @param array<int,string> $phpExts
     * @return bool
     */
    protected function isDoubleExtensionSuspicious(string $basename, array $phpExts): bool
    {
        $lower = strtolower($basename);
        $parts = explode('.', $lower);

        if (count($parts) < 2) {
            return false;
        }

        // Example suspicious forms:
        //  - *.php.*
        //  - *.*.php
        $last = array_pop($parts);
        if (in_array($last, $phpExts, true)) {
            // e.g., name.jpg.php
            return true;
        }
        if (in_array($parts[count($parts) - 1] ?? '', $phpExts, true)) {
            // e.g., name.php.txt
            return true;
        }

        return false;
    }

    /**
     * Detect presence of Unicode bidi override or other RTL control chars in filename
     * that could visually spoof extensions in some UIs.
     *
     * @param string $basename
     * @return bool
     */
    protected function hasSuspiciousUnicodeName(string $basename): bool
    {
        // Common suspects: U+202E (RTL override), U+202A..U+202C (embedding/POP),
        // U+2066..U+2069 (isolates)
        /** @noinspection RegExpSingleCharAlternation */
        return (bool)preg_match('/\x{202E}|\x{202A}|\x{202B}|\x{202C}|\x{2066}|\x{2067}|\x{2068}|\x{2069}/u', $basename);
    }

    /**
     * Web-only: check per-extension max size via policy scan_size.
     *
     * @param SplFileInfo $info
     * @param array<string,int> $limits
     * @return bool
     */
    protected function exceedsMaxSizeByExt(SplFileInfo $info, array $limits): bool
    {
        if (!$this->isWebContext()) {
            return false;
        }
        $ext = strtolower(pathinfo($info->getPathname(), PATHINFO_EXTENSION));
        $limit = ($limits[$ext] ?? 0);
        if ($limit <= 0) {
            return false;
        }
        $size = $info->getSize();
        return $size !== false && $size > $limit;
    }

    /**
     * Normalize path separators to the current OS.
     *
     * @param string $path
     * @return string
     */
    protected function normalizeSeparators(string $path): string
    {
        return str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
    }

    /**
     * True if running in a web (non-console) context.
     * Laravel's queue workers and CLI commands runInConsole() === true.
     *
     * @return bool
     */
    protected function isWebContext(): bool
    {
        // Prefer Laravel helper if available; fallback to PHP_SAPI check.
        if (function_exists('app')) {
            try {
                return !app()->runningInConsole();
            } catch (Throwable) {
                // ignore
            }
        }
        return PHP_SAPI !== 'cli';
    }

    /**
     * Invoke analyzer callback. If it accepts (path, meta), pass both.
     *
     * @template TResult
     * @param Closure $callback Closure(string $path [, array $meta]): TResult
     * @param string $path
     * @param array<string,mixed> $meta
     * @return mixed                  TResult|false|null
     */
    protected function invokeCallback(Closure $callback, string $path, array $meta): mixed
    {
        $arity = $this->callbackArity($callback);
        if ($arity >= 2) {
            return $callback($path, $meta);
        }
        return $callback($path);
    }

    /**
     * Determine number of parameters accepted by the callback.
     *
     * @param Closure $callback
     * @return int
     */
    protected function callbackArity(Closure $callback): int
    {
        try {
            return (new ReflectionFunction($callback))->getNumberOfParameters();
        } catch (Throwable) {
            return 1; // safe fallback: assume single-arg
        }
    }

    /**
     * Convert collected pre-flags into canonical issue rows.
     *
     * Each flag item should be ['type'=>string,'hint'=>string].
     * The filename (basename) is reported as the "token" for quick context.
     *
     * @param string $file
     * @param string $basename
     * @param array<int,array<string,mixed>> $flags
     * @return array<int,array<string,mixed>>
     */
    protected function makeFlagIssues(string $file, string $basename, array $flags): array
    {
        $rows = [];
        foreach ($flags as $f) {
            $rows[] = [
                'type' => (string)($f['type'] ?? 'suspicious'),
                'token' => $basename,
                'file' => $file,
                'line' => 0,      // filename-level issue (not line-based)
                'snippet' => '',
                'issue' => (string)($f['hint'] ?? 'Suspicious file indicator'),
            ];
        }
        return $rows;
    }
}
```

---
#### 22


` File: src/Core/Security/HostConfigValidator.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Core\Security;

use Timeax\FortiPlugin\Core\Exceptions\HostConfigException;
use Timeax\FortiPlugin\Core\Exceptions\DuplicateSettingIdException;

final class HostConfigValidator
{
    /**
     * Validate a HostConfig object:
     * - 'global' has no 'id' and only SettingValue values
     * - 'settings' is an array of Setting objects with unique 'id'
     * - Each Setting's non-id props are valid SettingValue types
     *
     * @throws HostConfigException
     */
    public static function validate(array $hostConfig): void
    {
        // ----- global (optional) -----
        if (array_key_exists('global', $hostConfig)) {
            if (!is_array($hostConfig['global'])) {
                throw new HostConfigException("'global' must be an object.");
            }
            if (array_key_exists('id', $hostConfig['global'])) {
                throw new HostConfigException("'global' must not contain an 'id'.");
            }
            foreach ($hostConfig['global'] as $k => $v) {
                if (!self::isValidSettingValue($v)) {
                    throw new HostConfigException("Invalid SettingValue at global['{$k}'].");
                }
            }
        }

        // ----- settings (optional) -----
        $ids = [];
        if (array_key_exists('settings', $hostConfig)) {
            if (!is_array($hostConfig['settings'])) {
                throw new HostConfigException("'settings' must be an array of Setting objects.");
            }

            foreach ($hostConfig['settings'] as $i => $setting) {
                $path = "settings[{$i}]";

                if (!is_array($setting)) {
                    throw new HostConfigException("'{$path}' must be an object.");
                }
                if (!array_key_exists('id', $setting)) {
                    throw new HostConfigException("'{$path}.id' is required.");
                }

                $id = $setting['id'];
                if (!is_string($id) && !is_int($id) && !is_float($id)) {
                    throw new HostConfigException("'{$path}.id' must be a string or number.");
                }

                // Enforce uniqueness (stringify so '1' and 1 collide)
                $idKey = (string)$id;
                if (isset($ids[$idKey])) {
                    throw new DuplicateSettingIdException($id, "at {$path}");
                }
                $ids[$idKey] = true;

                // Validate each non-id property value
                foreach ($setting as $k => $v) {
                    if ($k === 'id') {
                        continue;
                    }
                    if (!self::isValidSettingValue($v)) {
                        throw new HostConfigException("Invalid SettingValue at {$path}['{$k}'].");
                    }
                }
            }
        }
    }

    /** SettingValue = boolean | null | string | number | string[] | map<string, TriState> */
    private static function isValidSettingValue(mixed $v): bool
    {
        if (is_bool($v) || is_null($v) || is_string($v) || is_int($v) || is_float($v)) {
            return true;
        }

        if (is_array($v)) {
            // list of strings?
            if (self::isStringList($v)) {
                return true;
            }
            // map<string, TriState> ?
            if (!self::isList($v)) {
                foreach ($v as $kk => $vv) {
                    if (!is_string($kk)) return false;
                    if (!is_bool($vv) && !is_null($vv)) return false; // TriState
                }
                return true;
            }
        }

        return false;
    }

    private static function isStringList(array $arr): bool
    {
        if (!self::isList($arr)) return false;
        foreach ($arr as $item) {
            if (!is_string($item)) return false;
        }
        return true;
    }

    /** Polyfill for PHP < 8.1 array_is_list */
    private static function isList(array $arr): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($arr);
        }
        $i = 0;
        foreach ($arr as $k => $_) {
            if ($k !== $i++) return false;
        }
        return true;
    }
}
```

---
#### 23


` File: src/Core/Security/PermissionManifestValidator.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection PhpUnnecessaryCurlyVarSyntaxInspection */
/** @noinspection PhpSameParameterValueInspection */

/** @noinspection PhpUndefinedClassInspection */

namespace Timeax\FortiPlugin\Core\Security;

use InvalidArgumentException;
use JsonException;
use Timeax\FortiPlugin\Lib\Obfuscator;
use Timeax\FortiPlugin\Permissions\Support\HostConfigNormalizer;

final class PermissionManifestValidator
{
    // inside PermissionManifestValidator class (properties section)
    private array $moduleAliasMap;      // alias => ['map' => FQCN, 'docs' => string|null]
    private array $moduleFqcnToAlias;   // FQCN => alias
    /** Canonical codec groups → method names */
    private array $codecGroups;

    /** Allowed rule types */
    private const TYPES = ['db', 'file', 'network', 'notify', 'module', 'codec'];

    /** Per-type allowed actions */
    private const ACTIONS = [
        'db' => ['select', 'insert', 'update', 'delete', 'truncate', 'transaction'],
        'file' => ['read', 'write', 'append', 'delete', 'mkdir', 'rmdir', 'list'],
        'network' => ['request'],
        'notify' => ['send'],
        'module' => ['call', 'publish', 'subscribe'],
        'codec' => ['invoke'],
    ];

    /** HTTP method allowlist */
    private const HTTP_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

    /* ========================= Host-provided catalogs ========================= */

    /** @var string[] Allowed notification channels (from host config). */
    private array $allowedChannels;

    /**
     * Host DB model map (alias => ['map' => FQCN, 'relations' => array<string,string>])
     * Example entry: 'user' => ['map' => 'App\\Models\\User', 'relations' => ['posts' => 'post']]
     */
    private array $modelAliasMap;

    /** @var array<string,string> FQCN => alias (reverse lookup for convenience) */
    private array $fqcnToAlias;

    /**
     * @param string[]|null $allowedChannels If null, tries config('fortiplugin.notifications-channels', [])
     * @param array<string,array{map:string,relations?:array<string,string>}>|null $modelConfig
     *        If null, tries config('fortiplugin.models', [])
     */
    public function __construct(
        ?array $allowedChannels = null,
        ?array $modelConfig = null,
        ?array $moduleConfig = null,
        ?array $codecConfig = null,
    )
    {
        $this->allowedChannels = $this->normalizeChannels(
            $allowedChannels ?? $this->readConfig('fortiplugin-maps.notifications-channels', [])
        );

        $this->modelAliasMap = $this->normalizeModels(
            $modelConfig ?? $this->readConfig('fortiplugin-maps.models', [])
        );
        $this->fqcnToAlias = [];
        foreach ($this->modelAliasMap as $alias => $def) {
            $this->fqcnToAlias[$def['map']] = $alias;
        }

        // NEW: modules catalog
        $this->moduleAliasMap = $this->normalizeModules(
            $moduleConfig ?? $this->readConfig('fortiplugin-maps.modules', [])
        );
        $this->moduleFqcnToAlias = [];
        foreach ($this->moduleAliasMap as $alias => $def) {
            $this->moduleFqcnToAlias[$def['map']] = $alias;
        }

        $this->codecGroups = $codecConfig ?? $this->loadCodecGroups();
    }

    /**
     * Pull groups from Timeax\FortiPlugin\Lib\Obfuscator::availableGroups().
     * Obfuscator returns [group => [methodName => wrapperName]].
     * We normalize to [group => [methodName, ...]].
     */
    private function loadCodecGroups(): array
    {
        if (!class_exists(Obfuscator::class) || !method_exists(Obfuscator::class, 'availableGroups')) {
            return []; // no catalog available (e.g., during tests)
        }

        return HostConfigNormalizer::codecGroupsFromObfuscatorMap(Obfuscator::availableGroups());
    }
    /* ========================= Public API ========================= */

    /** Validate a manifest (array or JSON string). Returns normalized manifest or throws. */
    public function validate(array|string $manifest): array
    {
        $data = is_string($manifest) ? $this->decodeJson($manifest) : $manifest;

        $errors = [];
        $norm = ['required_permissions' => [], 'optional_permissions' => []];

        // Top-level shape
        if (!is_array($data)) {
            $this->boom('$.', 'manifest must be an object', $errors);
        }
        $this->rejectUnknownKeys($data, ['required_permissions', 'optional_permissions', '$schema', '$id', 'title', 'description'], '$');

        // required_permissions (required)
        if (!array_key_exists('required_permissions', $data) || !is_array($data['required_permissions'])) {
            $this->boom('$.required_permissions', 'required_permissions must be an array', $errors);
        }

        // optional_permissions (optional)
        if (isset($data['optional_permissions']) && !is_array($data['optional_permissions'])) {
            $this->boom('$.optional_permissions', 'optional_permissions must be an array if provided', $errors);
        }

        // Validate both lists
        foreach (['required_permissions', 'optional_permissions'] as $listKey) {
            if (!isset($data[$listKey]) || !is_array($data[$listKey])) {
                continue;
            }
            foreach (array_values($data[$listKey]) as $i => $rule) {
                $path = '$.' . $listKey . '[' . $i . ']';
                $norm[$listKey][] = $this->validateRule($rule, $path, $errors);
            }
        }

        if ($errors) {
            $msg = "Permission manifest validation failed:\n- " . implode("\n- ", $errors);
            throw new InvalidArgumentException($msg);
        }

        return $norm;
    }

    /* ========================= Rule validators ========================= */

    private function validateRule(mixed $rule, string $path, array &$errors): array
    {
        if (!is_array($rule)) {
            $this->boom($path, 'rule must be an object', $errors);
            return [];
        }

        $this->rejectUnknownKeys($rule, ['type', 'target', 'actions', 'conditions', 'audit', 'justification', 'methods', 'groups', 'options'], $path);

        // common fields
        $type = $rule['type'] ?? null;
        if (!is_string($type) || !in_array($type, self::TYPES, true)) {
            $this->boom("{$path}.type", 'type must be one of: ' . implode(',', self::TYPES), $errors);
        }

        $actions = $rule['actions'] ?? null;
        if (!is_array($actions) || $actions === []) {
            $this->boom("{$path}.actions", 'actions must be a non-empty array', $errors);
        } else {
            $actions = array_values(array_unique(array_map('strval', $actions)));
            $allowed = self::ACTIONS[$type] ?? [];
            foreach ($actions as $a) {
                if (!in_array($a, $allowed, true)) {
                    $this->boom("{$path}.actions", "action '{$a}' not allowed for type '{$type}'", $errors);
                }
            }
        }

        // audit (optional)
        if (isset($rule['audit'])) {
            $this->validateAudit($rule['audit'], "{$path}.audit", $errors);
        }

        // conditions (optional; only setting_link, guard, env)
        $condNorm = null;
        if (isset($rule['conditions'])) {
            $condNorm = $this->validateConditions($rule['conditions'], "{$path}.conditions", $errors);
        }

        // per-type target + extras
        $target = $rule['target'] ?? null;
        $normalized = [
            'type' => $type,
            'target' => null,
            'actions' => $actions ?? [],
            'conditions' => $condNorm,
            'audit' => $rule['audit'] ?? null,
            'justification' => isset($rule['justification']) ? (string)$rule['justification'] : null,
        ];

        switch ($type) {
            case 'db':
                $normalized['target'] = $this->validateDbTarget($target, "{$path}.target", $errors, $actions);
                break;

            case 'file':
                $normalized['target'] = $this->validateFileTarget($target, "{$path}.target", $errors);
                break;

            case 'network':
                $normalized['target'] = $this->validateNetworkTarget($target, "{$path}.target", $errors);
                break;

            case 'notify':
                $normalized['target'] = $this->validateNotifyTarget($target, "{$path}.target", $errors);
                break;

            case 'module':
                $normalized['target'] = $this->validateModuleTarget($target, "{$path}.target", $errors);
                break;

            case 'codec':
                $normalized['target'] = $this->validateCodecTarget($target, "{$path}.target", $errors);
                [$resolved, $requiresGuard] = $this->validateCodecMethodsAndGroups(
                    $rule['methods'] ?? null,
                    $rule['groups'] ?? null,
                    $rule['options'] ?? null,
                    ($path)
                    , $errors);

                $normalized['methods'] = $rule['methods'] ?? null;
                $normalized['groups'] = $rule['groups'] ?? null;
                $normalized['options'] = $rule['options'] ?? null;
                $normalized['resolved_methods'] = $resolved;
                $normalized['requires_unserialize_guard'] = $requiresGuard;
                break;
        }

        return $normalized;
    }

    /* ========================= Type: DB ========================= */

    private function validateDbTarget(mixed $target, string $path, array &$errors, array $actions): ?array
    {
        if (!is_array($target)) {
            $this->boom($path, 'target must be an object', $errors);
            return null;
        }
        $hasModel = array_key_exists('model', $target);
        $hasTable = array_key_exists('table', $target);
        if ($hasModel === $hasTable) {
            $this->boom($path, "exactly one of 'model' or 'table' is required", $errors);
        }

        $modelFqcn = null;
        $modelAlias = null;
        $hostColsAll = null;
        $hostColsWritable = null;

        if ($hasModel) {
            $decl = $target['model'];
            if (!is_string($decl) || $decl === '') {
                $this->boom("{$path}.model", 'model must be a non-empty string (alias or FQCN)', $errors);
            } else if (array_key_exists($decl, $this->modelAliasMap)) {
                $modelAlias = $decl;
                $modelFqcn = $this->modelAliasMap[$decl]['map'];
                $hostColsAll = $this->modelAliasMap[$decl]['columns']['all'] ?? null;
                $hostColsWritable = $this->modelAliasMap[$decl]['columns']['writable'] ?? null;
            } else if (array_key_exists($decl, $this->fqcnToAlias)) {
                $modelFqcn = $decl;
                $modelAlias = $this->fqcnToAlias[$decl];
                $hostColsAll = $this->modelAliasMap[$modelAlias]['columns']['all'] ?? null;
                $hostColsWritable = $this->modelAliasMap[$modelAlias]['columns']['writable'] ?? null;
            } else if ($this->modelAliasMap !== []) {
                $this->boom("{$path}.model", "unknown model alias/FQCN '{$decl}' (not in host 'models' map)", $errors);
            } else {
                $modelFqcn = $decl;
            }
        }

        if ($hasTable && (!is_string($target['table']) || $target['table'] === '')) {
            $this->boom("{$path}.table", 'table must be a non-empty string', $errors);
        }

        // columns in manifest (optional)
        $cols = null;
        if (isset($target['columns'])) {
            if (!$this->isStringList($target['columns'])) {
                $this->boom("{$path}.columns", 'columns must be an array of unique strings', $errors);
            } else {
                $cols = array_values(array_unique(array_map('strval', $target['columns'])));
            }
        }

        $this->rejectUnknownKeys($target, ['model', 'table', 'columns'], $path);

        // Enforce host column policy if present and a model is known
        if ($modelAlias !== null) {
            $hasWrite = (bool)array_intersect($actions, ['insert', 'update']);

            if ($cols !== null) {
                // If write actions requested, require ⊆ writable (when known); else require ⊆ all (when known)
                if ($hasWrite && $hostColsWritable !== null) {
                    $diff = array_diff($cols, $hostColsWritable);
                    if ($diff) {
                        $this->boom("{$path}.columns", "columns not writable by host policy: " . implode(', ', $diff), $errors);
                    }
                }
                if ($hostColsAll !== null) {
                    $diffAll = array_diff($cols, $hostColsAll);
                    if ($diffAll) {
                        $this->boom("{$path}.columns", "columns not allowed by host policy: " . implode(', ', $diffAll), $errors);
                    }
                }
            }
        }

        return [
            'model' => $modelFqcn,
            'model_alias' => $modelAlias,
            'table' => $hasTable ? $target['table'] : null,
            'columns' => $cols,
        ];
    }

    /* ========================= Type: FILE ========================= */

    private function validateFileTarget(mixed $target, string $path, array &$errors): ?array
    {
        if (!is_array($target)) {
            $this->boom($path, 'target must be an object', $errors);
            return null;
        }
        foreach (['base_dir', 'paths'] as $req) {
            if (!array_key_exists($req, $target)) {
                $this->boom("{$path}.{$req}", "{$req} is required", $errors);
            }
        }
        if (!is_string($target['base_dir'] ?? null) || $target['base_dir'] === '') {
            $this->boom("{$path}.base_dir", 'base_dir must be a non-empty string', $errors);
        }
        if (!$this->isStringList($target['paths'] ?? null, true)) {
            $this->boom("{$path}.paths", 'paths must be a non-empty array of unique strings', $errors);
        }
        foreach ($target['paths'] ?? [] as $idx => $p) {
            if (str_contains($p, '..')) {
                $this->boom("{$path}.paths[{$idx}]", "path must not contain '..'", $errors);
            }
        }
        if (isset($target['follow_symlinks']) && !is_bool($target['follow_symlinks'])) {
            $this->boom("{$path}.follow_symlinks", 'follow_symlinks must be boolean', $errors);
        }
        $this->rejectUnknownKeys($target, ['base_dir', 'paths', 'follow_symlinks'], $path);

        return [
            'base_dir' => (string)$target['base_dir'],
            'paths' => array_values(array_unique(array_map('strval', $target['paths']))),
            'follow_symlinks' => (bool)($target['follow_symlinks'] ?? false),
        ];
    }

    /* ========================= Type: NETWORK ========================= */

    private function validateNetworkTarget(mixed $target, string $path, array &$errors): ?array
    {
        if (!is_array($target)) {
            $this->boom($path, 'target must be an object', $errors);
            return null;
        }
        foreach (['hosts', 'methods'] as $req) {
            if (!array_key_exists($req, $target)) {
                $this->boom("{$path}.{$req}", "{$req} is required", $errors);
            }
        }
        if (!$this->isStringList($target['hosts'] ?? null, true)) {
            $this->boom("{$path}.hosts", 'hosts must be a non-empty array of strings', $errors);
        } else {
            foreach ($target['hosts'] as $i => $h) {
                if (!preg_match('/^([a-z0-9.-]+|\*\.[a-z0-9.-]+)$/', $h)) {
                    $this->boom("{$path}.hosts[{$i}]", "invalid host pattern '{$h}'", $errors);
                }
            }
        }
        if (!$this->isStringList($target['methods'] ?? null, true)) {
            $this->boom("{$path}.methods", 'methods must be a non-empty array of strings', $errors);
        } else {
            foreach ($target['methods'] as $i => $m) {
                if (!in_array(strtoupper($m), self::HTTP_METHODS, true)) {
                    $this->boom("{$path}.methods[{$i}]", "method '{$m}' not allowed", $errors);
                }
            }
        }
        if (isset($target['schemes'])) {
            if (!$this->isStringList($target['schemes'])) {
                $this->boom("{$path}.schemes", 'schemes must be an array of strings', $errors);
            } else {
                foreach ($target['schemes'] as $i => $s) {
                    if (!in_array($s, ['https', 'http'], true)) {
                        $this->boom("{$path}.schemes[{$i}]", "scheme '{$s}' not allowed", $errors);
                    }
                }
            }
        }
        if (isset($target['ports'])) {
            if (!is_array($target['ports'])) {
                $this->boom("{$path}.ports", 'ports must be an array of integers', $errors);
            } else {
                foreach ($target['ports'] as $i => $p) {
                    if (!is_int($p) || $p < 1 || $p > 65535) {
                        $this->boom("{$path}.ports[{$i}]", 'port must be an integer between 1 and 65535', $errors);
                    }
                }
            }
        }
        if (isset($target['ips_allowed'])) {
            if (!$this->isStringList($target['ips_allowed'])) {
                $this->boom("{$path}.ips_allowed", 'ips_allowed must be an array of strings', $errors);
            } else {
                foreach ($target['ips_allowed'] as $i => $ip) {
                    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                        $this->boom("{$path}.ips_allowed[{$i}]", "invalid IP '{$ip}'", $errors);
                    }
                }
            }
        }
        if (isset($target['headers_allowed']) && !$this->isStringList($target['headers_allowed'])) {
            $this->boom("{$path}.headers_allowed", 'headers_allowed must be an array of strings', $errors);
        }
        if (isset($target['paths']) && !$this->isStringList($target['paths'])) {
            $this->boom("{$path}.paths", 'paths must be an array of strings', $errors);
        }
        if (isset($target['auth_via_host_secret']) && !is_bool($target['auth_via_host_secret'])) {
            $this->boom("{$path}.auth_via_host_secret", 'auth_via_host_secret must be boolean', $errors);
        }

        $this->rejectUnknownKeys($target, [
            'hosts', 'schemes', 'ports', 'paths', 'methods', 'headers_allowed', 'auth_via_host_secret', 'ips_allowed'
        ], $path);

        return [
            'hosts' => array_values(array_unique(array_map('strval', $target['hosts']))),
            'schemes' => isset($target['schemes']) ? array_values(array_unique(array_map('strval', $target['schemes']))) : null,
            'ports' => isset($target['ports']) ? array_values($target['ports']) : null,
            'paths' => isset($target['paths']) ? array_values(array_unique(array_map('strval', $target['paths']))) : null,
            'methods' => array_values(array_unique(array_map(static fn($m) => strtoupper($m), $target['methods']))),
            'headers_allowed' => isset($target['headers_allowed']) ? array_values(array_unique(array_map('strval', $target['headers_allowed']))) : null,
            'auth_via_host_secret' => (bool)($target['auth_via_host_secret'] ?? true),
            'ips_allowed' => isset($target['ips_allowed']) ? array_values(array_unique(array_map('strval', $target['ips_allowed']))) : null,
        ];
    }

    /* ========================= Type: NOTIFY ========================= */

    private function validateNotifyTarget(mixed $target, string $path, array &$errors): ?array
    {
        if (!is_array($target)) {
            $this->boom($path, 'target must be an object', $errors);
            return null;
        }
        if (!$this->isStringList($target['channels'] ?? null, true)) {
            $this->boom("{$path}.channels", 'channels must be a non-empty array of strings', $errors);
        } else if ($this->allowedChannels !== []) {
            foreach ($target['channels'] as $i => $c) {
                if (!in_array($c, $this->allowedChannels, true)) {
                    $this->boom("{$path}.channels[{$i}]", "channel '{$c}' is not allowed by host", $errors);
                }
            }
        }
        if (isset($target['templates']) && !$this->isStringList($target['templates'])) {
            $this->boom("{$path}.templates", 'templates must be an array of strings', $errors);
        }
        if (isset($target['recipients']) && !$this->isStringList($target['recipients'])) {
            $this->boom("{$path}.recipients", 'recipients must be an array of strings', $errors);
        }
        $this->rejectUnknownKeys($target, ['channels', 'templates', 'recipients'], $path);

        return [
            'channels' => array_values(array_unique(array_map('strval', $target['channels']))),
            'templates' => isset($target['templates']) ? array_values(array_unique(array_map('strval', $target['templates']))) : null,
            'recipients' => isset($target['recipients']) ? array_values(array_unique(array_map('strval', $target['recipients']))) : null,
        ];
    }

    /* ========================= Type: MODULE ========================= */

    private function validateModuleTarget(mixed $target, string $path, array &$errors): ?array
    {
        if (!is_array($target)) {
            $this->boom($path, 'target must be an object', $errors);
            return null;
        }

        foreach (['plugin', 'apis'] as $req) {
            if (!array_key_exists($req, $target)) {
                $this->boom("{$path}.{$req}", "{$req} is required", $errors);
            }
        }

        // plugin must be a non-empty string
        $pluginDecl = $target['plugin'] ?? null;
        if (!is_string($pluginDecl) || $pluginDecl === '') {
            $this->boom("{$path}.plugin", 'plugin must be a non-empty string', $errors);
        }

        // apis must be a non-empty list of strings
        if (!$this->isStringList($target['apis'] ?? null, true)) {
            $this->boom("{$path}.apis", 'apis must be a non-empty array of strings', $errors);
        }

        $this->rejectUnknownKeys($target, ['plugin', 'apis'], $path);

        // ---- Host catalog check (alias or FQCN) ----
        $alias = null;
        $fqcn = null;
        $docs = null;

        if (array_key_exists($pluginDecl, $this->moduleAliasMap)) {
            // Declared as alias
            $alias = $pluginDecl;
            $fqcn = $this->moduleAliasMap[$alias]['map'];
            $docs = $this->moduleAliasMap[$alias]['docs'];
        } elseif (array_key_exists($pluginDecl, $this->moduleFqcnToAlias)) {
            // Declared as FQCN
            $fqcn = $pluginDecl;
            $alias = $this->moduleFqcnToAlias[$fqcn];
            $docs = $this->moduleAliasMap[$alias]['docs'] ?? null;
        } else if ($this->moduleAliasMap !== []) {
            $this->boom("{$path}.plugin", "unknown module '{$pluginDecl}' (not in host modules map)", $errors);
        } else {
            // No catalog -> accept free-form, but no alias/docs
            $fqcn = $pluginDecl;
        }

        return [
            'plugin' => (string)$pluginDecl,                                      // as declared
            'plugin_alias' => $alias,                                                   // normalized alias (if known)
            'plugin_fqcn' => $fqcn,                                                    // normalized FQCN (if known or free-form)
            'plugin_docs' => $docs,                                                    // host docs URL (if any)
            'apis' => array_values(array_unique(array_map('strval', $target['apis']))),
        ];
    }

    /* ========================= Type: CODEC ========================= */

    private function validateCodecTarget(mixed $target, string $path, array &$errors): ?string
    {
        if (!is_string($target)) {
            $this->boom($path, 'target must be the string "codec"', $errors);
            return null;
        }
        if ($target !== 'codec') {
            $this->boom($path, 'codec rule target must be "codec"', $errors);
        }
        return 'codec';
    }

    /**
     * Validate codec methods/groups + options, and return [resolved_methods, requires_unserialize_guard].
     */
    private function validateCodecMethodsAndGroups(mixed $methods, mixed $groups, mixed $options, string $path, array &$errors): array
    {
        $resolved = [];
        $needsGuard = false;

        // groups (optional)
        if ($groups !== null) {
            if (!$this->isStringList($groups)) {
                $this->boom("{$path}.groups", 'groups must be an array of strings', $errors);
            } else {
                foreach ($groups as $i => $g) {
                    if (!isset($this->codecGroups[$g])) {
                        $this->boom("{$path}.groups[{$i}]", "unknown codec group '{$g}'", $errors);
                        continue;
                    }

                    // If the group includes 'unserialize', require allowlist options
                    if (in_array('unserialize', $this->codecGroups[$g], true)) {
                        $needsGuard = true;
                    }

                    // Append methods directly (avoid array_merge in loop)
                    foreach ($this->codecGroups[$g] as $method) {
                        $resolved[] = $method;
                    }
                }
            }
        }

        // methods (optional | "*" | array)
        $wildcard = false;
        if ($methods !== null) {
            if ($methods === '*') {
                $wildcard = true;
                $needsGuard = true; // wildcard includes 'unserialize'
            } elseif ($this->isStringList($methods)) {
                foreach ($methods as $i => $m) {
                    if (!preg_match('/^[a-z0-9_]+$/', $m)) {
                        $this->boom("{$path}.methods[{$i}]", "invalid method name '{$m}'", $errors);
                    }
                    if ($m === 'unserialize') {
                        $needsGuard = true;
                    }
                    $resolved[] = $m;
                }
            } else {
                $this->boom("{$path}.methods", 'methods must be "*" or an array of strings', $errors);
            }
        }

        if ($methods === null && $groups === null) {
            $this->boom($path, 'codec rule requires one of: methods or groups', $errors);
        }

        // options (required if guard is needed)
        if ($needsGuard) {
            if (!is_array($options) || !array_key_exists('allow_unserialize_classes', $options)) {
                $this->boom("{$path}.options", 'options.allow_unserialize_classes is required when methods="*" or includes "unserialize", or groups include "serialize"', $errors);
            } else if (!is_array($options['allow_unserialize_classes'])) {
                $this->boom("{$path}.options.allow_unserialize_classes", 'must be an array (empty array = no classes allowed)', $errors);
            } else {
                foreach ($options['allow_unserialize_classes'] as $i => $cls) {
                    if (!is_string($cls) || $cls === '') {
                        $this->boom("{$path}.options.allow_unserialize_classes[{$i}]", 'class name must be a non-empty string', $errors);
                    }
                }
            }
        } elseif ($options !== null) {
            $this->rejectUnknownKeys($options, ['allow_unserialize_classes'], "{$path}.options");
        }

        // Normalize resolved methods
        if ($wildcard) {
            $resolved = '*';
        } else {
            $resolved = array_values(array_unique($resolved));
        }

        return [$resolved, $needsGuard];
    }

    /* ========================= Common validators ========================= */

    private function validateAudit(mixed $audit, string $path, array &$errors): void
    {
        if (!is_array($audit)) {
            $this->boom($path, 'audit must be an object', $errors);
            return;
        }
        $this->rejectUnknownKeys($audit, ['log', 'redact_fields', 'tags'], $path);
        if (isset($audit['log']) && !in_array($audit['log'], ['always', 'on_deny', 'never'], true)) {
            $this->boom("{$path}.log", "log must be 'always', 'on_deny' or 'never'", $errors);
        }
        if (isset($audit['redact_fields']) && !$this->isStringList($audit['redact_fields'])) {
            $this->boom("{$path}.redact_fields", 'redact_fields must be an array of strings', $errors);
        }
        if (isset($audit['tags']) && !$this->isStringList($audit['tags'])) {
            $this->boom("{$path}.tags", 'tags must be an array of strings', $errors);
        }
    }

    private function validateConditions(mixed $cond, string $path, array &$errors): ?array
    {
        if (!is_array($cond)) {
            $this->boom($path, 'conditions must be an object', $errors);
            return null;
        }
        $this->rejectUnknownKeys($cond, ['setting_link', 'guard', 'env'], $path);

        $out = ['setting_link' => null, 'guard' => null, 'env' => null];

        if (array_key_exists('setting_link', $cond)) {
            $v = $cond['setting_link'];
            if (!is_string($v) && !is_int($v) && !is_float($v)) {
                $this->boom("{$path}.setting_link", 'setting_link must be a string or number', $errors);
            } else {
                $out['setting_link'] = $v;
            }
        }
        if (array_key_exists('guard', $cond)) {
            if (!is_string($cond['guard']) || $cond['guard'] === '') {
                $this->boom("{$path}.guard", 'guard must be a non-empty string', $errors);
            } else {
                $out['guard'] = $cond['guard'];
            }
        }
        if (array_key_exists('env', $cond)) {
            $env = $cond['env'];
            if (!is_array($env)) {
                $this->boom("{$path}.env", 'env must be an object with allow/deny arrays', $errors);
            } else {
                $this->rejectUnknownKeys($env, ['allow', 'deny'], "{$path}.env");
                foreach (['allow', 'deny'] as $k) {
                    if (isset($env[$k]) && !$this->isStringList($env[$k])) {
                        $this->boom("{$path}.env.{$k}", "{$k} must be an array of strings", $errors);
                    }
                }
                $out['env'] = [
                    'allow' => isset($env['allow']) ? array_values(array_unique(array_map('strval', $env['allow']))) : null,
                    'deny' => isset($env['deny']) ? array_values(array_unique(array_map('strval', $env['deny']))) : null,
                ];
            }
        }

        return $out;
    }

    /* ========================= Helpers ========================= */

    private function decodeJson(string $json): array
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Invalid JSON: ' . $e->getMessage(), 0, $e);
        }
        if (!is_array($data)) {
            throw new InvalidArgumentException('Manifest root must decode to an object');
        }
        return $data;
    }

    private function isStringList(mixed $v, bool $nonEmpty = false): bool
    {
        if (!is_array($v)) return false;
        if ($nonEmpty && $v === []) return false;
        foreach ($v as $s) {
            if (!is_string($s)) return false;
        }
        return count($v) === count(array_unique($v));
    }

    private function rejectUnknownKeys(array $obj, array $allowed, string $path): void
    {
        $unknown = array_diff(array_keys($obj), $allowed);
        if ($unknown) {
            $keys = implode(', ', $unknown);
            throw new InvalidArgumentException("{$path}: unknown field(s): {$keys}");
        }
    }

    private function boom(string $path, string $msg, array &$errors): void
    {
        $errors[] = "{$path}: {$msg}";
    }

    /** Config helper that safely no-ops outside Laravel. */
    private function readConfig(string $key, mixed $default): mixed
    {
        if (function_exists('config')) {
            return config($key, $default);
        }
        return $default;
    }

    /**
     * Normalize channels list: supports ['email','sms'] or ['email'=>true,'sms'=>true].
     * Returns a unique list of strings.
     */
    private function normalizeChannels(array $channels): array
    {
        return HostConfigNormalizer::notificationChannels($channels);
    }

    /**
     * Normalize models map. Ensures 'map' (FQCN), optional 'relations', and optional
     * 'columns' policy with 'all' and 'writable' (writable ⊆ all).
     * @param array<string,mixed> $models
     * @return array<string,array{map:string,relations:array<string,string>,columns:array{all?:array,writable?:array}}>
     */
    private function normalizeModels(array $models): array
    {
        return HostConfigNormalizer::models($models);
    }

    /**
     * Normalize modules map. Ensures 'map' (FQCN) exists; 'docs' optional string.
     * @param array<string,mixed> $modules
     * @return array<string,array{map:string,docs:?string}>
     */
    private function normalizeModules(array $modules): array
    {
        return HostConfigNormalizer::modules($modules);
    }
}
```

---
#### 24


` File: src/Core/Security/PluginSecurityScanner.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection GrazieInspection */
/** @noinspection PhpUnusedPrivateMethodInspection */
/** @noinspection PhpUnusedLocalVariableInspection */
/** @noinspection PhpPossiblePolymorphicInvocationInspection */
/** @noinspection PhpUndefinedVariableInspection */

/** @noinspection NotOptimalIfConditionsInspection */

namespace Timeax\FortiPlugin\Core\Security;

use JsonException;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\Eval_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\UnionType;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use Throwable;
use Timeax\FortiPlugin\Core\PluginPolicy;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use Timeax\FortiPlugin\Core\Security\Concerns\ResolvesNames;

/**
 * PluginSecurityScanner: Extensible, policy-driven, always-forbidden-aware PHP plugin validator
 */
class PluginSecurityScanner extends NodeVisitorAbstract
{
    use ResolvesNames;

    protected array $config;
    protected PluginPolicy $policy;
    protected array $aliases = []; // Aliases (from use statements) in this file
    protected array $matches = [];
    /** @var array<string, string[]>  lower(fqcn) => [lower(method)...] */
    private array $classAllowlist = [];
    /** @var array<string,string> $variableTypes // $var => FQCN (no leading \) */
    private array $variableTypes = [];
    /** @var array<string,string> $classNameVars // $var => FQCN (no leading \) */
    private array $classNameVars = [];
    protected CallGraphAnalyzer $callGraphAnalyzer;
    protected mixed $currentFile = null;

    protected array $variableValues = [];
    protected array $superglobals = ['_GET', '_POST', '_REQUEST', '_COOKIE', '_FILES', '_SESSION', '_SERVER', '_ENV'];

    public function __construct(array $config = null, $filePath = null)
    {
        $this->policy = new PluginPolicy($config);
        $this->callGraphAnalyzer = new CallGraphAnalyzer($this->policy);
        $this->config = $config;
        $this->currentFile = $filePath;
    }

    public function getPolicy(): PluginPolicy
    {
        return $this->policy;
    }

    public function setCurrentFile(string $file): static
    {
        $this->currentFile = $file;
        return $this;
    }

    private function initClassAllowlist(): void
    {
        $raw = $this->policy->getBlocklist() ?? [];
        $this->classAllowlist = $this->normalizeBlocklist($raw);
    }

    private function normalizeBlocklist(array $map): array
    {
        $out = [];
        foreach ($map as $class => $methods) {
            if (!is_array($methods)) continue;
            $key = $this->normClassKey($class);
            $out[$key] = array_values(array_unique(array_map('strtolower', $methods)));
        }
        return $out;
    }

    private function normClassKey(?string $name): string
    {
        return strtolower(ltrim((string)$name, '\\'));
    }

    /**
     * Scan a raw PHP source string and return violations.
     * - Runs NameResolver (FQCN / function names)
     * - Connects parent pointers and tags parent_class
     * - Builds call graph (functions/methods) for indirect-return checks
     * - Traverses with this scanner as a visitor
     *
     * @param string $phpSource
     * @param string|null $filePath Optional file path for context in reports
     * @return array                 Flat list of violation records
     */
    public function scanSource(string $phpSource, ?string $filePath = null): array
    {
        if (property_exists($this, 'currentFile')) {
            $this->currentFile = $filePath ?? $this->currentFile ?? '[source]';
        }

        // 1) Parse
        $parser = (new ParserFactory())->createForHostVersion();
        try {
            $ast = $parser->parse($phpSource);
        } catch (Throwable $e) {
            return [[
                'type' => 'parse_error',
                'error' => $e->getMessage(),
                'file' => $filePath ?? '[source]',
                'line' => 0,
                'snippet' => '',
            ]];
        }
        if (!$ast) {
            return [];
        }

        // 2) Name resolution (fully-qualify names), then parent pointers
        $trResolve = new NodeTraverser();
        $trResolve->addVisitor(new NameResolver(options: [
            'preserveOriginalNames' => true,
            'replaceNodes' => true, // rewrite Name nodes to FullyQualified
        ]));
        $trResolve->addVisitor(new ParentConnectingVisitor());
        $ast = $trResolve->traverse($ast);

        $this->initClassAllowlist();

        // 3) Tag every node with its enclosing class ("parent_class") for easy lookups
        $trClassTag = new NodeTraverser();
        $trClassTag->addVisitor(new class extends NodeVisitorAbstract {
            private ?Node\Stmt\Class_ $current = null;

            public function enterNode(Node $node): void
            {
                if ($node instanceof Node\Stmt\Class_) {
                    $this->current = $node;
                }
                if ($this->current && $node !== $this->current) {
                    $node->setAttribute('parent_class', $this->current);
                }
            }

            public function leaveNode(Node $node): void
            {
                if ($node instanceof Node\Stmt\Class_) {
                    $this->current = null;
                }
            }
        });
        $ast = $trClassTag->traverse($ast);

        // 4) Build (or reuse) the call graph index for indirect-return checks
        if (!property_exists($this, 'callGraphAnalyzer')) {
            // assumes $this->policy (PluginPolicy) exists on the scanner
            $this->callGraphAnalyzer = new CallGraphAnalyzer($this->policy);
        }
        $this->callGraphAnalyzer->collect([$ast]);

        // 5) Security scan — treat this scanner as a NodeVisitor
        $trScan = new NodeTraverser();
        $trScan->addVisitor($this); // $this must extend NodeVisitorAbstract
        $trScan->traverse($ast);

        // 6) Return flat list of matches (whatever your scanner accumulates)
        return method_exists($this, 'getMatches') ? $this->getMatches() : [];
    }

    /**
     * Parity helper: read a file and delegate to scanSource().
     */
    public function scanFile(string $filePath): array
    {
        $code = @file_get_contents($filePath);
        if ($code === false) {
            return [[
                'type' => 'read_error',
                'file' => $filePath,
                'line' => 0,
                'snippet' => '',
                'issue' => 'Unable to read file',
            ]];
        }

        return $this->scanSource($code, $filePath);
    }

    public function getFileErrors(): array
    {
        $errors = $this->matches;
        $this->matches = [];
        return $errors;
    }

    // Pass in alias map after first pass (see below)
    public function setAliases(array $aliases): void
    {
        $this->aliases = $aliases;
    }

    /**
     * Track simple variable assignments for:
     *  - string/concat/superglobal values (existing behavior → $this->variableValues)
     *  - instance types from `new \Fqcn(...)` and simple `$b = $a` propagation (→ $this->variableTypes)
     */
    /**
     * Track simple variable assignments for:
     *  - string/concat/superglobal values (→ $this->variableValues)
     *  - class literals via ::class and dynamic-new resolution (→ $this->classNameVars, $this->variableTypes)
     *  - instance types from `new \Fqcn(...)` and simple `$b = $a` propagation (→ $this->variableTypes)
     */
    public function trackAssignments($node): void
    {
        // $x = ...   or   $x =& ...
        if (($node instanceof Assign || $node instanceof AssignRef)
            && $node->var instanceof Variable
            && is_string($node->var->name)) {

            $varName = $node->var->name;
            $expr = $node->expr; // same for AssignRef

            // Reset stale info unless set below
            unset($this->variableValues[$varName], $this->variableTypes[$varName], $this->classNameVars[$varName]);

            // ── value tracking (strings / concat / superglobals)
            if ($expr instanceof String_) {
                $this->variableValues[$varName] = $expr->value;
                return;
            }

            if ($expr instanceof Node\Expr\BinaryOp\Concat) {
                $this->variableValues[$varName] = $this->stringifyDynamic($expr);
                return;
            }

            if ($expr instanceof Node\Expr\ArrayDimFetch
                && $expr->var instanceof Variable
                && is_string($expr->var->name)
                && in_array($expr->var->name, $this->superglobals, true)) {
                $this->variableValues[$varName] = '{superglobal}';
                return;
            }

            // ── class literal: $class = A::class; (imported or FQCN)
            if ($expr instanceof Node\Expr\ClassConstFetch
                && $expr->name instanceof Identifier
                && strtolower($expr->name->toString()) === 'class') {

                $fq = null;
                if ($expr->class instanceof Name) {
                    $fq = $this->fqNameOf($expr->class) ?? $expr->class->toString();
                } /** @noinspection PhpConditionAlreadyCheckedInspection */ elseif (is_string($expr->class)) {
                    $fq = $expr->class;
                }
                if ($fq) {
                    $this->classNameVars[$varName] = ltrim($fq, '\\');
                }
                return;
            }

            // ── dynamic new via class var: $obj = new $class();
            if ($expr instanceof New_
                && $expr->class instanceof Variable
                && is_string($expr->class->name)) {

                $clsVar = $expr->class->name;
                if (isset($this->classNameVars[$clsVar])) {
                    $this->variableTypes[$varName] = $this->classNameVars[$clsVar];
                    return;
                }
            }

            // ── direct instance type: $x = new \Vendor\Class(...);
            if ($expr instanceof New_) {
                $fq = $this->getClassName($expr->class); // resolver-aware in your codebase
                if ($fq) {
                    $this->variableTypes[$varName] = ltrim($fq, '\\');
                }
                return;
            }

            // ── simple propagation: $b = $a;  (carry value/type/class-literal if known)
            if ($expr instanceof Variable && is_string($expr->name)) {
                if (array_key_exists($expr->name, $this->variableValues)) {
                    $this->variableValues[$varName] = $this->variableValues[$expr->name];
                }
                if (array_key_exists($expr->name, $this->variableTypes)) {
                    $this->variableTypes[$varName] = $this->variableTypes[$expr->name];
                }
                if (array_key_exists($expr->name, $this->classNameVars)) {
                    $this->classNameVars[$varName] = $this->classNameVars[$expr->name];
                }
                return;
            }

            // Anything else → leave unset (unknown)
            return;
        }

        // unset($x) — forget tracked info
        if ($node instanceof Node\Stmt\Unset_) {
            foreach ($node->vars as $v) {
                if ($v instanceof Variable && is_string($v->name)) {
                    unset($this->variableValues[$v->name], $this->variableTypes[$v->name], $this->classNameVars[$v->name]);
                }
            }
        }
    }

    /**
     * @throws JsonException
     */
    public function enterNode(Node $node): void
    {
        $this->trackAssignments($node);

        // -- 1. ALWAYS FORBIDDEN CHECKS --
        // A. Functions
        if ($node instanceof Node\Expr\FuncCall) {
            $fname = $node->name instanceof Node\Name ? strtolower($this->fqNameOf($node->name) ?? '') : null;

            if ($fname && $this->policy->isForbiddenFunction($fname)) {
                $this->report('always_forbidden_function', ['function' => $fname], $node);
            }

            // Wrapper stream usage in file ops
            if (in_array($fname, ['fopen', 'file_get_contents', 'file_put_contents', 'file', 'readfile'], true) && !empty($node->args[0])) {
                $arg = $node->args[0]->value;
                if ($arg instanceof String_) {
                    $path = $arg->value;
                    foreach ($this->policy->getForbiddenWrappers() as $prefix) {
                        if (stripos($path, $prefix) === 0) {
                            $this->report('always_forbidden_wrapper_stream', [
                                'function' => $fname, 'value' => $path
                            ], $node);
                        }
                    }
                }
            }
        }

        if ($node instanceof Eval_) {
            $this->report(
                'always_forbidden_function',
                ['function' => 'eval'],
                $node,
                'critical'
            );
        }

        // B. Reflection classes (instantiation, static, instanceof, type hint)
        if (
            ($node instanceof New_ && $this->isReflectionClass($node->class)) ||
            ($node instanceof StaticCall && $this->isReflectionClass($node->class)) ||
            ($node instanceof Node\Expr\Instanceof_ && $this->isReflectionClass($node->class)) ||
            ($node instanceof Node\Param && $node->type && $this->isReflectionClass($node->type))
        ) {
            $class = $this->getClassName($node->class ?? $node->type);
            $this->report('always_forbidden_reflection', ['class' => $class], $node);
        }

        // C. Forbidden magic method definitions
        if ($node instanceof Node\Stmt\ClassMethod) {
            $mname = strtolower($node->name->toString());
            if (in_array($mname, $this->policy->getForbiddenMagicMethods(), true)) {
                $this->report('always_forbidden_magic_method', ['method' => $node->name->toString()], $node);
            }
        }

        // D. Dynamic includes/requires
        if ($node instanceof Node\Expr\Include_) {
            if (!($node->expr instanceof String_)) {
                $this->report('always_forbidden_dynamic_include', [
                    'expr_type' => get_class($node->expr)
                ], $node);
            } else {
                $path = $node->expr->value;
                foreach ($this->policy->getForbiddenWrappers() as $prefix) {
                    if (stripos($path, $prefix) === 0) {
                        $this->report('always_forbidden_wrapper_stream_include', [
                            'value' => $path
                        ], $node);
                    }
                }
            }
        }

        // E. Callback/handler registration with forbidden function
        if ($node instanceof Node\Expr\FuncCall) {
            $regName = $node->name instanceof Node\Name ? strtolower($this->fqNameOf($node->name) ?? '') : null;
            if (in_array($regName, [
                    'register_shutdown_function',
                    'set_error_handler',
                    'set_exception_handler',
                    'register_tick_function'
                ], true) && !empty($node->args[0])) {
                $cb = $node->args[0]->value;
                if ($cb instanceof String_) {
                    $cbFunc = strtolower($cb->value);
                    if (in_array($cbFunc, $this->policy->getForbiddenFunctions(), true)) {
                        $this->report('always_forbidden_callback_to_forbidden_function', [
                            'registration' => $regName, 'callback' => $cbFunc
                        ], $node);
                    }
                }
            }
        }

        // F. Obfuscated eval (eval(obfuscator(...)))
        if ($node instanceof Eval_) {
            $payload = $node->expr;

            // 1) Single-level: eval(obfuscator(...))
            if ($payload instanceof Node\Expr\FuncCall && $payload->name instanceof Node\Name) {
                $inner = strtolower($this->fqNameOf($payload->name) ?? '');
                if ($inner && in_array($inner, $this->policy->getObfuscators(), true)) {
                    $this->report('always_forbidden_obfuscated_eval', [
                        'outer' => 'eval',
                        'inner' => $inner
                    ], $node, 'critical');
                }
            }

            // 2) Nested chains: eval(gzinflate(base64_decode(...)))
            $chain = $this->callGraphAnalyzer->collectFuncCallChain($payload); // ['gzinflate','base64_decode', ...]
            if ($chain && count($chain) > 1 && array_intersect($chain, $this->policy->getObfuscators())) {
                $this->report('always_forbidden_obfuscated_eval', [
                    'outer' => 'eval',
                    'chain' => $chain
                ], $node, 'critical');
            }
        }

        // -- 2. CONFIGURABLE DANGEROUS/POLICY CHECKS --
        // A. Dangerous/risky functions (from config overlays)
        if ($node instanceof Node\Expr\FuncCall) {
            $fname = $node->name instanceof Node\Name ? strtolower($this->fqNameOf($node->name) ?? '') : null;

            if ($fname) {
                $cfgDanger = array_map('strtolower', $this->config['dangerous_functions'] ?? []);
                $cfgTokens = array_map('strtolower', $this->config['tokens'] ?? []);

                if (in_array($fname, $cfgDanger, true)) {
                    $this->report('config_dangerous_function', ['function' => $fname], $node);
                }
                if (in_array($fname, $cfgTokens, true)) {
                    $this->report('config_risky_function', ['function' => $fname], $node);
                }
            }
        }

        // B. Class/method blocklist (effective allowlist)
        if ($node instanceof StaticCall) {
            $class = $this->getClassName($node->class); // keep: already upgraded
            $method = $node->name instanceof Identifier ? strtolower($node->name->toString()) : null;

            if ($class && $method) {
                $blocklist = $this->policy->getBlocklist(); // merged with overrides
                if (isset($blocklist[$class])) {
                    $allowed = $blocklist[$class];
                    if (!in_array('*', $allowed, true) && !in_array($method, $allowed, true)) {
                        $this->report('config_blocked_method', [
                            'class' => $class, 'method' => $method
                        ], $node);
                    }
                }
            }
        }

        // C. Warn on large files (scan_size)
        if (isset($this->config['scan_size']) && $this->currentFile) {
            $ext = strtolower(pathinfo($this->currentFile, PATHINFO_EXTENSION));
            if (isset($this->config['scan_size'][$ext])) {
                $max = (int)$this->config['scan_size'][$ext];
                $size = @filesize($this->currentFile);
                if ($size !== false && $size > $max) {
                    $this->report('config_file_too_large', [
                        'file' => $this->currentFile, 'max_bytes' => $max
                    ], $node);
                }
            }
        }

        // -- 3. ADVANCED BACKDOOR/HEURISTIC CHECKS (SAMPLE) --
        $this->runBlocklist($node);
        $this->runNamespaceCheck($node);
        $this->advancedBackdoorDetection($node);
    }

    // Helper: check if class is Reflection*

    /** Return fully-qualified class-like names from a type-ish node. */
    private function extractTypeNames(Name|Identifier|NullableType|UnionType|IntersectionType|Node|string|null $node): array
    {
        // Fully-qualified or resolved simple names
        if ($node instanceof Name || $node instanceof Identifier) {
            $n = $this->fqNameOf($node);
            return $n ? [ltrim($n, '\\')] : [];
        }

        // ?T
        if ($node instanceof NullableType) {
            return $this->extractTypeNames($node->type);
        }

        // T1|T2
        if ($node instanceof UnionType) {
            $out = [];
            foreach ($node->types as $t) {
                foreach ($this->extractTypeNames($t) as $n) {
                    $out[] = $n;
                }
            }
            return array_values(array_unique($out));
        }

        if ($node instanceof IntersectionType) {
            $out = [];
            foreach ($node->types as $t) {
                foreach ($this->extractTypeNames($t) as $n) {
                    $out[] = $n;
                }
            }
            return array_values(array_unique($out));
        }

        // plain string (rare here)
        if (is_string($node)) {
            return [ltrim($node, '\\')];
        }

        // Anything dynamic (Variable/Expr/etc.) → we can’t resolve safely
        return [];
    }

    /** True if any resolved name is a Reflection* class (policy-driven). */
    private function isReflectionClass(Name|Identifier|NullableType|UnionType|IntersectionType|Node|string|null $node): bool
    {
        foreach ($this->extractTypeNames($node) as $fqcn) {
            if ($this->policy->isForbiddenReflection($fqcn)) {
                return true;
            }
        }
        return false;
    }

    /** Safe, best-effort single class-like name (for reporting). */
    private function safeClassLikeName(Name|Identifier|NullableType|UnionType|IntersectionType|Node|string|null $node): ?string
    {
        $names = $this->extractTypeNames($node);
        return $names[0] ?? null;
    }

    // Helper: get class name from node/identifier/string
    private function getClassName($classNode): ?string
    {
        return $this->fqNameOf($classNode);
    }

    // Resolve class using aliases map
    private function resolveClassName($classNode)
    {
        $class = $this->getClassName($classNode);
        if ($class && isset($this->aliases[$class])) return $this->aliases[$class];
        return $class;
    }

    private function stringifyConcat($expr): string
    {
        // Recursively flatten simple string concat
        if ($expr instanceof Node\Expr\BinaryOp\Concat) {
            return $this->stringifyConcat($expr->left) . $this->stringifyConcat($expr->right);
        }
        if ($expr instanceof String_) {
            return $expr->value;
        }
        return '{dynamic}';
    }

    // Report a finding
    private function report($type, $data, $node, $severity = 'high'): void
    {
        $this->matches[] = array_merge([
            'type' => $type,
            'severity' => $severity,
            'line' => $node->getLine(),
            'file' => $this->currentFile,
        ], $data);
    }

    public function getMatches(): array
    {
        return $this->matches;
    }

    protected function runBlocklist($node): void
    {
        // Static calls: \Vendor\Class::method()
        if ($node instanceof StaticCall && $node->name instanceof Identifier) {
            $class = $this->getClassName($node->class); // resolver-aware in your code
            $meth = strtolower($node->name->toString());
            $this->enforceClassAllowlist($class, $meth, $node, true);
        }

        // Instance calls: $this->method()
        if ($node instanceof MethodCall && $node->name instanceof Identifier) {
            $meth = strtolower($node->name->toString());

            // $this->method() → enclosing class
            if ($node->var instanceof Variable && $node->var->name === 'this') {
                $class = $this->enclosingClassName($node);
                $this->enforceClassAllowlist($class, $meth, $node, false);
            }

            // $x->method() → if we know $x is an instance of FQCN
            if ($node->var instanceof Variable && is_string($node->var->name)) {
                $fq = $this->variableTypes[$node->var->name] ?? null;
                if ($fq) {
                    $this->enforceClassAllowlist($fq, $meth, $node, false);
                }
            }
        }

        // Nullsafe calls: $x?->method()
        if ($node instanceof NullsafeMethodCall && $node->name instanceof Identifier) {
            $meth = strtolower($node->name->toString());
            if ($node->var instanceof Variable && is_string($node->var->name)) {
                $fq = $this->variableTypes[$node->var->name] ?? null;
                if ($fq) {
                    $this->enforceClassAllowlist($fq, $meth, $node, false);
                }
            }
        }
    }

    private function enforceClassAllowlist(?string $class, string $method, Node $node, bool $isStatic): void
    {
        if (!$class) return;

        $fqcn = ltrim($class, '\\');
        $key = $this->normClassKey($fqcn);

        // Only enforce for classes present in the policy map
        if (!array_key_exists($key, $this->classAllowlist)) return;

        $allowed = $this->classAllowlist[$key];

        // Semantics: if a class is listed, ONLY these methods are allowed.
        // An empty array => no methods allowed.
        if (!in_array($method, $allowed, true)) {
            $this->report(
                'config_blocked_method',
                ['class' => $fqcn, 'method' => $method, 'call' => $isStatic ? 'static' : 'instance'],
                $node,
                'critical'
            );
        }
    }

    protected function runForbiddenFuncCall($node, $checkReturns = false): void
    {
        $calledName = null;

        // direct eval
        if ($node instanceof Eval_) {
            $this->report('return_forbidden_function', ['function' => 'eval'], $node, 'critical');
            return;
        }

        // plain function call: foo()
        if ($node instanceof Node\Expr\FuncCall) {
            $calledName = $node->name instanceof Node\Name ? strtolower($this->fqNameOf($node->name) ?? '') : null;

            if ($calledName && ($this->policy->isForbiddenFunction($calledName) || $this->policy->isUnsupportedFunction($calledName))) {
                $this->report('return_forbidden_function', ['function' => $calledName], $node, 'critical');
            }

            if ($checkReturns && $calledName && isset($this->callGraphAnalyzer) &&
                $this->callGraphAnalyzer->hasForbiddenReturnChain($calledName)) {
                $this->report('return_indirect_forbidden_chain', ['chain' => $calledName], $node, 'critical');
            }

            return; // handled
        }

        if (!$checkReturns || !isset($this->callGraphAnalyzer)) {
            return;
        }

        // static method call: ClassName::method()
        if ($node instanceof StaticCall && $node->name instanceof Identifier) {
            $class = $this->getClassName($node->class); // already resolver-aware in your codebase
            $method = strtolower($node->name->toString());

            if ($class && $method &&
                $this->callGraphAnalyzer->hasForbiddenMethodReturnChain($class, $method)) {
                $this->report('return_indirect_forbidden_chain', [
                    'chain' => $class . '::' . $method
                ], $node, 'critical');
            }
            return;
        }

        // instance method call on $this: $this->method()
        if ($node instanceof MethodCall && $node->name instanceof Identifier && $node->var instanceof Variable && $node->var->name === 'this') {
            $classNode = $node->getAttribute('parent_class'); // set in scanSource() prepass
            $className = null;
            if ($classNode instanceof Node\Stmt\Class_) {
                // prefer namespacedName from NameResolver; fallback to local name
                $className = isset($classNode->namespacedName)
                    ? ltrim($classNode->namespacedName->toString(), '\\')
                    : ($classNode->name?->toString());
            }

            $method = strtolower($node->name->toString());

            if ($className && $method &&
                $this->callGraphAnalyzer->hasForbiddenMethodReturnChain($className, $method)) {
                $this->report('return_indirect_forbidden_chain', [
                    'chain' => $className . '::' . $method
                ], $node, 'critical');
            }
        }
    }

    protected function runNamespaceCheck(Node $node): void
    {
        // Helper: normalized check against policy (after NameResolver)
        $isForbidden = function (?string $ns): bool {
            if (!$ns) return false;
            $ns = ltrim($ns, '\\');
            return $ns !== '' && $this->policy->isForbiddenNamespace($ns);
        };

        // 1) use Foo\Bar;  use function Foo\bar;  use const Foo\BAR;
        if ($node instanceof Node\Stmt\Use_) {
            foreach ($node->uses as $use) {
                // Note: NameResolver does not set resolvedName for use-imports; build from token.
                $full = ltrim($this->fqNameOf($use->name) ?? $use->name->toString(), '\\');

                $kind = match ($use->type) {
                    Node\Stmt\Use_::TYPE_FUNCTION => 'function',
                    Node\Stmt\Use_::TYPE_CONSTANT => 'const',
                    default => 'class',
                };

                if ($isForbidden($full)) {
                    $this->report(
                        'forbidden_namespace_import' . ($kind !== 'class' ? "_$kind" : ''),
                        ['namespace' => $full, 'kind' => $kind],
                        $node,
                        'critical'
                    );
                }
            }
            return;
        }

        // 1b) use Prefix\{A, B as C, function f, const X};
        if ($node instanceof Node\Stmt\GroupUse) {
            $prefix = $this->fqNameOf($node->prefix) ?? $node->prefix->toString();
            $prefix = rtrim($prefix, '\\');

            foreach ($node->uses as $use) {
                // Each leaf can optionally carry its own type; fall back to group type.
                $type = ($use->type !== 0) ? $use->type : $node->type;
                $kind = match ($type) {
                    Node\Stmt\Use_::TYPE_FUNCTION => 'function',
                    Node\Stmt\Use_::TYPE_CONSTANT => 'const',
                    default => 'class',
                };

                $leaf = $use->name->toString();              // e.g. "DB" or "Route"
                $full = ltrim($prefix . '\\' . $leaf, '\\'); // Prefix\Leaf

                if ($isForbidden($full)) {
                    $this->report(
                        'forbidden_namespace_import' . ($kind !== 'class' ? "_$kind" : ''),
                        ['namespace' => $full, 'kind' => $kind],
                        $node,
                        'critical'
                    );
                }
            }
            return;
        }

        // 1c) Trait imports inside classes: use Some\TraitName;
        if ($node instanceof Node\Stmt\TraitUse) {
            foreach ($node->traits as $t) {
                $full = ltrim($this->fqNameOf($t) ?? $t->toString(), '\\');
                if ($isForbidden($full)) {
                    $this->report(
                        'forbidden_namespace_trait_use',
                        ['namespace' => $full],
                        $node,
                        'critical'
                    );
                }
            }
            // continue scanning other checks below (no early return)
        }

        // 2) References in expressions: new, static call, const fetch, instanceof
        if (
            $node instanceof New_
            || $node instanceof StaticCall
            || $node instanceof Node\Expr\ClassConstFetch
            || $node instanceof Node\Expr\Instanceof_
        ) {
            $class = $this->getClassName($node->class ?? null); // resolver-aware in your codebase
            if ($isForbidden($class)) {
                $this->report(
                    'forbidden_namespace_reference',
                    ['namespace' => $class],
                    $node,
                    'critical'
                );
            }
        }

        // 3) Class extends / implements
        if ($node instanceof Node\Stmt\Class_) {
            if ($node->extends) {
                $parent = ltrim($this->fqNameOf($node->extends) ?? $node->extends->toString(), '\\');
                if ($isForbidden($parent)) {
                    $this->report('forbidden_namespace_extends', ['namespace' => $parent], $node, 'critical');
                }
            }
            foreach ($node->implements as $impl) {
                $iface = ltrim($this->fqNameOf($impl) ?? $impl->toString(), '\\');
                if ($isForbidden($iface)) {
                    $this->report('forbidden_namespace_implements', ['namespace' => $iface], $node, 'critical');
                }
            }
        }

        // 4) String references to classes (e.g. "$c = 'GuzzleHttp\\Client';")
        if ($node instanceof String_) {
            $str = ltrim($node->value, '\\');
            if ($isForbidden($str)) {
                $this->report(
                    'forbidden_namespace_string_reference',
                    ['namespace' => $node->value],
                    $node
                );
            }
        }
    }

    /**
     * @throws JsonException
     */
    public function advancedBackdoorDetection($node): void
    {
        // Backdoor 1 - Variable/dynamic function calls
        if ($node instanceof Node\Expr\FuncCall) {
            // 1. Variable function: $func()
            if ($node->name instanceof Variable) {
                $funcVar = $node->name->name;
                $resolved = $this->resolvedVarString($funcVar);

                $reportType = 'backdoor_variable_function_call';
                $severity = 'high';
                $extra = [
                    'var' => is_string($funcVar) ? $funcVar : json_encode($funcVar, JSON_THROW_ON_ERROR)
                ];

                if ($resolved && $this->policy->isForbiddenFunction($resolved)) {
                    $severity = 'critical';
                    $reportType = 'backdoor_variable_function_call_chain_forbidden';
                    $extra['resolved_function'] = $resolved;
                } elseif ($resolved && isset($this->callGraphAnalyzer) && $this->callGraphAnalyzer->hasForbiddenReturnChain($resolved)) {
                    $severity = 'critical';
                    $reportType = 'backdoor_variable_function_call_chain_forbidden';
                    $extra['resolved_function'] = $resolved;
                }

                $this->report($reportType, $extra, $node, $severity);
            }

            // 2. Dynamic concat: ("eva"."l")()
            if ($node->name instanceof Node\Expr\BinaryOp\Concat) {
                $exprStr = strtolower($this->stringifyConcat($node->name));
                $severity = 'high';
                $type = 'backdoor_concat_function_call_unknown';

                if ($this->policy->isForbiddenFunction($exprStr)) {
                    $severity = 'critical';
                    $type = 'backdoor_concat_function_call_always_forbidden';
                } elseif ($this->policy->isUnsupportedFunction($exprStr)) {
                    $type = 'backdoor_concat_function_call_unsupported';
                } elseif (isset($this->callGraphAnalyzer) && $this->callGraphAnalyzer->hasForbiddenReturnChain($exprStr)) {
                    $severity = 'critical';
                    $type = 'backdoor_concat_function_call_chain_forbidden';
                }

                $this->report($type, ['expression' => $exprStr], $node, $severity);
            }

            // 3. Direct call by resolved name
            if ($node->name instanceof Node\Name) {
                $name = strtolower($this->fqNameOf($node->name) ?? $node->name->toString());
                if ($this->policy->isForbiddenFunction($name)) {
                    $this->report('always_forbidden_function', ['function' => $name], $node, 'critical');
                } elseif ($this->policy->isUnsupportedFunction($name)) {
                    $this->report('unsupported_function', ['function' => $name], $node);
                } elseif (isset($this->callGraphAnalyzer) && $this->callGraphAnalyzer->hasForbiddenReturnChain($name)) {
                    $this->report('function_call_chain_forbidden', ['function' => $name], $node, 'critical');
                }
            }
        }

        // Backdoor 2 - Closures & callbacks
        if ($node instanceof Node\Expr\FuncCall) {
            $callbackFunctions = array_map('strtolower', $this->policy->getCallbackFunctions());
            $name = $node->name instanceof Node\Name ? strtolower($this->fqNameOf($node->name) ?? $node->name->toString()) : null;

            if ($name && in_array($name, $callbackFunctions, true) && !empty($node->args[0])) {
                $cb = $node->args[0]->value;

                if ($cb instanceof String_) {
                    $cbFunc = strtolower($cb->value);
                    if ($this->policy->isForbiddenFunction($cbFunc)) {
                        $this->report('callback_always_forbidden', [
                            'function' => $name, 'callback' => $cbFunc
                        ], $node, 'critical');
                    } elseif ($this->policy->isUnsupportedFunction($cbFunc)) {
                        $this->report('callback_unsupported', [
                            'function' => $name, 'callback' => $cbFunc
                        ], $node);
                    } elseif (isset($this->callGraphAnalyzer) && $this->callGraphAnalyzer->hasForbiddenReturnChain($cbFunc)) {
                        $this->report('callback_user_defined_forbidden_chain', [
                            'function' => $name,
                            'callback_chain' => $cbFunc
                        ], $node, 'critical');
                    }
                } elseif ($cb instanceof Node\Expr\Closure || $cb instanceof Node\Expr\ArrowFunction) {
                    $danger = false;
                    foreach ($cb->getStmts() as $stmt) {
                        $danger = $this->closureScan($stmt);
                        if ($danger) break;
                    }
                    if ($danger) {
                        $this->report('callback_closure_forbidden', [
                            'function' => $name,
                            'closure_dangerous' => true
                        ], $node, 'critical');
                    }
                }
            }
        }

        // Backdoor 3 - Dynamic class instantiation
        if ($node instanceof New_) {
            if ($node->class instanceof Variable) {
                $classVar = $node->class->name;
                $resolved = is_string($classVar) ? ($this->variableValues[$classVar] ?? null) : null;

                if ($resolved === '{superglobal}') {
                    $this->report('backdoor_dynamic_class_instantiation_superglobal', [
                        'expression' => '$' . (is_string($classVar) ? $classVar : '{dynamic}'),
                    ], $node, 'critical');
                } elseif ($resolved) {
                    if ($this->policy->isForbiddenReflection($resolved) || $this->policy->isForbiddenNamespace(ltrim($resolved, '\\'))) {
                        $this->report('backdoor_dynamic_class_instantiation_forbidden', [
                            'resolved_class' => $resolved,
                            'expression' => '$' . (is_string($classVar) ? $classVar : '{dynamic}'),
                        ], $node, 'critical');
                    }
                } else {
                    $this->report('backdoor_dynamic_class_instantiation_unresolved', [
                        'expression' => '$' . (is_string($classVar) ? $classVar : '{dynamic}'),
                    ], $node);
                }
            } elseif (!($node->class instanceof Node\Name)) {
                $classStr = $this->stringifyDynamic($node->class);
                $this->report('backdoor_dynamic_class_instantiation_complex', [
                    'expression' => $classStr,
                ], $node);
            }
        }

        // Backdoor 4 & 11 (unified) - Dynamic member access (method/property)
        if ($node instanceof MethodCall && $node->name instanceof Variable) {
            $methodVar = $node->name->name;
            $resolved = $this->resolvedVarString($methodVar);
            $className = $this->enclosingClassName($node);

            $this->handleDynamicMember('method', $className, $resolved, '$' . (is_string($methodVar) ? $methodVar : '{dynamic}'), $node);
        } elseif ($node instanceof MethodCall && !($node->name instanceof Identifier)) {
            $methodStr = $this->stringifyDynamic($node->name);
            $this->report('backdoor_dynamic_method_call_complex', ['expression' => $methodStr], $node);
        }

        if ($node instanceof Node\Expr\PropertyFetch && $node->name instanceof Variable) {
            $propVar = $node->name->name;
            $resolved = $this->resolvedVarString($propVar);
            $className = $this->enclosingClassName($node);

            $this->handleDynamicMember('property', $className, $resolved, '$obj->$' . (is_string($propVar) ? $propVar : '{dynamic}'), $node);
        }

        if ($node instanceof Node\Expr\StaticPropertyFetch && $node->name instanceof Variable) {
            $var = $node->name->name;
            $this->report('dynamic_static_property_access', [
                'expression' => '::$' . (is_string($var) ? $var : '{dynamic}')
            ], $node);
        }

        if ($node instanceof Variable && is_object($node->name)) {
            $this->report('variable_variable_usage', [
                'expression' => '$$' . $this->stringifyDynamic($node->name)
            ], $node);
        }

        // Backdoor 5 - Forbidden magic methods (scan body)
        if ($node instanceof Node\Stmt\ClassMethod) {
            $name = strtolower($node->name->toString());
            if (in_array($name, $this->policy->getForbiddenMagicMethods(), true)) {
                $dangerInfo = $this->scanMagicMethodBody($node);
                $this->report('magic_method_defined', [
                    'method' => $name,
                    'dangerous_content' => $dangerInfo['danger'],
                    'explanation' => $dangerInfo['explanation']
                ], $node, $dangerInfo['severity']);
            }
        }

        // Backdoor 6 - Reflection usage
        if (
            ($node instanceof New_ && $this->policy->isForbiddenReflection($this->getClassName($node->class))) ||
            ($node instanceof StaticCall && $this->policy->isForbiddenReflection($this->getClassName($node->class))) ||
            ($node instanceof Node\Expr\Instanceof_ && $this->policy->isForbiddenReflection($this->getClassName($node->class))) ||
            ($node instanceof Node\Param && $node->type && $this->policy->isForbiddenReflection($this->getClassName($node->type)))
        ) {
            $class = $this->getClassName($node->class ?? $node->type) ?? '{dynamic}';
            $this->report('reflection_usage', ['class' => $class], $node, 'critical');
        }

        // Backdoor 7 - File includes
        if ($node instanceof Node\Expr\Include_) {
            if ($node->expr instanceof String_) {
                $path = $node->expr->value;
                foreach ($this->policy->getForbiddenWrappers() as $prefix) {
                    if (stripos($path, $prefix) === 0) {
                        $this->report('include_forbidden_wrapper', ['value' => $path], $node, 'critical');
                    }
                }
            } else {
                $exprString = $this->stringifyDynamic($node->expr);
                $resolved = null;
                $varName = null;
                if ($node->expr instanceof Variable && is_string($node->expr->name)) {
                    $varName = $node->expr->name;
                    $resolved = $this->variableValues[$varName] ?? null;
                }

                if ($resolved === '{superglobal}') {
                    $this->report('include_dynamic_path_superglobal', [
                        'expression' => $varName ? ('$' . $varName) : '{dynamic}'
                    ], $node, 'critical');
                } else {
                    $this->report('include_dynamic_path', ['expression' => $exprString], $node);
                }
            }
        }

        // Backdoor 8 - Obfuscators
        if ($node instanceof Node\Expr\FuncCall) {
            $name = $node->name instanceof Node\Name ? strtolower($this->fqNameOf($node->name) ?? $node->name->toString()) : null;
            if ($name && in_array($name, $this->policy->getObfuscators(), true)) {
                $this->report('obfuscation_function', ['function' => $name], $node);
            }
        }

        // Backdoor 9 - Anonymous class / closure leakage
        if ($node instanceof New_ && $node->class instanceof Class_) {
            $danger = $this->scanAnonymousClass($node->class);
            $this->report('anonymous_class_leak', ['dangerous_content' => $danger], $node, $danger ? 'critical' : 'info');
        }
        if ($node instanceof Node\Expr\Closure || $node instanceof Node\Expr\ArrowFunction) {
            $danger = $this->scanClosureBody($node);
            $this->report('anonymous_function_leak', ['dangerous_content' => $danger], $node, $danger ? 'critical' : 'info');
        }

        // Backdoor 10 - Assignments to superglobals
        if ($node instanceof Node\Expr\Assign
            && $node->var instanceof Node\Expr\ArrayDimFetch
            && $node->var->var instanceof Variable) {

            $arrayName = $node->var->var->name;
            if (in_array($arrayName, ['GLOBALS', '_SESSION', '_ENV', '_SERVER'], true)) {
                $danger = $this->containsDangerousValue($node->expr);
                $this->report('global_or_session_leak', [
                    'array' => '$' . $arrayName,
                    'dangerous_content' => $danger,
                ], $node, $danger ? 'critical' : 'high');
            }
        }

        if ($node instanceof Node\Stmt\Static_) {
            foreach ($node->vars as $staticVar) {
                $danger = $this->containsDangerousValue($staticVar->default);
                $this->report('static_variable_leak', [
                    'var' => $staticVar->var->name,
                    'dangerous_content' => $danger,
                ], $node, $danger ? 'critical' : 'high');
            }
        }

        // Backdoor 12 - Chained/indirect returns
        if ($node instanceof Node\Stmt\Return_) {
            $expr = $node->expr;

            // Direct: return new ForbiddenClass();
            if ($expr instanceof New_) {
                $class = $this->getClassName($expr->class);
                if ($class && ($this->policy->isForbiddenReflection($class) || $this->policy->isForbiddenNamespace($class))) {
                    $this->report('return_forbidden_class', ['class' => $class], $node, 'critical');
                }
            }

            // Direct/indirect via function
            $this->runForbiddenFuncCall($expr, true);

            // Indirect: return $this->method()
            if ($expr instanceof MethodCall && $expr->name instanceof Identifier) {
                $className = $this->enclosingClassName($node);
                $methName = strtolower($expr->name->toString());
                if ($className && isset($this->callGraphAnalyzer) &&
                    $this->callGraphAnalyzer->hasForbiddenMethodReturnChain($className, $methName)) {
                    $this->report('return_indirect_forbidden_method_chain', [
                        'chain' => $className . '::' . $methName
                    ], $node, 'critical');
                }
            }

            // Indirect: return SomeClass::method()
            if ($expr instanceof StaticCall && $expr->name instanceof Identifier) {
                $className = $this->getClassName($expr->class);
                $methName = strtolower($expr->name->toString());
                if ($className && isset($this->callGraphAnalyzer) &&
                    $this->callGraphAnalyzer->hasForbiddenMethodReturnChain($className, $methName)) {
                    $this->report('return_indirect_forbidden_method_chain', [
                        'chain' => $className . '::' . $methName
                    ], $node, 'critical');
                }
            }
        }
    }

    /* ====================== helpers to DRY Backdoor 4 & 11 ====================== */

    private function enclosingClassName(Node $node): ?string
    {
        $classNode = $node->getAttribute('parent_class');
        if ($classNode instanceof Node\Stmt\Class_) {
            return isset($classNode->namespacedName)
                ? ltrim($classNode->namespacedName->toString(), '\\')
                : ($classNode->name?->toString());
        }
        return null;
    }

    /** Return strtolower($this->variableValues[$name]) or null, safely. */
    private function resolvedVarString($name): ?string
    {
        if (!is_string($name)) return null;
        $v = $this->variableValues[$name] ?? null;
        if (!is_string($v)) return null;
        $v = strtolower($v);
        return $v !== '' ? $v : null;
    }

    /**
     * Unified handler for dynamic member access.
     * $kind: 'method'|'property'
     */
    private function handleDynamicMember(string $kind, ?string $className, ?string $resolved, string $exprLabel, Node $node): void
    {
        if ($resolved === '{superglobal}') {
            $this->report(
                $kind === 'method' ? 'backdoor_dynamic_method_call_superglobal' : 'dynamic_property_access_superglobal',
                ['expression' => $exprLabel],
                $node,
                'critical'
            );
            return;
        }

        if ($resolved === null) {
            $this->report(
                $kind === 'method' ? 'backdoor_dynamic_method_call_unresolved' : 'dynamic_property_access',
                ['expression' => $exprLabel],
                $node
            );
            return;
        }

        if ($kind === 'method') {
            if ($this->policy->isForbiddenFunction($resolved) || $this->policy->isUnsupportedFunction($resolved)) {
                $this->report('backdoor_dynamic_method_call_forbidden', [
                    'resolved_method' => $resolved,
                    'expression' => $exprLabel,
                ], $node, 'critical');
                return;
            }
            if ($className && isset($this->callGraphAnalyzer) &&
                $this->callGraphAnalyzer->hasForbiddenMethodReturnChain($className, $resolved)) {
                $this->report('backdoor_dynamic_method_call_chain_forbidden', [
                    'class' => $className,
                    'resolved_method' => $resolved,
                    'expression' => $exprLabel,
                ], $node, 'critical');
                return;
            }

            // else: benign/suspicious dynamic call, no report needed beyond the general one already emitted
            return;
        }

        // property kind — treat resolved property as potential method reference
        if ($className && isset($this->callGraphAnalyzer)) {
            $defs = $this->callGraphAnalyzer->getMethodDefs($className);
            if (isset($defs[strtolower($resolved)]) &&
                $this->callGraphAnalyzer->hasForbiddenMethodReturnChain($className, $resolved)) {
                $this->report('dynamic_property_access_chain_forbidden', [
                    'class' => $className,
                    'resolved_property' => $resolved,
                    'expression' => $exprLabel,
                ], $node, 'critical');
                return;
            }
        }

        // Default informational report for dynamic property access
        $this->report('dynamic_property_access', ['expression' => $exprLabel], $node);
    }

    // Helper to scan closure body for forbidden/unsupported function calls
    private function closureScan(Node $node): bool
    {
        // Direct forbidden/unsupported function call
        if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name) {
            $name = strtolower($this->fqNameOf($node->name) ?? $node->name->toString());
            if ($this->policy->isForbiddenFunction($name)) {
                $this->report('closure_calls_always_forbidden', ['function' => $name], $node, 'critical');
                return true;
            }
            if ($this->policy->isUnsupportedFunction($name)) {
                $this->report('closure_calls_unsupported', ['function' => $name], $node);
                return true;
            }
            // Analyzer: user-defined function returns forbidden
            if (isset($this->callGraphAnalyzer) && $this->callGraphAnalyzer->hasForbiddenReturnChain($name)) {
                $this->report('closure_calls_forbidden_chain', ['function' => $name], $node, 'critical');
                return true;
            }
        }

        // Recursively scan nested nodes
        foreach ($node->getSubNodeNames() as $sub) {
            $child = $node->$sub;
            if ($child instanceof Node) {
                if ($this->closureScan($child)) return true;
            } elseif (is_array($child)) {
                foreach ($child as $c) {
                    if ($c instanceof Node && $this->closureScan($c)) return true;
                }
            }
        }
        return false;
    }

    private function stringifyDynamic($expr): string
    {
        if ($expr instanceof Variable) {
            return '$' . (is_string($expr->name) ? $expr->name : '{complex}');
        }
        if ($expr instanceof Node\Expr\BinaryOp\Concat) {
            return $this->stringifyDynamic($expr->left) . $this->stringifyDynamic($expr->right);
        }
        if ($expr instanceof String_) {
            return $expr->value;
        }
        if ($expr instanceof Node\Scalar\Encapsed) {
            $parts = [];
            foreach ($expr->parts as $p) {
                if ($p instanceof Node\Scalar\EncapsedStringPart) {
                    $parts[] = $p->value;
                } elseif ($p instanceof Variable) {
                    $parts[] = '$' . (is_string($p->name) ? $p->name : '{var}');
                } else {
                    $parts[] = '{expr}';
                }
            }
            return implode('', $parts);
        }
        return '{dynamic}';
    }

    /**
     * Scan a magic method body for dangerous patterns.
     * @return array{danger: bool, severity: string, explanation: string}
     */
    private function scanMagicMethodBody(Node\Stmt\ClassMethod $node): array
    {
        $danger = false;
        $severity = 'low';
        $explanation = '';

        foreach ($node->getStmts() ?? [] as $stmt) {
            // First pass: generic analyzer for this statement (recursive)
            $check = $this->analyzeMagicBodyStmt($stmt);
            if ($check['danger']) {
                return $check;
            }

            // Analyzer integration: $this->$name() inside magic method
            if ($stmt instanceof MethodCall
                && $stmt->var instanceof Variable
                && $stmt->var->name === 'this'
                && $stmt->name instanceof Variable) {

                $methodVar = $stmt->name->name;
                $resolved = $this->resolvedVarString($methodVar);
                $classNode = $node->getAttribute('parent_class');
                $className = null;

                if ($classNode instanceof Node\Stmt\Class_) {
                    $className = isset($classNode->namespacedName)
                        ? ltrim($classNode->namespacedName->toString(), '\\')
                        : ($classNode->name?->toString());
                }

                if ($resolved && $className && isset($this->callGraphAnalyzer) &&
                    $this->callGraphAnalyzer->hasForbiddenMethodReturnChain($className, $resolved)) {

                    return [
                        'danger' => true,
                        'severity' => 'critical',
                        'explanation' => "Dynamic call to forbidden chain ($className::$resolved) via magic method"
                    ];
                }
            }
        }

        return [
            'danger' => false,
            'severity' => $severity,
            'explanation' => "No dangerous dynamic calls"
        ];
    }

    /**
     * Recursive inspector for magic method statements.
     * @return array{danger: bool, severity: string, explanation: string}
     */
    private function analyzeMagicBodyStmt(Node $node): array
    {
        // Direct forbidden/unsupported function call
        if ($node instanceof Node\Expr\FuncCall) {
            $name = $node->name instanceof Node\Name ? strtolower($this->fqNameOf($node->name) ?? $node->name->toString()) : null;

            if ($name && ($this->policy->isForbiddenFunction($name) || $this->policy->isUnsupportedFunction($name))) {
                return ['danger' => true, 'severity' => 'critical', 'explanation' => 'Direct forbidden/unsupported function called'];
            }

            // call_user_func / call_user_func_array checks
            if (in_array($name, ['call_user_func', 'call_user_func_array'], true) && !empty($node->args[0])) {
                $cb = $node->args[0]->value;
                if ($cb instanceof String_) {
                    $cbFunc = strtolower($cb->value);
                    if ($this->policy->isForbiddenFunction($cbFunc) || $this->policy->isUnsupportedFunction($cbFunc)) {
                        return ['danger' => true, 'severity' => 'critical', 'explanation' => "call_user_func to forbidden/unsupported"];
                    }
                    if (isset($this->callGraphAnalyzer) && $this->callGraphAnalyzer->hasForbiddenReturnChain($cbFunc)) {
                        return ['danger' => true, 'severity' => 'critical', 'explanation' => "call_user_func to forbidden chain"];
                    }
                } elseif ($cb instanceof Variable) {
                    return ['danger' => true, 'severity' => 'high', 'explanation' => "call_user_func to unknown variable"];
                }
            }
        }

        // Variable/dynamic method or function name in magic method
        if (
            ($node instanceof MethodCall && !($node->name instanceof Identifier)) ||
            ($node instanceof Node\Expr\FuncCall && !($node->name instanceof Node\Name))
        ) {
            return ['danger' => true, 'severity' => 'high', 'explanation' => "Dynamic method/function call in magic method"];
        }

        // Recurse into children
        foreach ($node->getSubNodeNames() as $sub) {
            $child = $node->$sub;
            if ($child instanceof Node) {
                $check = $this->analyzeMagicBodyStmt($child);
                if ($check['danger']) return $check;
            } elseif (is_array($child)) {
                foreach ($child as $c) {
                    if ($c instanceof Node) {
                        $check = $this->analyzeMagicBodyStmt($c);
                        if ($check['danger']) return $check;
                    }
                }
            }
        }

        return ['danger' => false, 'severity' => 'low', 'explanation' => "No dangerous dynamic calls"];
    }

    private function scanAnonymousClass(Class_ $class): bool
    {
        foreach ($class->getMethods() as $method) {
            if ($this->magicMethodContainsDanger($method)) {
                return true;
            }
        }
        return false;
    }

    private function scanClosureBody($closure): bool
    {
        if (!($closure instanceof Node\Expr\Closure)) return false;
        foreach ($closure->getStmts() ?? [] as $stmt) {
            // Direct forbidden/unsupported function call, or forbidden chain
            if ($stmt instanceof Node\Expr\FuncCall && $stmt->name instanceof Node\Name) {
                $name = strtolower($this->fqNameOf($stmt->name) ?? $stmt->name->toString());
                if ($this->policy->isForbiddenFunction($name) || $this->policy->isUnsupportedFunction($name)) {
                    return true;
                }
                if (isset($this->callGraphAnalyzer) && $this->callGraphAnalyzer->hasForbiddenReturnChain($name)) {
                    return true;
                }
            }
            // Recurse
            foreach ($stmt->getSubNodeNames() as $sub) {
                $child = $stmt->$sub;
                if ($child instanceof Node) {
                    if ($this->scanClosureBody($child)) return true;
                } elseif (is_array($child)) {
                    foreach ($child as $c) {
                        if ($c instanceof Node && $this->scanClosureBody($c)) return true;
                    }
                }
            }
        }
        return false;
    }

    private function magicMethodContainsDanger(Node $node): bool
    {
        // Direct forbidden/unsupported calls
        if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name) {
            $name = strtolower($this->fqNameOf($node->name) ?? $node->name->toString());
            if ($this->policy->isForbiddenFunction($name) || $this->policy->isUnsupportedFunction($name)) {
                return true;
            }
            if (in_array($name, ['call_user_func', 'call_user_func_array'], true) && !empty($node->args[0])) {
                $cb = $node->args[0]->value;
                if ($cb instanceof String_) {
                    $cbFunc = strtolower($cb->value);
                    if ($this->policy->isForbiddenFunction($cbFunc) || $this->policy->isUnsupportedFunction($cbFunc)) {
                        return true;
                    }
                }
            }
        }

        // Variable function/method calls ($this->{$x}(), $fn(), etc.)
        if ($node instanceof MethodCall || $node instanceof Node\Expr\FuncCall) {
            if (!($node->name instanceof Identifier) && !($node->name instanceof Node\Name)) {
                return true;
            }
        }

        // Recurse
        foreach ($node->getSubNodeNames() as $sub) {
            $child = $node->$sub;
            if ($child instanceof Node && $this->magicMethodContainsDanger($child)) {
                return true;
            }
            if (is_array($child)) {
                foreach ($child as $c) {
                    if ($c instanceof Node && $this->magicMethodContainsDanger($c)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function containsDangerousValue($expr): bool
    {
        // Direct: new {Class}
        if ($expr instanceof New_) {
            $class = $this->getClassName($expr->class);
            return $class && ($this->policy->isForbiddenNamespace($class) || $this->policy->isForbiddenReflection($class));
        }

        // Direct: forbidden/unsupported function call
        if ($expr instanceof Node\Expr\FuncCall) {
            if ($expr->name instanceof Node\Name) {
                $name = strtolower($this->fqNameOf($expr->name) ?? $expr->name->toString());
                if ($this->policy->isForbiddenFunction($name) || $this->policy->isUnsupportedFunction($name)) {
                    return true;
                }
                if (isset($this->callGraphAnalyzer) && $this->callGraphAnalyzer->hasForbiddenReturnChain($name)) {
                    return true;
                }
            } else {
                // Variable/dynamic function in value context — err on safe side
                return true;
            }
        }

        // Closure/arrow fn — scan body
        if ($expr instanceof Node\Expr\Closure || $expr instanceof Node\Expr\ArrowFunction) {
            foreach ($expr->getStmts() ?? [] as $stmt) {
                if ($this->scanClosureBody($stmt)) return true;
            }
        }

        // Array literal — recurse
        if ($expr instanceof Node\Expr\Array_) {
            foreach ($expr->items as $item) {
                if ($item && $this->containsDangerousValue($item->value)) return true;
            }
        }

        // Variable — try resolve a tracked value that might be callable
        if ($expr instanceof Variable && is_string($expr->name)) {
            $resolved = $this->variableValues[$expr->name] ?? null;
            if (is_string($resolved)) {
                $resolvedLc = strtolower($resolved);
                if ($this->policy->isForbiddenFunction($resolvedLc) || $this->policy->isUnsupportedFunction($resolvedLc)) {
                    return true;
                }
                if (isset($this->callGraphAnalyzer) && $this->callGraphAnalyzer->hasForbiddenReturnChain($resolvedLc)) {
                    return true;
                }
            }
        }

        return false;
    }
}
```

---
#### 25


` File: src/Core/Security/RouteFileValidator.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Core\Security;

use JsonException;
use RuntimeException;
use Timeax\FortiPlugin\Exceptions\DuplicateRouteIdException;

final class RouteFileValidator
{
    /**
     * Validate a single route JSON file:
     * - Decode JSON
     * - (Optionally) validate with JSON Schema externally
     * - Enforce unique "id" per route node within the file
     * - Register IDs globally in $registry to ensure cross-file uniqueness
     *
     * @throws JsonException
     * @throws DuplicateRouteIdException
     */
    public static function validateFile(string $filePath, RouteIdRegistry $registry): void
    {
        $json = file_get_contents($filePath);
        if ($json === false) {
            throw new RuntimeException("Cannot read route file: $filePath");
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['routes']) || !is_array($data['routes'])) {
            throw new RuntimeException("Invalid route file (missing 'routes' array): $filePath");
        }

        // Enforce uniqueness within this file.
        $local = [];
        $walk = static function (array $node, string $path) use (&$walk, &$local, $filePath, $registry): void {
            // all route nodes must carry id/desc by schema; be defensive:
            $id = $node['id'] ?? null;
            $desc = $node['desc'] ?? null;
            if (!is_string($id) || $id === '' || !is_string($desc) || $desc === '') {
                throw new RuntimeException("Route at $filePath $path missing required 'id'/'desc'.");
            }

            // Check file-scope uniqueness
            if (isset($local[$id])) {
                $first = $local[$id];
                throw new RuntimeException(
                    "Duplicate route id '$id' within the same file.\n" .
                    " - First at: $filePath $first\n" .
                    " - Again at: $filePath $path"
                );
            }
            $local[$id] = $path;

            // Check plugin-scope uniqueness (across files)
            $registry->register($id, $filePath, $path);

            // Recurse into groups
            if (($node['type'] ?? null) === 'group') {
                $children = $node['routes'] ?? [];
                foreach ($children as $i => $child) {
                    $walk($child, $path . "/routes[$i]");
                }
            }
        };

        foreach ($data['routes'] as $i => $node) {
            $walk($node, "/routes[$i]");
        }
    }
}
```

---
#### 26


` File: src/Core/Security/RouteIdRegistry.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Core\Security;

use Timeax\FortiPlugin\Exceptions\DuplicateRouteIdException;

/**
 * Tracks route IDs to enforce uniqueness within a plugin.
 */
final class RouteIdRegistry
{
    /**
     * @var array<string, array{file:string, path:string}>
     */
    private array $seen = [];

    /**
     * @throws DuplicateRouteIdException
     */
    public function register(string $id, string $file, string $jsonPath = ''): void
    {
        $id = trim($id);
        if ($id === '') {
            return; // schema should already require non-empty; be lenient here
        }

        if (isset($this->seen[$id])) {
            $first = $this->seen[$id];
            throw new DuplicateRouteIdException(
                $id,
                $first['file'],
                $first['path'],
                $file,
                $jsonPath
            );
        }

        $this->seen[$id] = ['file' => $file, 'path' => $jsonPath];
    }
}
```

---
#### 27


` File: src/Core/Security/TokenUsageAnalyzer.php`  [↑ Back to top](#index)

```php
<?php

declare(strict_types=1);

namespace Timeax\FortiPlugin\Core\Security;

use RuntimeException;

/**
 * TokenUsageAnalyzer
 *
 * Analyzes a PHP file for direct (and lightly-obfuscated) usage of forbidden tokens
 * (e.g., eval, exec, shell_exec). Designed to be used as the callback with FileScanner.
 *
 * Detection strategy (fast and with low false positives):
 *  1) Uses token_get_all() to walk PHP tokens and capture T_STRING calls that match
 *     forbidden function names, only when followed by "(" (i.e., actual calls).
 *  2) Detects simple string-concatenation obfuscation of function names, e.g. "ev"."al"(
 *     by concatenating adjacent string literals directly before "(" and comparing.
 *  3) Flags backtick command execution (`...`) by scanning raw code segments (rare in tokenizer).
 *
 * Not a full parser; intentionally lightweight. This should be paired with your AST scanner
 * for deep analysis.
 */
final class TokenUsageAnalyzer
{
    /**
     * Scan a file and return a list of issues for direct/obfuscated token usage.
     *
     * @param  string        $filePath         Absolute path to the PHP file.
     * @param  array<string> $forbiddenTokens  Lowercase function names to flag (e.g., ['eval','exec','shell_exec'])
     * @return array<int,array{
     *     type: string,
     *     token: string,
     *     file: string,
     *     line: int,
     *     snippet: string,
     *     issue: string
     * }>
     * @noinspection ForeachInvariantsInspection
     */
    public static function analyzeFile(string $filePath, array $forbiddenTokens): array
    {
        // Normalize to lowercase set for cheap lookup
        $forbidden = [];
        foreach ($forbiddenTokens as $t) {
            $forbidden[strtolower($t)] = true;
        }

        $code = @file_get_contents($filePath);
        if ($code === false) {
            throw new RuntimeException("Unable to read file: $filePath");
        }

        $lines = preg_split('/\R/u', $code) ?: [];

        // Quick check for backticks: flag any line with non-escaped backtick outside string contexts.
        // (Tokenizer doesn’t give a special token for backticks; this heuristic is acceptable here.)
        $issues = self::scanBackticks($filePath, $lines);

        // Tokenize once
        $tokens = @token_get_all($code, TOKEN_PARSE);

        // Walk tokens and detect:
        //  A) T_STRING function calls that are forbidden and followed by '('
        //  B) String-concatenated function names immediately followed by '(' (e.g., "ev"."al"()
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $tk = $tokens[$i];

            // A) Direct function call: T_STRING '('
            if (is_array($tk) && $tk[0] === T_STRING) {
                $name = strtolower($tk[1]);

                if (isset($forbidden[$name])) {
                    $next = self::skipWhitespaceAndComments($tokens, $i + 1, $count);
                    if ($next < $count && $tokens[$next] === '(') {
                        /** @noinspection PhpConditionAlreadyCheckedInspection */
                        $line = is_array($tk) ? $tk[2] : self::safeLineGuess($lines);
                        $issues[] = self::issueRow($name, $filePath, $line, $lines, 'Direct usage of invalid token');
                    }
                }

                continue;
            }

            // B) Obfuscated via concatenated strings right before '(':
            //    e.g., "ev" . "al" ( ... )
            // Collect a run of [string-literal] (dot [string-literal])* immediately followed by '('
            if (self::isStringLiteral($tk)) {
                [$assembled, $line, $nextIndex] = self::assembleConcatenatedString($tokens, $i, $count);
                if ($assembled !== '') {
                    // Check if immediately followed by '('
                    $after = self::skipWhitespaceAndComments($tokens, $nextIndex, $count);
                    if ($after < $count && $tokens[$after] === '(') {
                        $lower = strtolower($assembled);
                        if (isset($forbidden[$lower])) {
                            $issues[] = self::issueRow($lower, $filePath, $line, $lines, 'Obfuscated usage of invalid token');
                        }
                    }
                }
                // Move the cursor to the end of the processed segment
                $i = max($i, ($nextIndex - 1));
            }
        }

        return $issues;
    }

    /**
     * Create an issue row in the exact structure requested.
     *
     * @param  string        $token
     * @param  string        $file
     * @param  int           $lineNumber
     * @param  array<int,string> $lines
     * @param  string        $message
     * @return array<string,mixed>
     */
    private static function issueRow(string $token, string $file, int $lineNumber, array $lines, string $message): array
    {
        $snippet = isset($lines[$lineNumber - 1]) ? trim($lines[$lineNumber - 1]) : '';
        return [
            'type'    => 'invalid_token_usage',
            'token'   => $token,
            'file'    => $file,
            'line'    => $lineNumber,
            'snippet' => $snippet,
            'issue'   => $message,
        ];
    }

    /**
     * Skip whitespace/comments and return the index of the next significant token.
     */
    private static function skipWhitespaceAndComments(array $tokens, int $i, int $count): int
    {
        for (; $i < $count; $i++) {
            $t = $tokens[$i];
            if (is_string($t)) {
                // Single-char tokens like '(' or ')'
                break;
            }
            $id = $t[0];
            if ($id !== T_WHITESPACE && $id !== T_COMMENT && $id !== T_DOC_COMMENT) {
                break;
            }
        }
        return $i;
    }

    /**
     * True if token is a PHP string literal (single or double quoted).
     */
    private static function isStringLiteral($token): bool
    {
        return is_array($token) && ($token[0] === T_CONSTANT_ENCAPSED_STRING);
    }

    /**
     * Assemble a concatenated string sequence starting at $i:
     *   T_CONSTANT_ENCAPSED_STRING ( (T_WHITESPACE|T_COMMENT|'.') T_CONSTANT_ENCAPSED_STRING )*
     * Returns [assembledString, lineNumberOfFirstLiteral, nextIndexAfterSequence].
     *
     * Only concatenates adjacent literals, ignoring whitespace/comments and literal '.' operators.
     */
    private static function assembleConcatenatedString(array $tokens, int $i, int $count): array
    {
        $assembled = '';
        $line = is_array($tokens[$i]) ? $tokens[$i][2] : 1;
        $idx = $i;

        // First literal
        if (!self::isStringLiteral($tokens[$idx])) {
            return ['', $line, $i];
        }
        $assembled .= self::unquoteLiteral($tokens[$idx][1]);
        $idx++;

        // Zero or more: (whitespace/comment | '.') + literal
        while ($idx < $count) {
            $t = $tokens[$idx];

            // Skip whitespace or comments between pieces
            if (is_array($t) && ($t[0] === T_WHITESPACE || $t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT)) {
                $idx++;
                continue;
            }

            // Require '.' operator to continue, otherwise stop
            if ($t !== '.') {
                break;
            }
            $idx++;

            // Skip whitespace/comments after '.'
            while ($idx < $count && is_array($tokens[$idx]) && in_array($tokens[$idx][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $idx++;
            }

            // Next must be a literal
            if ($idx >= $count || !self::isStringLiteral($tokens[$idx])) {
                break;
            }

            $assembled .= self::unquoteLiteral($tokens[$idx][1]);
            $idx++;
        }

        return [$assembled, $line, $idx];
    }

    /**
     * Remove surrounding quotes from a PHP literal and unescape simple escapes.
     * Handles both single- and double-quoted literals conservatively.
     */
    private static function unquoteLiteral(string $literal): string
    {
        $len = strlen($literal);
        if ($len < 2) {
            return $literal;
        }
        $q = $literal[0];
        if (($q !== '\'' && $q !== '"') || $literal[$len - 1] !== $q) {
            return $literal;
        }
        $body = substr($literal, 1, $len - 2);

        // Minimal unescape to cover common cases used for obfuscation
        // (We don’t need full PHP string semantics here)
        return str_replace(
            $q === '\'' ? ["\\'","\\\\"] : ['\\"','\\\\','\n','\r','\t'],
            $q === '\'' ? ["'","\\"]      : ['"','\\',"\n","\r","\t"],
            $body
        );
    }

    /**
     * Heuristic backtick execution detection: flags lines that contain an unescaped backtick
     * outside obvious quoted string contexts. This is intentionally simple and errs on the side of caution.
     *
     * @param  string              $filePath
     * @param  array<int,string>   $lines
     * @return array<int,array<string,mixed>>
     */
    private static function scanBackticks(string $filePath, array $lines): array
    {
        $issues = [];
        foreach ($lines as $i => $line) {
            // quick skip
            if (!str_contains($line, '`')) {
                continue;
            }
            // Very light check: if the line has an odd number of backticks (unbalanced),
            // or any backtick not obviously inside a quoted string, we flag it.
            // We avoid heavy parsing; this is just a heads-up.
            $tickCount = substr_count($line, '`');
            if ($tickCount > 0) {
                $issues[] = [
                    'type'    => 'invalid_token_usage',
                    'token'   => '`',
                    'file'    => $filePath,
                    'line'    => $i + 1,
                    'snippet' => trim($line),
                    'issue'   => 'Backtick shell execution detected',
                ];
            }
        }
        return $issues;
    }

    /**
     * Fallback when a line number is not available (shouldn’t happen with token_get_all).
     */
    private static function safeLineGuess(array $lines): int
    {
        return max(1, count($lines));
    }
}
```

---
#### 28


` File: src/Enums/AuthorStatus.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Enums;

enum AuthorStatus: string
{
	case pending = "pending";
	case active = "active";
	case inactive = "inactive";
	case blocked = "blocked";
}
```

---
#### 29


` File: src/Enums/IssueStatus.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Enums;

enum IssueStatus: string
{
	case open = "open";
	case triage = "triage";
	case in_progress = "in_progress";
	case resolved = "resolved";
	case rejected = "rejected";
	case closed = "closed";
}
```

---
#### 30


` File: src/Enums/KeyPurpose.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Enums;

enum KeyPurpose: string
{
	case packager_sign = "packager_sign";
	case installer_verify = "installer_verify";
}
```

---
#### 31


` File: src/Enums/PermissionType.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Enums;

enum PermissionType: string
{
	case db = "db";
	case file = "file";
	case notification = "notification";
	case module = "module";
	case network = "network";
	case codec = "codec";
}
```

---
#### 32


` File: src/Enums/PluginSettingValueType.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Enums;

enum PluginSettingValueType: string
{
	case string = "string";
	case number = "number";
	case boolean = "boolean";
	case json = "json";
	case file = "file";
	case blob = "blob";
}
```

---
#### 33


` File: src/Enums/PluginStatus.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Enums;

enum PluginStatus: string
{
	case active = "active";
	case inactive = "inactive";
	case archived = "archived";
}
```

---
#### 34


` File: src/Enums/RoutePermissionStatus.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Enums;

enum RoutePermissionStatus: string
{
	case pending = "pending";
	case approved = "approved";
	case denied = "denied";
	case revoked = "revoked";
}
```

---
#### 35


` File: src/Enums/ValidationStatus.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Enums;

enum ValidationStatus: string
{
    case valid = "valid";
    case unchecked = "unchecked";
    case unverified = "unverified";
    case failed = "failed";
    case pending = "pending";
}
```

---
#### 36


` File: src/Exceptions/DuplicateRouteIdException.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Exceptions;

use RuntimeException;

final class DuplicateRouteIdException extends RuntimeException
{
    public function __construct(
        public readonly string $routeId,
        public readonly string $firstFile,
        public readonly string $firstPath,
        public readonly string $dupFile,
        public readonly string $dupPath
    )
    {
        parent::__construct(
            "Duplicate route id '$routeId' found.\n" .
            " - First seen in: $firstFile $firstPath\n" .
            " - Duplicate in:  $dupFile $dupPath"
        );
    }
}
```

---
#### 37


` File: src/Exceptions/PermissionDeniedException.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Exceptions;

use RuntimeException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Timeax\FortiPlugin\Models\User;
use Timeax\FortiPlugin\Notifications\PermissionGrantNotification;

class PermissionDeniedException extends RuntimeException
{
    protected string $type;
    protected string $target;
    protected array|string|null $permissions;
    protected ?Request $request;

    public function __construct(
        string $type,
        string $target,
        array|string|null $permissions = null,
        ?Request $request = null,
        string $message = "",
        int $code = 0,
        Throwable $previous = null
    ) {
        $this->type = $type;
        $this->target = $target;
        $this->permissions = $permissions;
        $this->request = $request;
        $message = $message ?: "Permission denied for {$type}:{$target}" . ($permissions ? " (" . implode(',', (array)$permissions) . ")" : '');
        parent::__construct($message, $code, $previous);
    }

    public function render($request = null): Response
    {
        /** @var Request|null $request */
        $request = $request ?: $this->request ?: (function_exists('request') ? request() : null);

        // If no request object (e.g. job, command, fallback context)
        if (!$request) {
            // Notify admins with relevant permissions
            $this->notifyPermissionAdmins();

            // Optionally, just throw a generic 403
            abort(403, "Permission denied. Your request has been forwarded to an administrator for review.");
        }

        // 1. API/axios/JSON requests
        if ($request->expectsJson() || $request->isXmlHttpRequest() || $request->wantsJson()) {
            return response()->json([
                'error' => 'plugin_permission_denied',
                'type' => $this->type,
                'target' => $this->target,
                'permissions' => $this->permissions,
                'message' => $this->getMessage(),
                'can_request_permission' => true,
                'request_data' => $this->getClonedRequestData(),
            ], 403);
        }

        // 2. All browser/inertia/other requests: redirect back with flash data only
        return redirect()->back()->with('plugin_permission_data', [
            'type' => $this->type,
            'target' => $this->target,
            'permissions' => $this->permissions,
            'message' => $this->getMessage(),
            'can_request_permission' => true,
            'request_data' => $this->getClonedRequestData(),
        ]);
    }

    protected function notifyPermissionAdmins(): void
    {
        // Find admins who can grant $this->permissions on $this->target of $this->type
        $admins = User::permission('can_grant_permission', 1)->get();

        foreach ($admins as $admin) {
            $admin->notify(new PermissionGrantNotification([
                'type' => $this->type,
                'target' => $this->target,
                'permissions' => $this->permissions,
                'message' => $this->getMessage(),
                'request_data' => $this->getClonedRequestData(),
                // Add more details as needed
            ]));
        }
    }

    public function getClonedRequestData(): array
    {
        if (!$this->request) return [];
        return [
            'method' => $this->request->method(),
            'uri' => $this->request->getRequestUri(),
            'headers' => $this->request->headers->all(),
            'body' => $this->request->all(),
        ];
    }

    public function getType(): string { return $this->type; }
    public function getTarget(): string { return $this->target; }
    public function getPermissions(): array|string|null { return $this->permissions; }
}
```

---
#### 38


` File: src/Exceptions/PluginContextException.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Exceptions;

use RuntimeException;

class PluginContextException extends RuntimeException {}
```

---
#### 39


` File: src/Lib/Obfuscator.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection EncryptionInitializationVectorRandomnessInspection */

/** @noinspection SpellCheckingInspection */

namespace Timeax\FortiPlugin\Lib;

use DeflateContext;
use InflateContext;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Timeax\FortiPlugin\Exceptions\PermissionDeniedException;
use Timeax\FortiPlugin\Lib\Utils\ObfuscatorUtil;
use Timeax\FortiPlugin\Core\ChecksModulePermission;

/**
 * Permission-gated wrappers for sensitive encoder/decoder / obfuscator functions.
 *
 * Plugins MUST call these methods instead of calling the PHP functions directly.
 */
class Obfuscator
{
    use ObfuscatorUtil;

    protected string $type = 'module';
    protected string $target = 'obfuscator';

    use ChecksModulePermission;

    /**
     * Ensure the plugin has permission to use a given obfuscator function.
     *
     * @throws PermissionDeniedException
     */
    protected function ensurePermission(string $capability): void
    {
        $permission = 'use-obfuscator:' . $capability;
        $this->checkModulePermission($permission);
    }


    // ----------------------
    // Base64
    // ----------------------

    public function encodeBase64(string $input): string
    {
        $this->ensurePermission('base64_encode');
        if (!function_exists('base64_encode')) {
            throw new RuntimeException('base64_encode is not available');
        }
        return base64_encode($input);
    }

    public function decodeBase64(string $input, bool $strict = false): string|false
    {
        $this->ensurePermission('base64_decode');
        if (!function_exists('base64_decode')) {
            throw new RuntimeException('base64_decode is not available');
        }
        return base64_decode($input, $strict);
    }

    // ----------------------
    // JSON
    // ----------------------

    /**
     * @throws JsonException
     */
    public function encodeJson(mixed $input, int $flags = 0, int $depth = 512): string|false
    {
        $this->ensurePermission('json_encode');
        if (!function_exists('json_encode')) {
            throw new RuntimeException('json_encode is not available');
        }
        return json_encode($input, JSON_THROW_ON_ERROR | $flags, $depth);
    }

    /**
     * @throws JsonException
     */
    public function decodeJson(string $input, bool $assoc = false, int $depth = 512, int $flags = 0): mixed
    {
        $this->ensurePermission('json_decode');
        if (!function_exists('json_decode')) {
            throw new RuntimeException('json_decode is not available');
        }
        return json_decode($input, $assoc, $depth, JSON_THROW_ON_ERROR | $flags);
    }

    // ----------------------
    // GZIP / zlib
    // ----------------------

    public function compressGz(string $input, int $level = -1, ?int $encoding = null): string|false
    {
        $this->ensurePermission('gzencode');
        if (!function_exists('gzencode')) {
            throw new RuntimeException('gzencode is not available');
        }
        return gzencode($input, $level, $encoding);
    }

    public function decompressGz(string $input): string|false
    {
        $this->ensurePermission('gzdecode');
        if (!function_exists('gzdecode')) {
            throw new RuntimeException('gzdecode is not available');
        }
        return gzdecode($input);
    }

    public function deflateCompress(string $input, int $level = -1): string|false
    {
        $this->ensurePermission('gzdeflate');
        if (!function_exists('gzdeflate')) {
            throw new RuntimeException('gzdeflate is not available');
        }
        return gzdeflate($input, $level);
    }

    public function deflateDecompress(string $input): string|false
    {
        $this->ensurePermission('gzinflate');
        if (!function_exists('gzinflate')) {
            throw new RuntimeException('gzinflate is not available');
        }
        return gzinflate($input);
    }

    // ----------------------
    // BZ2
    // ----------------------

    public function compressBz(string $input, int $blocksize = 4, int $workfactor = 0): string|false
    {
        $this->ensurePermission('bzcompress');
        if (!function_exists('bzcompress')) {
            throw new RuntimeException('bzcompress is not available');
        }
        return bzcompress($input, $blocksize, $workfactor);
    }

    public function decompressBz(string $input, int $small = 0): string|false
    {
        $this->ensurePermission('bzdecompress');
        if (!function_exists('bzdecompress')) {
            throw new RuntimeException('bzdecompress is not available');
        }
        return bzdecompress($input, $small);
    }

    // ----------------------
    // zlib_encode / zlib_decode
    // ----------------------

    public function zlibEncode(string $input, int $encoding = ZLIB_ENCODING_DEFLATE): string|false
    {
        $this->ensurePermission('zlib_encode');
        if (!function_exists('zlib_encode')) {
            throw new RuntimeException('zlib_encode is not available');
        }
        return zlib_encode($input, $encoding);
    }

    public function zlibDecode(string $input): string|false
    {
        $this->ensurePermission('zlib_decode');
        if (!function_exists('zlib_decode')) {
            throw new RuntimeException('zlib_decode is not available');
        }
        return zlib_decode($input);
    }

    // ----------------------
    // Deflate/Inflate stream helpers (if used)
    // ----------------------

    public function deflateInit(int $mode = ZLIB_ENCODING_DEFLATE, array $options = []): false|DeflateContext
    {
        $this->ensurePermission('deflate_init');
        if (!function_exists('deflate_init')) {
            throw new RuntimeException('deflate_init is not available');
        }
        // signature deflate_init(int $encoding, array $options = ?)
        return deflate_init($mode, $options);
    }

    public function deflateAdd($context, string $data, int $flush = ZLIB_SYNC_FLUSH): string|false
    {
        $this->ensurePermission('deflate_add');
        if (!function_exists('deflate_add')) {
            throw new RuntimeException('deflate_add is not available');
        }
        return deflate_add($context, $data, $flush);
    }

    public function inflateInit(int $encoding, array $options = []): false|InflateContext
    {
        $this->ensurePermission('inflate_init');
        if (!function_exists('inflate_init')) {
            throw new RuntimeException('inflate_init is not available');
        }
        return inflate_init($encoding, $options);
    }

    public function inflateAdd($context, string $data): string|false
    {
        $this->ensurePermission('inflate_add');
        if (!function_exists('inflate_add')) {
            throw new RuntimeException('inflate_add is not available');
        }
        return inflate_add($context, $data);
    }

    // ----------------------
    // ROT13 and simple transforms
    // ----------------------

    public function rot13(string $input): string
    {
        $this->ensurePermission('str_rot13');
        if (!function_exists('str_rot13')) {
            throw new RuntimeException('str_rot13 is not available');
        }
        return str_rot13($input);
    }

    public function reverseString(string $input): string
    {
        $this->ensurePermission('strrev');
        if (!function_exists('strrev')) {
            throw new RuntimeException('strrev is not available');
        }
        return strrev($input);
    }

    public function addSlashes(string $input): string
    {
        $this->ensurePermission('addslashes');
        if (!function_exists('addslashes')) {
            throw new RuntimeException('addslashes is not available');
        }
        return addslashes($input);
    }

    public function stripSlashes(string $input): string
    {
        $this->ensurePermission('stripslashes');
        if (!function_exists('stripslashes')) {
            throw new RuntimeException('stripslashes is not available');
        }
        return stripslashes($input);
    }

    public function quoteMeta(string $input): string
    {
        $this->ensurePermission('quotemeta');
        if (!function_exists('quotemeta')) {
            throw new RuntimeException('quotemeta is not available');
        }
        return quotemeta($input);
    }

    public function stripTags(string $input, ?string $allowed = null): string
    {
        $this->ensurePermission('strip_tags');
        if (!function_exists('strip_tags')) {
            throw new RuntimeException('strip_tags is not available');
        }
        return strip_tags($input, $allowed);
    }

    // ----------------------
    // Hex / binary conversions
    // ----------------------

    public function encodeHex(string $input): string
    {
        $this->ensurePermission('bin2hex');
        if (!function_exists('bin2hex')) {
            throw new RuntimeException('bin2hex is not available');
        }
        return bin2hex($input);
    }

    public function decodeHex(string $input): string|false
    {
        $this->ensurePermission('hex2bin');
        if (!function_exists('hex2bin')) {
            throw new RuntimeException('hex2bin is not available');
        }
        return hex2bin($input);
    }

    // ----------------------
    // chr / ord
    // ----------------------

    public function chr(int $ascii): string
    {
        $this->ensurePermission('chr');
        if (!function_exists('chr')) {
            throw new RuntimeException('chr is not available');
        }
        return chr($ascii);
    }

    public function ord(string $char): int
    {
        $this->ensurePermission('ord');
        if (!function_exists('ord')) {
            throw new RuntimeException('ord is not available');
        }
        return ord($char);
    }

    // ----------------------
    // pack / unpack
    // ----------------------

    /**
     * Pack values according to format.
     * Example: pack('H*', $data)
     *
     * @param string $format
     * @param mixed ...$values
     * @return string
     */
    public function pack(string $format, mixed ...$values): string
    {
        $this->ensurePermission('pack');
        if (!function_exists('pack')) {
            throw new RuntimeException('pack is not available');
        }
        return pack($format, ...$values);
    }

    /**
     * Unpack data according to format.
     *
     * @param string $format
     * @param string $data
     * @return array|false
     */
    public function unpack(string $format, string $data): array|false
    {
        $this->ensurePermission('unpack');
        if (!function_exists('unpack')) {
            throw new RuntimeException('unpack is not available');
        }
        return unpack($format, $data);
    }

    // ----------------------
    // URL encoding
    // ----------------------

    public function encodeUrl(string $input): string
    {
        $this->ensurePermission('urlencode');
        if (!function_exists('urlencode')) {
            throw new RuntimeException('urlencode is not available');
        }
        return urlencode($input);
    }

    public function decodeUrl(string $input): string
    {
        $this->ensurePermission('urldecode');
        if (!function_exists('urldecode')) {
            throw new RuntimeException('urldecode is not available');
        }
        return urldecode($input);
    }

    public function rawEncodeUrl(string $input): string
    {
        $this->ensurePermission('rawurlencode');
        if (!function_exists('rawurlencode')) {
            throw new RuntimeException('rawurlencode is not available');
        }
        return rawurlencode($input);
    }

    public function rawDecodeUrl(string $input): string
    {
        $this->ensurePermission('rawurldecode');
        if (!function_exists('rawurldecode')) {
            throw new RuntimeException('rawurldecode is not available');
        }
        return rawurldecode($input);
    }

    // ----------------------
    // convert_uuencode / convert_uudecode
    // ----------------------

    public function convertUuEncode(string $input): string
    {
        $this->ensurePermission('convert_uuencode');
        if (!function_exists('convert_uuencode')) {
            throw new RuntimeException('convert_uuencode is not available');
        }
        return convert_uuencode($input);
    }

    public function convertUuDecode(string $input): string|false
    {
        $this->ensurePermission('convert_uudecode');
        if (!function_exists('convert_uudecode')) {
            throw new RuntimeException('convert_uudecode is not available');
        }
        return convert_uudecode($input);
    }

    // ----------------------
    // serialize / unserialize
    // ----------------------

    public function encodeSerialize(mixed $input): string
    {
        $this->ensurePermission('serialize');
        if (!function_exists('serialize')) {
            throw new RuntimeException('serialize is not available');
        }
        return serialize($input);
    }

    public function decodeSerialize(string $input, array $options = []): mixed
    {
        $this->ensurePermission('unserialize');
        if (!function_exists('unserialize')) {
            throw new RuntimeException('unserialize is not available');
        }
        // use php's second param options if provided (PHP 7.0+)
        return unserialize($input, $options);
    }

    // ----------------------
    // Hashing (md5, sha1, hash, hmac)
    // ----------------------

    public function md5(string $data, bool $rawOutput = false): string
    {
        $this->ensurePermission('md5');
        if (!function_exists('md5')) {
            throw new RuntimeException('md5 is not available');
        }
        return md5($data, $rawOutput);
    }

    public function sha1(string $data, bool $rawOutput = false): string
    {
        $this->ensurePermission('sha1');
        if (!function_exists('sha1')) {
            throw new RuntimeException('sha1 is not available');
        }
        return sha1($data, $rawOutput);
    }

    public function hash(string $algo, string $data, bool $rawOutput = false): string
    {
        $this->ensurePermission('hash');
        if (!function_exists('hash')) {
            throw new RuntimeException('hash is not available');
        }
        return hash($algo, $data, $rawOutput);
    }

    public function hashHmac(string $algo, string $data, string $key, bool $rawOutput = false): string
    {
        $this->ensurePermission('hash_hmac');
        if (!function_exists('hash_hmac')) {
            throw new RuntimeException('hash_hmac is not available');
        }
        return hash_hmac($algo, $data, $key, $rawOutput);
    }

    // ----------------------
    // OpenSSL
    // ----------------------
    /**
     * Encrypt with OpenSSL and return payload with IV prepended (raw binary).
     *
     * Returns raw binary string: iv || ciphertext (OPENSSL_RAW_DATA).
     */
    public function opensslEncryptWithIv(string $data, string $method, string $key, int $options = OPENSSL_RAW_DATA, ?string $iv = null): string
    {
        $this->ensurePermission('openssl_encrypt');

        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException('openssl_encrypt is not available');
        }

        $ivLength = openssl_cipher_iv_length($method);
        if ($ivLength === false) {
            throw new RuntimeException("Could not determine IV length for cipher: $method");
        }

        if ($ivLength > 0) {
            if ($iv === null) {
                $iv = $this->generateIv($method); // reuse your generateIv() helper
            }
            if (!is_string($iv) || strlen($iv) !== $ivLength) {
                throw new InvalidArgumentException("Invalid IV length for cipher $method. Expected $ivLength bytes.");
            }
        } else {
            $iv = $iv ?? '';
        }

        $ciphertext = openssl_encrypt($data, $method, $key, $options, $iv);
        if ($ciphertext === false) {
            return false;
        }

        // return iv + ciphertext (raw)
        return $iv . $ciphertext;
    }

    /**
     * Decrypt a payload produced by opensslEncryptWithIv (iv prepended).
     */
    public function opensslDecryptWithIv(string $payload, string $method, string $key, int $options = OPENSSL_RAW_DATA): string|false
    {
        $this->ensurePermission('openssl_decrypt');

        if (!function_exists('openssl_decrypt')) {
            throw new RuntimeException('openssl_decrypt is not available');
        }

        $ivLength = openssl_cipher_iv_length($method);
        if ($ivLength === false) {
            throw new RuntimeException("Could not determine IV length for cipher: $method");
        }

        if ($ivLength > 0) {
            if (strlen($payload) <= $ivLength) {
                throw new InvalidArgumentException('Payload too short to contain IV + ciphertext');
            }
            $iv = substr($payload, 0, $ivLength);
            $ciphertext = substr($payload, $ivLength);
        } else {
            $iv = '';
            $ciphertext = $payload;
        }

        return openssl_decrypt($ciphertext, $method, $key, $options, $iv);
    }
    // ----------------------
    // mcrypt (legacy) - only if available
    // ----------------------
    /**
     * mcryptEncrypt: generate IV first (via secureRandom), validate, encrypt.
     *
     * @throws RuntimeException|InvalidArgumentException
     */
    public function mcryptEncrypt(string $cipher, string $data, string $key, string $mode, ?string $iv = null): string|false
    {
        $this->ensurePermission('mcrypt_encrypt');
        $this->warnMcryptDeprecated();

        if (!function_exists('mcrypt_encrypt')) {
            throw new RuntimeException('mcrypt_encrypt is not available on this PHP build');
        }

        $ivSize = $this->ivSizeForMcrypt($cipher, $mode); // <- your utility

        // If IV required and not supplied, generate securely
        if ($ivSize > 0 && $iv === null) {
            $iv = $this->secureRandom($ivSize); // <- your utility
        }

        // Validate IV when required
        if ($ivSize > 0) {
            if (!is_string($iv) || strlen($iv) !== $ivSize) {
                throw new InvalidArgumentException(
                    "Invalid IV for cipher '$cipher' mode '$mode'. Expected $ivSize bytes, got " .
                    (is_string($iv) ? strlen($iv) : gettype($iv))
                );
            }
        } else {
            $iv = $iv ?? '';
        }

        // mcrypt_encrypt(string $cipher, string $key, string $data, string $mode [, string $iv ])
        return mcrypt_encrypt($cipher, $key, $data, $mode, $iv);
    }

    /**
     * mcryptDecrypt: require the same IV the encrypt used (no auto-generation).
     *
     * @throws RuntimeException|InvalidArgumentException
     */
    public function mcryptDecrypt(string $cipher, string $data, string $key, string $mode, ?string $iv = null): string|false
    {
        $this->ensurePermission('mcrypt_decrypt');
        $this->warnMcryptDeprecated();

        if (!function_exists('mcrypt_decrypt')) {
            throw new RuntimeException('mcrypt_decrypt is not available on this PHP build');
        }

        $ivSize = $this->ivSizeForMcrypt($cipher, $mode);
        if ($ivSize > 0 && $iv === null) {
            $iv = $this->generateLegacyIvForMcrypt($ivSize);
        }

        return mcrypt_decrypt($cipher, $key, $data, $mode, $iv);
    }

    /**
     * mcryptEncryptWithIv: generates IV (secureRandom) and returns ['iv'=>..., 'ciphertext'=>...].
     */
    public function mcryptEncryptWithIv(string $cipher, string $data, string $key, string $mode, ?string $iv = null): array
    {
        $this->ensurePermission('mcrypt_encrypt');
        $this->warnMcryptDeprecated();

        $ivSize = $this->ivSizeForMcrypt($cipher, $mode);
        if ($ivSize > 0 && $iv === null) {
            $iv = $this->generateLegacyIvForMcrypt($ivSize);
        }

        $ciphertext = $this->mcryptEncrypt($cipher, $data, $key, $mode, $iv);

        return ['iv' => $iv ?? '', 'ciphertext' => $ciphertext];
    }

    /**
     * mcryptDecryptWithIv: accepts payload with IV prepended or separate IV.
     * If $ivSize not provided, it is derived via ivSizeForMcrypt().
     */
    public function mcryptDecryptWithIv(string $payload, string $cipher, string $key, string $mode, ?int $ivSize = null): string|false
    {
        $this->ensurePermission('mcrypt_decrypt');
        $this->warnMcryptDeprecated();

        $ivSize = $ivSize ?? $this->ivSizeForMcrypt($cipher, $mode); // <- your utility

        if ($ivSize > 0) {
            if (strlen($payload) <= $ivSize) {
                throw new InvalidArgumentException('Payload too short to contain IV + ciphertext');
            }
            $iv = substr($payload, 0, $ivSize);
            $ciphertext = substr($payload, $ivSize);
        } else {
            $iv = '';
            $ciphertext = $payload;
        }

        return $this->mcryptDecrypt($cipher, $ciphertext, $key, $mode, $iv);
    }

    // ----------------------
    // Convenience: allow callers to list available wrappers
    // ----------------------

    public function available(): array
    {
        return [
            // grouped list of the exposed wrappers and their underlying functions
            'base64_encode' => 'encodeBase64',
            'base64_decode' => 'decodeBase64',
            'json_encode' => 'encodeJson',
            'json_decode' => 'decodeJson',
            'gzencode' => 'compressGz',
            'gzdecode' => 'decompressGz',
            'gzdeflate' => 'deflateCompress',
            'gzinflate' => 'deflateDecompress',
            'bzcompress' => 'compressBz',
            'bzdecompress' => 'decompressBz',
            'zlib_encode' => 'zlibEncode',
            'zlib_decode' => 'zlibDecode',
            'deflate_init' => 'deflateInit',
            'deflate_add' => 'deflateAdd',
            'inflate_init' => 'inflateInit',
            'inflate_add' => 'inflateAdd',
            'str_rot13' => 'rot13',
            'strrev' => 'reverseString',
            'addslashes' => 'addSlashes',
            'stripslashes' => 'stripSlashes',
            'quotemeta' => 'quoteMeta',
            'strip_tags' => 'stripTags',
            'bin2hex' => 'encodeHex',
            'hex2bin' => 'decodeHex',
            'chr' => 'chr',
            'ord' => 'ord',
            'pack' => 'pack',
            'unpack' => 'unpack',
            'urlencode' => 'encodeUrl',
            'urldecode' => 'decodeUrl',
            'rawurlencode' => 'rawEncodeUrl',
            'rawurldecode' => 'rawDecodeUrl',
            'convert_uuencode' => 'convertUuEncode',
            'convert_uudecode' => 'convertUuDecode',
            'serialize' => 'encodeSerialize',
            'unserialize' => 'decodeSerialize',
            'md5' => 'md5',
            'sha1' => 'sha1',
            'hash' => 'hash',
            'hash_hmac' => 'hashHmac',
            'openssl_encrypt' => 'opensslEncrypt',
            'openssl_decrypt' => 'opensslDecrypt',
            'mcrypt_encrypt' => 'mcryptEncrypt',
            'mcrypt_decrypt' => 'mcryptDecrypt',
        ];
    }

    /**
     * Grouped list of available wrappers, categorized by purpose.
     * Static variant of available().
     */
    public static function availableGroups(): array
    {
        return [
            'encoding' => [
                'base64_encode' => 'encodeBase64',
                'base64_decode' => 'decodeBase64',
                'json_encode' => 'encodeJson',
                'json_decode' => 'decodeJson',
                'bin2hex' => 'encodeHex',
                'hex2bin' => 'decodeHex',
                'urlencode' => 'encodeUrl',
                'urldecode' => 'decodeUrl',
                'rawurlencode' => 'rawEncodeUrl',
                'rawurldecode' => 'rawDecodeUrl',
                'convert_uuencode' => 'convertUuEncode',
                'convert_uudecode' => 'convertUuDecode',
                'pack' => 'pack',
                'unpack' => 'unpack',
                'chr' => 'chr',
                'ord' => 'ord',
            ],
            'compression' => [
                'gzencode' => 'compressGz',
                'gzdecode' => 'decompressGz',
                'gzdeflate' => 'deflateCompress',
                'gzinflate' => 'deflateDecompress',
                'bzcompress' => 'compressBz',
                'bzdecompress' => 'decompressBz',
                'zlib_encode' => 'zlibEncode',
                'zlib_decode' => 'zlibDecode',
                'deflate_init' => 'deflateInit',
                'deflate_add' => 'deflateAdd',
                'inflate_init' => 'inflateInit',
                'inflate_add' => 'inflateAdd',
            ],
            'hash' => [
                'md5' => 'md5',
                'sha1' => 'sha1',
                'hash' => 'hash',
                'hash_hmac' => 'hashHmac',
            ],
            'crypto' => [
                'openssl_encrypt' => 'opensslEncryptWithIv',
                'openssl_decrypt' => 'opensslDecryptWithIv',
                'mcrypt_encrypt' => 'mcryptEncrypt',
                'mcrypt_decrypt' => 'mcryptDecrypt',
            ],
            'serialize' => [
                'serialize' => 'encodeSerialize',
                'unserialize' => 'decodeSerialize',
            ],
            'obfuscation' => [
                'str_rot13' => 'rot13',
                'strrev' => 'reverseString',
                'addslashes' => 'addSlashes',
                'stripslashes' => 'stripSlashes',
                'quotemeta' => 'quoteMeta',
                'strip_tags' => 'stripTags',
            ],
        ];
    }
}
```

---
#### 40


` File: src/Lib/Utils/ObfuscatorUtil.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection CryptographicallySecureRandomnessInspection */

/** @noinspection SpellCheckingInspection */

namespace Timeax\FortiPlugin\Lib\Utils;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

trait ObfuscatorUtil
{
    /**
     * Emit deprecation and telemetry for mcrypt usage.
     */
    protected function warnMcryptDeprecated(): void
    {
        // E_USER_DEPRECATED so monitoring/logging systems can pick it up
        @trigger_error('mcrypt is deprecated. Migrate to OpenSSL (openssl_encrypt) or Sodium (sodium_crypto_*).', E_USER_DEPRECATED);

        // Telemetry/logging: record plugin/module name, stack, timestamp
        $this->telemetryLogMcryptUsage();
    }

    /**
     * Telemetry helper for legacy mcrypt usage.
     * Adjust to use your telemetry system or PSR-3 logger.
     */
    protected function telemetryLogMcryptUsage(): void
    {
        try {
            $payload = [
                'module' => static::class,
                'time' => date('c'),
                'caller' => $this->getCallerSummary(),
            ];

            if (class_exists(Log::class)) {
                Log::warning('Legacy mcrypt usage detected', $payload);
            }
        } /** @noinspection PhpUnusedLocalVariableInspection */ catch (Throwable $e) {
            // Never fail telemetry
        }
    }

    /**
     * Return a small caller summary for telemetry (file:line).
     */
    protected function getCallerSummary(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        // skip current method and direct parent calls
        foreach ($trace as $frame) {
            if (isset($frame['file']) && !str_ends_with($frame['file'], __FILE__)) {
                return ($frame['file']) . ':' . ($frame['line'] ?? '0');
            }
        }
        return 'unknown';
    }

    public function urlencode(string $input): string
    {
        return $this->encodeUrl($input);
    }

    public function urldecode(string $input): string
    {
        return $this->decodeUrl($input);
    }

    // inside your class / module
    /**
     * Determine IV size for mcrypt cipher/mode without @-suppression.
     * Returns 0 if the environment cannot determine/does not require an IV.
     */
    protected function ivSizeForMcrypt(string $cipher, string $mode): int
    {
        if (!function_exists('mcrypt_get_iv_size')) {
            // On hosts without ext-mcrypt or when IV isn't used, treat as 0
            return 0;
        }

        $size = mcrypt_get_iv_size($cipher, $mode);
        if ($size === false) {
            throw new RuntimeException("Unable to determine IV size for cipher '$cipher' mode '$mode'");
        }

        return $size;
    }

    /**
     * Generate a cryptographically secure IV for legacy mcrypt usage.
     * Prefer random_bytes(); fall back to mcrypt_create_iv() with MCRYPT_DEV_RANDOM.
     */
    protected function generateLegacyIvForMcrypt(int $ivSize): string
    {
        if ($ivSize <= 0) {
            return '';
        }

        // Preferred modern API (PHP 7+): throws on failure
        if (function_exists('random_bytes')) {
            try {
                $iv = random_bytes($ivSize);
                if (strlen($iv) !== $ivSize) {
                    throw new RuntimeException('random_bytes() returned invalid length');
                }
                return $iv;
            } catch (Throwable $e) {
                throw new RuntimeException('random_bytes() failed to generate IV: ' . $e->getMessage(), 0, $e);
            }
        }

        // Legacy fallback
        if (function_exists('mcrypt_create_iv')) {
            // Prefer MCRYPT_DEV_RANDOM (may block until enough entropy is available)
            if (defined('MCRYPT_DEV_RANDOM')) {
                $source = MCRYPT_DEV_RANDOM;
            } elseif (defined('MCRYPT_DEV_URANDOM')) {
                $source = MCRYPT_DEV_URANDOM; // older PHPs; acceptable if present
            } elseif (defined('MCRYPT_RAND')) {
                $source = MCRYPT_RAND; // weakest; avoid if possible
                @trigger_error('Using MCRYPT_RAND for IV generation (not cryptographically strong).', E_USER_WARNING);
            } else {
                throw new RuntimeException('No suitable MCRYPT constant available for IV generation');
            }

            $iv = mcrypt_create_iv($ivSize, $source);
            if ($iv === false || !is_string($iv) || strlen($iv) !== $ivSize) {
                throw new RuntimeException('mcrypt_create_iv() failed to generate a valid IV');
            }

            return $iv;
        }

        throw new RuntimeException('No secure random generator available (random_bytes() or mcrypt_create_iv()).');
    }

    /**
     * Return cryptographically secure random bytes of $length.
     *
     * Prefer random_bytes() (PHP7+). Fallback to openssl_random_pseudo_bytes()
     * with crypto-strength check if random_bytes() is not available.
     *
     * @param int $length
     * @return string
     * @throws RuntimeException
     */
    protected function secureRandom(int $length): string
    {
        if ($length <= 0) {
            return '';
        }

        // Preferred modern API: throws on failure.
        if (function_exists('random_bytes')) {
            try {
                $bytes = random_bytes($length);
            } catch (Throwable $e) {
                throw new RuntimeException('random_bytes() failed: ' . $e->getMessage(), 0, $e);
            }

            if (strlen($bytes) !== $length) {
                throw new RuntimeException('random_bytes() produced invalid output');
            }

            return $bytes;
        }

        // Fallback to openssl_random_pseudo_bytes() and verify crypto-strong flag.
        if (function_exists('openssl_random_pseudo_bytes')) {
            $crypto_strong = false;
            $bytes = openssl_random_pseudo_bytes($length, $crypto_strong);

            /** @noinspection PhpStrictComparisonWithOperandsOfDifferentTypesInspection */
            if ($bytes === false || $crypto_strong === false) {
                throw new RuntimeException('openssl_random_pseudo_bytes() failed or is not cryptographically strong');
            }
            if (strlen($bytes) !== $length) {
                throw new RuntimeException('openssl_random_pseudo_bytes() produced invalid output');
            }

            return $bytes;
        }

        throw new RuntimeException('No secure random generator available (random_bytes() or openssl_random_pseudo_bytes()).');
    }

    /**
     * Generate an IV for a given cipher method (OpenSSL) and validate it.
     *
     * @param string $method
     * @return string
     * @throws RuntimeException
     */
    protected function generateIv(string $method): string
    {
        $ivLength = openssl_cipher_iv_length($method);
        if ($ivLength === false) {
            throw new RuntimeException("Could not determine IV length for cipher: $method");
        }

        if ($ivLength === 0) {
            return '';
        }

        $iv = $this->secureRandom($ivLength);

        // Extra sanity check (should be redundant)
        if (strlen($iv) !== $ivLength) {
            throw new RuntimeException("Generated IV has invalid length for $method");
        }

        return $iv;
    }
}
```

---
#### 41


` File: src/Models/AuditLog.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $actor
 * @property int|null $actor_author_id
 * @property string $action
 * @property array|null $context
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Author::class $actorAuthor
 */
class AuditLog extends Model
{
	protected $table = "scpl_audit_logs";

	protected $fillable = ["actor", "actor_author_id", "action", "context"];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"context" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function actorAuthor()
	{
		return $this->belongsTo(Author::class, "actor_author_id", "id");
	}
}
```

---
#### 42


` File: src/Models/Author.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Timeax\FortiPlugin\Enums\AuthorStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $handle
 * @property string|null $email
 * @property string $password
 * @property string|null $avatar_url
 * @property string|null $org
 * @property string|null $website
 * @property array|null $meta
 * @property AuthorStatus::class $status
 * @property bool $verified
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Illuminate\Support\Collection<int, Plugin::class> $pluginLinks
 * @property \Illuminate\Support\Collection<int, PluginIssue::class> $reportedIssues
 * @property \Illuminate\Support\Collection<int, PluginIssueMessage::class> $issueMessages
 * @property \Illuminate\Support\Collection<int, PluginZip::class> $uploadedZips
 * @property \Illuminate\Support\Collection<int, PluginToken::class> $pluginTokens
 * @property \Illuminate\Support\Collection<int, AuthorToken::class> $tokens
 * @property \Illuminate\Support\Collection<int, PluginAuditLog::class> $pluginAuditActors
 * @property \Illuminate\Support\Collection<int, AuditLog::class> $auditActors
 */
class Author extends Model
{
	protected $table = "scpl_authors";

	protected $fillable = [
		"slug",
		"name",
		"handle",
		"email",
		"password",
		"avatar_url",
		"org",
		"website",
		"meta",
		"status",
		"verified",
	];

	protected $hidden = ["password"];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"meta" => AsArrayObject::class,
		"status" => AuthorStatus::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function pluginLinks()
	{
		return $this->belongsToMany(
			Plugin::class,
			"plugin_author",
			"author_id",
			"plugin_id",
			"id",
			"id",
		); // pivot: plugin_author
	}

	public function reportedIssues()
	{
		return $this->hasMany(PluginIssue::class, "reporter_id", "id");
	}

	public function issueMessages()
	{
		return $this->hasMany(PluginIssueMessage::class, "author_id", "id");
	}

	public function uploadedZips()
	{
		return $this->hasMany(PluginZip::class, "uploaded_by_author_id", "id");
	}

	public function pluginTokens()
	{
		return $this->hasMany(PluginToken::class, "author_id", "id");
	}

	public function tokens()
	{
		return $this->hasMany(AuthorToken::class, "author_id", "id");
	}

	public function pluginAuditActors()
	{
		return $this->hasMany(PluginAuditLog::class, "actor_author_id", "id");
	}

	public function auditActors()
	{
		return $this->hasMany(AuditLog::class, "actor_author_id", "id");
	}
}
```

---
#### 43


` File: src/Models/AuthorToken.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $author_id
 * @property string $token_hash
 * @property \Carbon\Carbon $expires_at
 * @property \Carbon\Carbon|null $last_used
 * @property bool $revoked
 * @property array|null $meta
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Author::class $author
 */
class AuthorToken extends Model
{
	protected $table = "scpl_author_tokens";

	protected $fillable = [
		"author_id",
		"token_hash",
		"expires_at",
		"last_used",
		"revoked",
		"meta",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"expires_at" => "datetime",
		"last_used" => "datetime",
		"meta" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function author()
	{
		return $this->belongsTo(Author::class, "author_id", "id");
	}
}
```

---
#### 44


` File: src/Models/HostKey.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Timeax\FortiPlugin\Enums\KeyPurpose;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property KeyPurpose::class $purpose
 * @property string $public_pem
 * @property string|null $private_pem
 * @property string $fingerprint
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $rotated_at
 */
class HostKey extends Model
{
	protected $table = "scpl_host_keys";

	protected $fillable = [
		"purpose",
		"public_pem",
		"private_pem",
		"fingerprint",
		"created_at",
		"rotated_at",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"purpose" => KeyPurpose::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
		"rotated_at" => "datetime",
	];
}
```

---
#### 45


` File: src/Models/PermissionTag.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Timeax\FortiPlugin\Enums\PluginStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_system
 * @property PluginStatus::class $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Illuminate\Support\Collection<int, PluginPermissionTag::class> $plugins
 * @property \Illuminate\Support\Collection<int, PermissionTagItem::class> $items
 */
class PermissionTag extends Model
{
	protected $table = "scpl_permission_tags";

	protected $fillable = ["name", "description", "is_system", "status"];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"status" => PluginStatus::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function plugins()
	{
		return $this->hasMany(PluginPermissionTag::class, "tag_id", "id");
	}

	public function items()
	{
		return $this->hasMany(PermissionTagItem::class, "tag_id", "id");
	}
}
```

---
#### 46


` File: src/Models/PermissionTagItem.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Timeax\FortiPlugin\Enums\PermissionType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tag_id
 * @property PermissionType::class $permission_type
 * @property int $permission_id
 * @property array|null $constraints
 * @property array|null $audit
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property PermissionTag::class $tag
 */
class PermissionTagItem extends Model
{
	protected $table = "scpl_permission_tag_items";

	protected $fillable = [
		"tag_id",
		"permission_type",
		"permission_id",
		"constraints",
		"audit",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"permission_type" => PermissionType::class,
		"constraints" => AsArrayObject::class,
		"audit" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function tag()
	{
		return $this->belongsTo(PermissionTag::class, "tag_id", "id");
	}
}
```

---
#### 47


` File: src/Models/Plugin.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Timeax\FortiPlugin\Enums\PluginStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $image
 * @property PluginStatus::class $status
 * @property array|null $config
 * @property array|null $meta
 * @property int $plugin_placeholder_id
 * @property int $active_version_id
 * @property string|null $owner_ref
 * @property \Carbon\Carbon|null $activated_at
 * @property int|null $activated_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property PluginPlaceholder::class $placeholder
 * @property \Illuminate\Support\Collection<int, PluginSetting::class> $plugin_settings
 * @property \Illuminate\Support\Collection<int, PluginVersion::class> $plugin_versions
 * @property \Illuminate\Support\Collection<int, PluginAuditLog::class> $logs
 * @property \Illuminate\Support\Collection<int, Author::class> $authors
 * @property \Illuminate\Support\Collection<int, PluginIssue::class> $issues
 * @property \Illuminate\Support\Collection<int, PluginPermission::class> $plugin_permissions
 * @property \Illuminate\Support\Collection<int, PluginPermissionTag::class> $permission_tags
 * @property \Illuminate\Support\Collection<int, PluginRoutePermission::class> $routes
 */
class Plugin extends Model
{
	protected $table = "scpl_plugins";

	protected $guarded = [];

	protected $casts = [
		"status" => PluginStatus::class,
		"config" => AsArrayObject::class,
		"meta" => AsArrayObject::class,
		"activated_at" => "datetime",
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function placeholder()
	{
		return $this->belongsTo(
			PluginPlaceholder::class,
			"plugin_placeholder_id",
			"id",
		);
	}

	public function plugin_settings()
	{
		return $this->hasMany(PluginSetting::class, "plugin_id", "id");
	}

	public function plugin_versions()
	{
		return $this->hasMany(PluginVersion::class, "plugin_id", "id");
	}

	public function logs()
	{
		return $this->hasMany(PluginAuditLog::class, "plugin_id", "id");
	}

	public function authors()
	{
		return $this->belongsToMany(
			Author::class,
			"plugin_author",
			"plugin_id",
			"author_id",
			"id",
			"id",
		); // pivot: plugin_author
	}

	public function issues()
	{
		return $this->hasMany(PluginIssue::class, "plugin_id", "id");
	}

	public function plugin_permissions()
	{
		return $this->hasMany(PluginPermission::class, "plugin_id", "id");
	}

	public function permission_tags()
	{
		return $this->hasMany(PluginPermissionTag::class, "plugin_id", "id");
	}

	public function routes()
	{
		return $this->hasMany(PluginRoutePermission::class, "plugin_id", "id");
	}
}
```

---
#### 48


` File: src/Models/PluginAuditLog.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $plugin_id
 * @property string|null $actor
 * @property int|null $actor_author_id
 * @property string $type
 * @property string $action
 * @property string $resource
 * @property array|null $context
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Plugin::class $plugin
 * @property Author::class $actorAuthor
 */
class PluginAuditLog extends Model
{
	protected $table = "scpl_plugin_audit_logs";

	protected $fillable = [
		"plugin_id",
		"actor",
		"actor_author_id",
		"type",
		"action",
		"resource",
		"context",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"context" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function plugin()
	{
		return $this->belongsTo(Plugin::class, "plugin_id", "id");
	}

	public function actorAuthor()
	{
		return $this->belongsTo(Author::class, "actor_author_id", "id");
	}
}
```

---
#### 49


` File: src/Models/PluginIssue.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Timeax\FortiPlugin\Enums\IssueStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $plugin_id
 * @property int $reporter_id
 * @property string $type
 * @property string $description
 * @property IssueStatus::class $status
 * @property string|null $severity
 * @property array|null $meta
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Plugin::class $plugin
 * @property Author::class $reporter
 * @property \Illuminate\Support\Collection<int, PluginIssueMessage::class> $messages
 */
class PluginIssue extends Model
{
	protected $table = "scpl_plugin_issues";

	protected $fillable = [
		"plugin_id",
		"reporter_id",
		"type",
		"description",
		"status",
		"severity",
		"meta",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"status" => IssueStatus::class,
		"meta" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function plugin()
	{
		return $this->belongsTo(Plugin::class, "plugin_id", "id");
	}

	public function reporter()
	{
		return $this->belongsTo(Author::class, "reporter_id", "id");
	}

	public function messages()
	{
		return $this->hasMany(PluginIssueMessage::class, "issue_id", "id");
	}
}
```

---
#### 50


` File: src/Models/PluginIssueMessage.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $issue_id
 * @property int $author_id
 * @property string $message
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property PluginIssue::class $issue
 * @property Author::class $author
 */
class PluginIssueMessage extends Model
{
	protected $table = "scpl_plugin_issue_messages";

	protected $fillable = ["issue_id", "author_id", "message"];

	protected $guarded = ["id", "id"];

	protected $casts = ["created_at" => "datetime", "updated_at" => "datetime"];

	public function issue()
	{
		return $this->belongsTo(PluginIssue::class, "issue_id", "id");
	}

	public function author()
	{
		return $this->belongsTo(Author::class, "author_id", "id");
	}
}
```

---
#### 51


` File: src/Models/PluginPermission.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Timeax\FortiPlugin\Enums\PermissionType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $plugin_id
 * @property PermissionType::class $permission_type
 * @property int $permission_id
 * @property bool $active
 * @property bool $limited
 * @property string|null $limit_type
 * @property string|null $limit_value
 * @property array|null $constraints
 * @property array|null $audit
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Plugin::class $plugin
 */
class PluginPermission extends Model
{
	protected $table = "scpl_plugin_permissions";

	protected $fillable = [
		"plugin_id",
		"permission_type",
		"permission_id",
		"active",
		"limited",
		"limit_type",
		"limit_value",
		"constraints",
		"audit",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"permission_type" => PermissionType::class,
		"constraints" => AsArrayObject::class,
		"audit" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function plugin()
	{
		return $this->belongsTo(Plugin::class, "plugin_id", "id");
	}
}
```

---
#### 52


` File: src/Models/PluginPermissionTag.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $plugin_id
 * @property int $tag_id
 * @property bool $active
 * @property bool $limited
 * @property string|null $limit_type
 * @property string|null $limit_value
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Plugin::class $plugin
 * @property PermissionTag::class $tag
 */
class PluginPermissionTag extends Model
{
	protected $table = "scpl_plugin_permission_tags";

	protected $fillable = [
		"plugin_id",
		"tag_id",
		"active",
		"limited",
		"limit_type",
		"limit_value",
	];

	protected $guarded = ["id", "id"];

	protected $casts = ["created_at" => "datetime", "updated_at" => "datetime"];

	public function plugin()
	{
		return $this->belongsTo(Plugin::class, "plugin_id", "id");
	}

	public function tag()
	{
		return $this->belongsTo(PermissionTag::class, "tag_id", "id");
	}
}
```

---
#### 53


` File: src/Models/PluginPlaceholder.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $unique_key
 * @property string|null $owner_ref
 * @property array|null $meta
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Illuminate\Support\Collection<int, PluginToken::class> $tokens
 * @property \Illuminate\Support\Collection<int, PluginSignature::class> $signatures
 * @property \Illuminate\Support\Collection<int, PluginZip::class> $zips
 * @property Plugin::class $plugin
 */
class PluginPlaceholder extends Model
{
	protected $table = "scpl_placeholders";

	protected $fillable = ["slug", "name", "unique_key", "owner_ref", "meta"];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"meta" => AsArrayObject::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function tokens()
	{
		return $this->hasMany(
			PluginToken::class,
			"plugin_placeholder_id",
			"id",
		);
	}

	public function signatures()
	{
		return $this->hasMany(PluginSignature::class, "placeholder_id", "id");
	}

	public function zips()
	{
		return $this->hasMany(PluginZip::class, "placeholder_id", "id");
	}

	public function plugin()
	{
		return $this->hasOne(Plugin::class, "plugin_placeholder_id", "id");
	}
}
```

---
#### 54


` File: src/Models/PluginRoutePermission.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Timeax\FortiPlugin\Enums\RoutePermissionStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $plugin_id
 * @property string $route_id
 * @property RoutePermissionStatus::class $status
 * @property string|null $guard
 * @property array|null $meta
 * @property \Carbon\Carbon|null $approved_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Plugin::class $plugin
 */
class PluginRoutePermission extends Model
{
	protected $table = "scpl_plugin_route_permissions";

	protected $fillable = [
		"plugin_id",
		"route_id",
		"status",
		"guard",
		"meta",
		"approved_at",
	];

	protected $casts = [
		"status" => RoutePermissionStatus::class,
		"meta" => AsArrayObject::class,
		"approved_at" => "datetime",
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function plugin()
	{
		return $this->belongsTo(Plugin::class, "plugin_id", "id");
	}
}
```

---
#### 55


` File: src/Models/PluginSetting.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Timeax\FortiPlugin\Enums\PluginSettingValueType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $plugin_id
 * @property string $key
 * @property string $value
 * @property PluginSettingValueType::class $type
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Plugin::class $plugin
 */
class PluginSetting extends Model
{
	protected $table = "scpl_plugin_settings";

	protected $fillable = ["plugin_id", "key", "value", "type"];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"type" => PluginSettingValueType::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function plugin()
	{
		return $this->belongsTo(Plugin::class, "plugin_id", "id");
	}
}
```

---
#### 56


` File: src/Models/PluginSignature.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $placeholder_id
 * @property string $host_domain
 * @property string $owner_host
 * @property string $plugin_key
 * @property string $signature
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property PluginPlaceholder::class $placeholder
 */
class PluginSignature extends Model
{
	protected $table = "scpl_plugin_signatures";

	protected $fillable = [
		"placeholder_id",
		"host_domain",
		"owner_host",
		"plugin_key",
		"signature",
	];

	protected $guarded = ["id", "id"];

	protected $casts = ["created_at" => "datetime", "updated_at" => "datetime"];

	public function placeholder()
	{
		return $this->belongsTo(
			PluginPlaceholder::class,
			"placeholder_id",
			"id",
		);
	}
}
```

---
#### 57


` File: src/Models/PluginToken.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $plugin_placeholder_id
 * @property string $token_hash
 * @property array $meta
 * @property \Carbon\Carbon $expires_at
 * @property \Carbon\Carbon|null $last_used
 * @property bool $revoked
 * @property int|null $author_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property PluginPlaceholder::class $placeholder
 * @property Author::class $author
 */
class PluginToken extends Model
{
	protected $table = "scpl_plugin_tokens";

	protected $fillable = [
		"plugin_placeholder_id",
		"token_hash",
		"meta",
		"expires_at",
		"last_used",
		"revoked",
		"author_id",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"meta" => AsArrayObject::class,
		"expires_at" => "datetime",
		"last_used" => "datetime",
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function placeholder()
	{
		return $this->belongsTo(
			PluginPlaceholder::class,
			"plugin_placeholder_id",
			"id",
		);
	}

	public function author()
	{
		return $this->belongsTo(Author::class, "author_id", "id");
	}
}
```

---
#### 58


` File: src/Models/PluginVersion.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Timeax\FortiPlugin\Enums\ValidationStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $plugin_id
 * @property string $version
 * @property string $archive_url
 * @property array|null $manifest
 * @property array|null $validation_report
 * @property ValidationStatus::class $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Plugin::class $plugin
 */
class PluginVersion extends Model
{
	protected $table = "scpl_plugin_versions";

	protected $fillable = [
		"plugin_id",
		"version",
		"archive_url",
		"manifest",
		"validation_report",
		"status",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"manifest" => AsArrayObject::class,
		"validation_report" => AsArrayObject::class,
		"status" => ValidationStatus::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function plugin()
	{
		return $this->belongsTo(Plugin::class, "plugin_id", "id");
	}
}
```

---
#### 59


` File: src/Models/PluginZip.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Timeax\FortiPlugin\Enums\PluginStatus;
use Timeax\FortiPlugin\Enums\ValidationStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $placeholder_id
 * @property string $path
 * @property array $meta
 * @property PluginStatus::class $status
 * @property ValidationStatus::class $validation_status
 * @property int|null $uploaded_by_author_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property PluginPlaceholder::class $placeholder
 * @property Author::class $uploadedBy
 */
class PluginZip extends Model
{
	protected $table = "scpl_plugin_zips";

	protected $fillable = [
		"placeholder_id",
		"path",
		"meta",
		"status",
		"validation_status",
		"uploaded_by_author_id",
	];

	protected $guarded = ["id", "id"];

	protected $casts = [
		"meta" => AsArrayObject::class,
		"status" => PluginStatus::class,
		"validation_status" => ValidationStatus::class,
		"created_at" => "datetime",
		"updated_at" => "datetime",
	];

	public function placeholder()
	{
		return $this->belongsTo(
			PluginPlaceholder::class,
			"placeholder_id",
			"id",
		);
	}

	public function uploadedBy()
	{
		return $this->belongsTo(Author::class, "uploaded_by_author_id", "id");
	}
}
```

---
#### 60


` File: src/Permissions/Evaluation/Dto/PermissionListResult.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation\Dto;

/**
 * Final list payload: items + summary.
 */
final readonly class PermissionListResult
{
    /** @param PermissionListItem[] $items */
    public function __construct(
        public array                $items,
        public PermissionListSummary $summary
    ) {}

    public function toArray(): array
    {
        return [
            'items'   => array_map(static fn($i) => $i instanceof PermissionListItem ? $i->toArray() : $i, $this->items),
            'summary' => $this->summary->toArray(),
        ];
    }
}
```

---
#### 61


` File: src/Permissions/Evaluation/Dto/PermissionListSummary.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Evaluation\Dto;

final readonly class PermissionListSummary
{
    /**
     * @param array<string,int> $byType
     */
    public function __construct(
        public array $byType,
        public int   $total,
        public int   $active,
        public int   $inactive,
        public int   $requiredTotal,
        public int   $requiredSatisfied,
        public int   $requiredPending
    ) {}

    public function toArray(): array
    {
        return [
            'totals' => [
                'by_type' => $this->byType,
                'total'   => $this->total,
                'active'  => $this->active,
                'inactive'=> $this->inactive,
            ],
            'required' => [
                'total'     => $this->requiredTotal,
                'satisfied' => $this->requiredSatisfied,
                'pending'   => $this->requiredPending,
            ],
        ];
    }
}
```

---
#### 62


` File: src/Permissions/Support/HostConfigNormalizer.php`  [↑ Back to top](#index)

```php
<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Support;

/**
 * Single source of truth for host-config normalization.
 *
 * All methods are pure (input → canonicalized output) and
 * safe to use from both catalogs and validators.
 */
final class HostConfigNormalizer
{
    /**
     * Normalize models map.
     *
     * Input shape (host config):
     *   [ alias => [
     *       'map' => FQCN,
     *       'relations' => [ relationName => relatedAlias, ... ] (optional),
     *       'columns' => [
     *         'all' => string[],       // optional
     *         'writable' => string[]   // optional, enforced ⊆ all when both present
     *       ] (optional)
     *   ], ...]
     *
     * Output (canonical):
     *   [ alias => [
     *       'map'       => FQCN,
     *       'relations' => [ relationName => relatedAlias, ... ],
     *       'columns'   => ['all' => ?string[], 'writable' => ?string[]]
     *   ], ...]
     *
     * - Drops invalid entries.
     * - Dedupes/sorts string lists.
     * - Ensures writable ⊆ all (when both present).
     */
    public static function models(array $raw): array
    {
        $out = [];
        foreach ($raw as $alias => $def) {
            if (!is_string($alias) || $alias === '' || !is_array($def)) {
                continue;
            }
            $fqcn = $def['map'] ?? null;
            if (!is_string($fqcn) || $fqcn === '') {
                continue;
            }

            // relations
            $rels = [];
            if (isset($def['relations']) && is_array($def['relations'])) {
                foreach ($def['relations'] as $rel => $relAlias) {
                    if (is_string($rel) && $rel !== '' && is_string($relAlias) && $relAlias !== '') {
                        $rels[$rel] = $relAlias;
                    }
                }
                ksort($rels, SORT_STRING);
            }

            // columns
            $all = null;
            $writable = null;
            if (isset($def['columns']) && is_array($def['columns'])) {
                if (isset($def['columns']['all']) && is_array($def['columns']['all'])) {
                    $all = self::uniqueSortedStrings($def['columns']['all']);
                }
                if (isset($def['columns']['writable']) && is_array($def['columns']['writable'])) {
                    $writable = self::uniqueSortedStrings($def['columns']['writable']);
                }
                // enforce writable ⊆ all when both present
                if ($all !== null && $writable !== null) {
                    $writable = array_values(array_intersect($writable, $all));
                }
            }

            $out[$alias] = [
                'map' => $fqcn,
                'relations' => $rels,
                'columns' => ['all' => $all, 'writable' => $writable],
            ];
        }

        ksort($out, SORT_STRING);
        return $out;
    }

    /**
     * Normalize modules map.
     *
     * Input:  [ alias => ['map' => FQCN, 'docs' => ?string], ...]
     * Output: [ alias => ['map' => FQCN, 'docs' => ?string], ...]
     * - Drops invalid entries, dedupes/sorts.
     */
    public static function modules(array $raw): array
    {
        $out = [];
        foreach ($raw as $alias => $def) {
            if (!is_string($alias) || $alias === '' || !is_array($def)) {
                continue;
            }
            $fqcn = $def['map'] ?? null;
            if (!is_string($fqcn) || $fqcn === '') {
                continue;
            }
            $docs = null;
            if (isset($def['docs']) && is_string($def['docs']) && $def['docs'] !== '') {
                $docs = $def['docs'];
            }
            $out[$alias] = ['map' => $fqcn, 'docs' => $docs];
        }

        ksort($out, SORT_STRING);
        return $out;
    }

    /**
     * Normalize notification channels.
     *
     * Accepts associative or list:
     *   ['email'=>true,'sms'=>true] OR ['email','sms']
     * Returns sorted unique list: ['email','sms']
     */
    public static function notificationChannels(array $raw): array
    {
        // If associative, use keys; else take values.
        $keys = array_keys($raw);
        $isAssoc = array_keys($keys) !== $keys;
        $list = $isAssoc ? array_keys($raw) : array_values($raw);

        return self::uniqueSortedStrings($list);
    }

    /**
     * Normalize codec groups from an Obfuscator-like map.
     *
     * Input (from Obfuscator::availableGroups()):
     *   [ group => [ phpFunctionName => wrapperName, ... ], ... ]
     * Output:
     *   [ group => [ phpFunctionName, ... ], ... ] // methods sorted/unique; groups sorted
     */
    public static function codecGroupsFromObfuscatorMap(array $raw): array
    {
        $out = [];
        foreach ($raw as $group => $map) {
            if (!is_string($group) || $group === '' || !is_array($map)) {
                continue;
            }
            $methods = array_keys($map);
            $methods = self::uniqueSortedStrings($methods);
            $out[$group] = $methods;
        }

        ksort($out, SORT_STRING);
        return $out;
    }

    /* ------------------------ helpers ------------------------ */

    /** @return string[] */
    private static function uniqueSortedStrings(array $list): array
    {
        $list = array_values(array_filter($list, static fn($v) => is_string($v) && $v !== ''));
        $list = array_values(array_unique(array_map('strval', $list)));
        sort($list, SORT_STRING);
        return $list;
    }
}
```

---
#### 63


` File: src/Services/ErrorReaderService.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Services;

use JsonException;

/**
 * ErrorReaderService
 *
 * Purpose:
 * - Normalize and render readable error/violation entries produced by FortiPlugin scanners.
 * - Provide a catalog of known error slugs with human-friendly names.
 *
 * Inputs supported:
 * - PluginSecurityScanner::getMatches() items (include: type, severity, line, file, + data)
 * - ContentValidator::scanSource()/scanFile() items (include: type, file, line, snippet, issue, ...)
 * - ComposerScan::scan() items (include: type, file, issue, package/version when applicable)
 * - ConfigValidator::validate() results (can be ["error"=>..., "details"=>...])
 */
class ErrorReaderService
{
    /**
     * Turn a raw error/violation array into a normalized, readable shape.
     *
     * Output shape:
     * - slug: string  (original type when present; or derived fallback)
     * - name: string  (human-readable title)
     * - description: string (expanded with best-available details)
     * - severity: string (critical|high|medium|low|info) when available
     * - file: string|null
     * - line: int|null
     * - column: int|null
     * - snippet: string|null
     * - extra: array (original payload for debugging)
     * @throws JsonException
     */
    public function format(array $error): array
    {
        // Some validators return an error summary instead of a typed violation.
        if (!isset($error['type']) && isset($error['error'])) {
            return $this->formatConfigValidatorError($error);
        }

        $slug = (string)($error['type'] ?? 'unknown_error');
        $severity = (string)($error['severity'] ?? ($this->defaultSeverityMap()[$slug] ?? 'high'));
        $file = $error['file'] ?? null;
        $line = isset($error['line']) ? (int)$error['line'] : null;
        $column = isset($error['column']) ? (int)$error['column'] : null;
        $snippet = $error['snippet'] ?? null;

        $catalog = self::catalog();
        $meta = $catalog[$slug] ?? [
            'name' => self::slugToTitle($slug),
            'description' => 'An issue of type "' . $slug . '" was reported.',
        ];

        $name = (string)$meta['name'];
        $descriptionTpl = (string)($meta['description'] ?? '');
        $description = $this->renderTemplate($descriptionTpl, $error);

        // Fallback description using generic fields if template was empty
        if ($description === '' || $description === $descriptionTpl) {
            $issue = (string)($error['issue'] ?? '');
            if ($issue !== '') {
                $description = $issue;
            }
        }

        return [
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'severity' => $severity,
            'file' => $file,
            'line' => $line,
            'column' => $column,
            'snippet' => $snippet,
            'extra' => $error,
        ];
    }

    /**
     * Convenience: format an array of errors.
     * @param array $errors
     * @return array
     * @throws JsonException
     * @throws JsonException
     */
    public function formatMany(array $errors): array
    {
        $out = [];
        foreach ($errors as $e) {
            if (!is_array($e)) continue;
            $out[] = $this->format($e);
        }
        return $out;
    }

    /**
     * List all known error kinds, with name and slug.
     * @return array<array{slug:string,name:string}>
     */
    public function listAllPossibleErrors(): array
    {
        $list = [];
        foreach (self::catalog() as $slug => $meta) {
            $list[] = [
                'slug' => $slug,
                'name' => ($meta['name'] ?? self::slugToTitle($slug)),
            ];
        }
        return $list;
    }

    // -------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------

    private function formatConfigValidatorError(array $error): array
    {
        $name = 'Configuration validation error';
        $description = (string)($error['error'] ?? '');
        $file = isset($error['file']) ? (string)$error['file'] : null;
        $details = $error['details'] ?? [];

        // Try to enrich description with first detail, if available
        if (is_array($details) && count($details) > 0) {
            $d = $details[0];
            $path = $d['path'] ?? '';
            $msg = $d['message'] ?? '';
            if ($msg !== '') {
                $description .= ($description !== '' ? ' — ' : '') . $msg;
            }
            if ($path !== '') {
                $description .= ($description !== '' ? ' ' : '') . "at $path";
            }
        }

        return [
            'slug' => 'config_validation_error',
            'name' => $name,
            'description' => $description,
            'severity' => 'high',
            'file' => $file,
            'line' => null,
            'column' => null,
            'snippet' => null,
            'extra' => $error,
        ];
    }

    private function renderTemplate(string $tpl, array $vars): string
    {
        if ($tpl === '') return '';

        // Simple {key} replacement using root keys of $vars
        return preg_replace_callback('/\{([a-zA-Z0-9_.]+)}/', function ($m) use ($vars) {
            $key = $m[1];
            // support dot access like details.0.message if present
            $value = $this->getDot($vars, $key);
            if ($value === null) return $m[0];
            if (is_scalar($value)) return (string)$value;
            /** @noinspection PhpUnhandledExceptionInspection */
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }, $tpl) ?? $tpl;
    }

    private function getDot(array $arr, string $path)
    {
        $parts = explode('.', $path);
        $cur = $arr;
        foreach ($parts as $p) {
            if (is_array($cur)) {
                if (array_key_exists($p, $cur)) {
                    $cur = $cur[$p];
                    continue;
                }
                // numeric index
                if (ctype_digit($p)) {
                    $i = (int)$p;
                    if (isset($cur[$i])) {
                        $cur = $cur[$i];
                        continue;
                    }
                }
            }
            return null;
        }
        return $cur;
    }

    private static function slugToTitle(string $slug): string
    {
        $title = str_replace(['_', '-'], ' ', $slug);
        return ucwords($title);
    }

    private function defaultSeverityMap(): array
    {
        return [
            'always_forbidden_function' => 'critical',
            'always_forbidden_wrapper_stream' => 'high',
            'always_forbidden_reflection' => 'high',
            'always_forbidden_magic_method' => 'high',
            'always_forbidden_dynamic_include' => 'high',
            'always_forbidden_wrapper_stream_include' => 'high',
            'always_forbidden_callback_to_forbidden_function' => 'high',
            'always_forbidden_obfuscated_eval' => 'critical',
            'return_forbidden_function' => 'critical',
            'return_indirect_forbidden_chain' => 'critical',
            'return_indirect_forbidden_method_chain' => 'critical',
            'function_call_chain_forbidden' => 'critical',
            'include_forbidden_wrapper' => 'critical',
            'backdoor_dynamic_class_instantiation_forbidden' => 'critical',
            'backdoor_dynamic_method_call_forbidden' => 'critical',
            'dynamic_property_access_chain_forbidden' => 'high',
            'reflection_usage' => 'critical',
            'config_dangerous_function' => 'medium',
            'config_risky_function' => 'low',
            'config_blocked_method' => 'high',
            'config_file_too_large' => 'low',
            'forbidden_namespace_import' => 'critical',
            'forbidden_namespace_reference' => 'critical',
            'forbidden_namespace_extends' => 'critical',
            'forbidden_namespace_implements' => 'critical',
            'forbidden_namespace_string_reference' => 'high',
            'blocklist_instantiation' => 'high',
            'blocklist_constructor' => 'high',
            'blocklist_class_reference' => 'high',
            'blocklist_method' => 'high',
            'forbidden_function' => 'high',
            'forbidden_function_assignment' => 'high',
            'unsupported_function_call' => 'medium',
            'unsupported_function' => 'medium',
            'read_error' => 'high',
            'composer_file_missing' => 'high',
            'composer_file_invalid' => 'high',
            'forbidden_package_dependency' => 'high',
            'invalid_token_usage' => 'high',
            'invalid_token_assignment' => 'high',
            'invalid_token_function_argument' => 'high',
            'include_dynamic_path_superglobal' => 'high',
            'include_dynamic_path' => 'high',
            'obfuscation_function' => 'medium',
            'anonymous_class_leak' => 'high',
            'anonymous_function_leak' => 'high',
            'global_or_session_leak' => 'high',
            'static_variable_leak' => 'medium',
            'dynamic_property_access' => 'low',
            'variable_variable_usage' => 'low',
            'magic_method_defined' => 'low',
            'closure_calls_always_forbidden' => 'critical',
            'closure_calls_unsupported' => 'medium',
            'closure_calls_forbidden_chain' => 'critical',
            'callback_always_forbidden' => 'critical',
            'callback_unsupported' => 'medium',
            'callback_user_defined_forbidden_chain' => 'critical',
            'backdoor_dynamic_class_instantiation_superglobal' => 'high',
            'backdoor_dynamic_class_instantiation_unresolved' => 'high',
            'backdoor_dynamic_class_instantiation_complex' => 'high',
            'backdoor_dynamic_method_call_chain_forbidden' => 'critical',
            'dynamic_static_property_access' => 'medium',
            'config_validation_error' => 'high',
            // ValidatorService and scanner orchestration
            'composer.composer_file_missing' => 'high',
            'composer.composer_file_invalid' => 'high',
            'composer.forbidden_package_dependency' => 'high',
            'config.schema' => 'high',
            'config.exception' => 'high',
            'hostconfig.error' => 'high',
            'route.invalid' => 'high',
            'scanner.exception' => 'high',
            'content.exception' => 'high',
            'token.exception' => 'high',
            'ast.exception' => 'high',
            'ast.violation' => 'high',
            'scan.issue' => 'medium',
            'suspicious_filename_unicode' => 'medium',
            'suspicious_double_extension' => 'medium',
            'php_payload_in_non_php' => 'high',
        ];
    }

    /**
     * Catalog of known error slugs with human-readable names and description templates.
     * Placeholders like {function}, {class}, {namespace}, {value}, {expression}, {chain}, etc. are filled from the raw error.
     */
    public static function catalog(): array
    {
        return [
            // Always forbidden
            'always_forbidden_function' => [
                'name' => 'Forbidden function call',
                'description' => 'Call to forbidden function {function}.',
            ],
            'always_forbidden_wrapper_stream' => [
                'name' => 'Forbidden wrapper stream',
                'description' => 'File operation {function} uses forbidden stream wrapper: {value}.',
            ],
            'always_forbidden_reflection' => [
                'name' => 'Reflection usage is forbidden',
                'description' => 'Use of reflection class {class} is not allowed.',
            ],
            'always_forbidden_magic_method' => [
                'name' => 'Forbidden magic method',
                'description' => 'Definition of magic method {method} is not allowed.',
            ],
            'always_forbidden_dynamic_include' => [
                'name' => 'Dynamic include/require',
                'description' => 'Dynamic include/require expression of type {expr_type} is not allowed.',
            ],
            'always_forbidden_wrapper_stream_include' => [
                'name' => 'Include uses forbidden wrapper',
                'description' => 'Including via forbidden stream wrapper: {value}.',
            ],
            'always_forbidden_callback_to_forbidden_function' => [
                'name' => 'Forbidden callback registered',
                'description' => 'Callback {callback} registered via {registration} is forbidden.',
            ],
            'always_forbidden_obfuscated_eval' => [
                'name' => 'Obfuscated eval',
                'description' => 'Obfuscated call: {outer}({inner}(...)).',
            ],

            // Policy/config driven
            'config_dangerous_function' => [
                'name' => 'Dangerous function (policy)',
                'description' => 'Call to dangerous function per policy: {function}.',
            ],
            'config_risky_function' => [
                'name' => 'Risky function (policy)',
                'description' => 'Call to risky function per policy: {function}.',
            ],
            'config_blocked_method' => [
                'name' => 'Blocked static method',
                'description' => 'Blocked method {class}::{method} per policy.',
            ],
            'config_file_too_large' => [
                'name' => 'File exceeds policy size',
                'description' => 'File {file} exceeds maximum size {max_bytes} bytes.',
            ],

            // Returns/indirect
            'return_forbidden_function' => [
                'name' => 'Return/call forbidden function',
                'description' => 'Execution path returns or calls forbidden function {function}.',
            ],
            'return_indirect_forbidden_chain' => [
                'name' => 'Indirect forbidden call chain',
                'description' => 'Indirect call chain reaches forbidden routine: {chain}.',
            ],
            'return_indirect_forbidden_method_chain' => [
                'name' => 'Indirect forbidden method chain',
                'description' => 'Indirect method chain reaches forbidden routine: {chain}.',
            ],
            'function_call_chain_forbidden' => [
                'name' => 'Forbidden function via chain',
                'description' => 'Function participation in forbidden call chain: {function}.',
            ],

            // Namespace issues
            'forbidden_namespace_import' => [
                'name' => 'Forbidden namespace import',
                'description' => 'Import of forbidden namespace or child: {namespace}.',
            ],
            'forbidden_namespace_reference' => [
                'name' => 'Forbidden namespace reference',
                'description' => 'Reference to forbidden namespace/class: {namespace}.',
            ],
            'forbidden_namespace_extends' => [
                'name' => 'Forbidden parent class',
                'description' => 'Class extends forbidden parent: {namespace}.',
            ],
            'forbidden_namespace_implements' => [
                'name' => 'Forbidden interface',
                'description' => 'Class implements forbidden interface: {namespace}.',
            ],
            'forbidden_namespace_string_reference' => [
                'name' => 'Forbidden namespace string',
                'description' => 'Forbidden namespace used as a string: {namespace}.',
            ],

            // ContentValidator namespace variant
            'forbidden_namespace_string' => [
                'name' => 'Forbidden namespace string',
                'description' => 'Forbidden namespace/class referenced as a string.',
            ],

            // ContentValidator tokens/functions
            'invalid_token_usage' => [
                'name' => 'Invalid token usage',
                'description' => 'Direct usage of invalid token {token}.',
            ],
            'invalid_token_assignment' => [
                'name' => 'Invalid token assignment',
                'description' => 'Invalid token {token} assigned to a variable/property.',
            ],
            'invalid_token_function_argument' => [
                'name' => 'Invalid token in function argument',
                'description' => 'Invalid token {token} used as a function argument.',
            ],
            'blocklist_instantiation' => [
                'name' => 'Blocked class instantiation',
                'description' => 'Instantiation of blocked class {token}.',
            ],
            'blocklist_constructor' => [
                'name' => 'Blocked constructor',
                'description' => 'Constructor call {token}::__construct is blocked.',
            ],
            'blocklist_class_reference' => [
                'name' => 'Blocked class reference',
                'description' => 'Reference to blocked class {token}::class.',
            ],
            'blocklist_method' => [
                'name' => 'Blocked method call',
                'description' => 'Blocked method {token}::{method}.',
            ],
            'forbidden_function' => [
                'name' => 'Forbidden function call',
                'description' => 'Call to forbidden function {function}.',
            ],
            'forbidden_function_assignment' => [
                'name' => 'Forbidden function in assignment',
                'description' => 'Forbidden function {function} assigned to a variable/property.',
            ],
            'unsupported_function' => [
                'name' => 'Unsupported/risky function',
                'description' => 'Call to unsupported or risky function {function}.',
            ],

            // Variable/dynamic/concat function call backdoors
            'backdoor_variable_function_call' => [
                'name' => 'Variable function call',
                'description' => 'Variable function call via ${var}.',
            ],
            'backdoor_variable_function_call_chain_forbidden' => [
                'name' => 'Variable function resolves to forbidden',
                'description' => 'Variable function resolves or chains to forbidden function {resolved_function}.',
            ],
            'backdoor_concat_function_call_unknown' => [
                'name' => 'Concatenated function call (unknown)',
                'description' => 'Function name constructed by concatenation: {expression}.',
            ],
            'backdoor_concat_function_call_always_forbidden' => [
                'name' => 'Concatenated function call (forbidden)',
                'description' => 'Concatenated function name resolves to forbidden: {expression}.',
            ],
            'backdoor_concat_function_call_unsupported' => [
                'name' => 'Concatenated function call (unsupported)',
                'description' => 'Concatenated function name resolves to unsupported: {expression}.',
            ],
            'backdoor_concat_function_call_chain_forbidden' => [
                'name' => 'Concatenated function call (forbidden chain)',
                'description' => 'Concatenated function name participates in forbidden chain: {expression}.',
            ],

            // Advanced backdoor/heuristics (PluginSecurityScanner)
            'reflection_usage' => [
                'name' => 'Reflection usage',
                'description' => 'Suspicious reflection usage with class {class}.',
            ],
            'include_forbidden_wrapper' => [
                'name' => 'Include with forbidden wrapper',
                'description' => 'Include/require uses a forbidden stream wrapper: {value}.',
            ],
            'include_dynamic_path_superglobal' => [
                'name' => 'Dynamic include path from superglobal',
                'description' => 'Dynamic include path sourced from a superglobal.',
            ],
            'include_dynamic_path' => [
                'name' => 'Dynamic include path',
                'description' => 'Dynamic include path via expression: {expression}.',
            ],
            'obfuscation_function' => [
                'name' => 'Obfuscation routine',
                'description' => 'Use of known obfuscation routine {function}.',
            ],
            'anonymous_class_leak' => [
                'name' => 'Anonymous class leaks dangerous content',
                'description' => 'Anonymous class contains dangerous content: {dangerous_content}.',
            ],
            'anonymous_function_leak' => [
                'name' => 'Anonymous function leaks dangerous content',
                'description' => 'Anonymous function contains dangerous content: {dangerous_content}.',
            ],
            'global_or_session_leak' => [
                'name' => 'Global/session leak',
                'description' => 'Global or session variable with dangerous content: {dangerous_content}.',
            ],
            'static_variable_leak' => [
                'name' => 'Static variable leak',
                'description' => 'Static variable with dangerous content: {dangerous_content}.',
            ],
            'return_forbidden_class' => [
                'name' => 'Return forbidden class',
                'description' => 'Function/method returns an instance of forbidden class {class}.',
            ],
            'backdoor_dynamic_class_instantiation_superglobal' => [
                'name' => 'Dynamic class instantiation from superglobal',
                'description' => 'Class name derived from superglobal detected in instantiation.',
            ],
            'backdoor_dynamic_class_instantiation_forbidden' => [
                'name' => 'Dynamic class instantiation (forbidden)',
                'description' => 'Dynamic class instantiation for forbidden class {class}.',
            ],
            'backdoor_dynamic_class_instantiation_unresolved' => [
                'name' => 'Dynamic class instantiation (unresolved)',
                'description' => 'Dynamic class instantiation with unresolved class name.',
            ],
            'backdoor_dynamic_class_instantiation_complex' => [
                'name' => 'Dynamic class instantiation (complex)',
                'description' => 'Dynamic class instantiation with complex expression.',
            ],
            'backdoor_dynamic_method_call_forbidden' => [
                'name' => 'Dynamic method call (forbidden)',
                'description' => 'Dynamic method call to forbidden routine on class {class}.',
            ],
            'backdoor_dynamic_method_call_chain_forbidden' => [
                'name' => 'Dynamic method call chain (forbidden)',
                'description' => 'Dynamic call chain reaches forbidden routine on class {class}.',
            ],
            'dynamic_property_access_chain_forbidden' => [
                'name' => 'Dynamic property access chain (forbidden)',
                'description' => 'Dynamic property access chain indicates potential backdoor.',
            ],
            'dynamic_property_access' => [
                'name' => 'Dynamic property access',
                'description' => 'Dynamic property access observed: {expression}.',
            ],
            'variable_variable_usage' => [
                'name' => 'Variable-variable usage',
                'description' => 'Variable-variable usage may indicate dynamic code execution.',
            ],
            'magic_method_defined' => [
                'name' => 'Magic method defined',
                'description' => 'Magic method defined: {method}.',
            ],
            'closure_calls_always_forbidden' => [
                'name' => 'Closure calls forbidden function',
                'description' => 'Closure calls forbidden function {function}.',
            ],
            'closure_calls_unsupported' => [
                'name' => 'Closure calls unsupported function',
                'description' => 'Closure calls unsupported function {function}.',
            ],
            'closure_calls_forbidden_chain' => [
                'name' => 'Closure participates in forbidden chain',
                'description' => 'Closure participates in forbidden call chain for {function}.',
            ],
            'callback_always_forbidden' => [
                'name' => 'Callback calls forbidden function',
                'description' => 'Registered callback calls forbidden function {function}.',
            ],
            'callback_unsupported' => [
                'name' => 'Callback calls unsupported function',
                'description' => 'Registered callback calls unsupported function {function}.',
            ],
            'callback_user_defined_forbidden_chain' => [
                'name' => 'Callback triggers forbidden chain',
                'description' => 'Registered callback triggers forbidden call chain for {function}.',
            ],

            // Composer/config
            'composer_file_missing' => [
                'name' => 'composer.json missing',
                'description' => 'composer.json not found at {file}.',
            ],
            'composer_file_invalid' => [
                'name' => 'composer.json invalid',
                'description' => 'Invalid JSON in composer.json at {file}.',
            ],
            'forbidden_package_dependency' => [
                'name' => 'Forbidden composer package',
                'description' => 'Composer requires forbidden package {package} ({version}).',
            ],

            // Prefixed composer.* variants emitted by ValidatorService
            'composer.composer_file_missing' => [
                'name' => 'composer.json missing',
                'description' => 'composer.json not found at {file}.',
            ],
            'composer.composer_file_invalid' => [
                'name' => 'composer.json invalid',
                'description' => 'Invalid JSON in composer.json at {file}.',
            ],
            'composer.forbidden_package_dependency' => [
                'name' => 'Forbidden composer package',
                'description' => 'Composer requires forbidden package {package} ({version}).',
            ],

            // Filesystem/content
            'read_error' => [
                'name' => 'File read error',
                'description' => 'Unable to read file {file}.',
            ],

            // Scanner pre-flag issues (from FileScanner)
            'suspicious_filename_unicode' => [
                'name' => 'Suspicious filename (Unicode control chars)',
                'description' => 'Filename may contain bidi control characters indicating spoofing.',
            ],
            'suspicious_double_extension' => [
                'name' => 'Suspicious double extension',
                'description' => 'File name looks like a double extension (e.g. .jpg.php or .php.txt).',
            ],
            'php_payload_in_non_php' => [
                'name' => 'PHP payload in non-PHP file',
                'description' => 'PHP code payload detected in a non-PHP file.',
            ],

            // Orchestration/service emitted
            'config.schema' => [
                'name' => 'Config schema violation',
                'description' => 'fortiplugin.json failed schema validation: {issue}',
            ],
            'config.exception' => [
                'name' => 'Config validation exception',
                'description' => 'Exception during config validation: {issue}',
            ],
            'hostconfig.error' => [
                'name' => 'Host config error',
                'description' => 'Host configuration validation error: {issue}',
            ],
            'manifest.invalid' => [
                'name' => 'Permission manifest invalid',
                'description' => 'Permission manifest validation failed: {issue}',
            ],
            'route.invalid' => [
                'name' => 'Route file invalid',
                'description' => 'Route file validation failed: {issue}',
            ],
            'scanner.exception' => [
                'name' => 'Scanner exception',
                'description' => 'File scanner threw an exception: {issue}',
            ],
            'content.exception' => [
                'name' => 'Content validator exception',
                'description' => 'Content validation threw an exception: {issue}',
            ],
            'token.exception' => [
                'name' => 'Token analyzer exception',
                'description' => 'TokenUsageAnalyzer threw an exception: {issue}',
            ],
            'ast.exception' => [
                'name' => 'AST scanner exception',
                'description' => 'AST scanner threw an exception: {issue}',
            ],
            'ast.violation' => [
                'name' => 'AST violation',
                'description' => '{issue}',
            ],
            'scan.issue' => [
                'name' => 'Scan issue',
                'description' => '{issue}',
            ],
        ];
    }
}
```

---
#### 64


` File: src/Services/HostKeyService.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection PhpUnused */

namespace Timeax\FortiPlugin\Services;

use Illuminate\Support\Facades\File;
use JsonException;
use OpenSSLAsymmetricKey;
use RuntimeException;
use SodiumException;
use Timeax\FortiPlugin\Enums\KeyPurpose;
use Timeax\FortiPlugin\Models\HostKey;
use Timeax\FortiPlugin\Support\Encryption;

final class HostKeyService
{
    /**
     * Return the current verifying key (public) for installers.
     * @return array{fingerprint:string, public_pem:string}
     */
    public function currentVerifyKey(string|KeyPurpose $purpose = null): array
    {
        $purpose ?: config('fortiplugin.keys.verify_purpose', 'installer_verify');

        $key = HostKey::query()
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        if (!$key) {
            throw new RuntimeException('No host verify key found (purpose=' . $purpose . ').');
        }

        return [
            'fingerprint' => $key->fingerprint,
            'public_pem' => $key->public_pem,
        ];
    }

    /**
     * Sign arbitrary data with the current signing key.
     * @return array{alg:string,fingerprint:string,signature_b64:string}
     * @throws JsonException
     */
    public function sign(string $data): array
    {
        $purpose = config('fortiplugin.keys.sign_purpose', 'packager_sign');
        $digest = (int)config('fortiplugin.keys.digest', OPENSSL_ALGO_SHA256);

        $key = HostKey::query()
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        if (!$key || empty($key->private_pem)) {
            throw new RuntimeException('No host signing key available (purpose=' . $purpose . ').');
        }

        $privateKey = openssl_pkey_get_private($key->private_pem);
        if (!$privateKey) {
            throw new RuntimeException('Invalid private key in HostKey#' . $key->id);
        }

        $ok = openssl_sign($data, $sigBin, $privateKey, $digest);
        // NOTE: openssl_free_key() is deprecated; let GC handle the resource/object.

        if (!$ok) {
            throw new RuntimeException('Signing failed.');
        }

        return [
            'alg' => (string)config('fortiplugin.keys.algo', 'RS256'),
            'fingerprint' => $key->fingerprint,
            'signature_b64' => Encryption::encrypt(base64_encode($sigBin)),
        ];
    }

    /**
     * Verify a signature using a public key (by fingerprint or provided PEM).
     * @throws JsonException|SodiumException
     */
    public function verify(string $data, string $signatureB64, ?string $fingerprint = null, ?string $publicPem = null): bool
    {
        $sig = base64_decode(Encryption::decrypt($signatureB64), true);
        if ($sig === false) {
            return false;
        }

        if (!$publicPem) {
            if (!$fingerprint) {
                throw new RuntimeException('Either publicPem or fingerprint must be provided for verification.');
            }
            $publicPem = $this->publicByFingerprint($fingerprint);
        }

        $publicKey = openssl_pkey_get_public($publicPem);
        if (!$publicKey) {
            return false;
        }

        $digest = (int)config('fortiplugin.keys.digest', OPENSSL_ALGO_SHA256);
        $res = openssl_verify($data, $sig, $publicKey, $digest);

        return $res === 1; // 1 = valid, 0 = invalid, -1 = error
    }

    public function generate(string $purpose): HostKey
    {
        $bits = (int)config('fortiplugin.keys.bits', 2048);
        $cnf = config('fortiplugin.keys.openssl_cnf') ?: $this->resolveOpensslConfigPath();

        // try with configured bits
        $res = $this->tryMakeKey([
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'config' => $cnf ?: null,
        ]);
        if ($res === false) {
            $errs1 = $this->collectOpenSslErrors();

            // retry with 2048 (Windows builds can reject larger sizes)
            $res = $this->tryMakeKey([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
                'config' => $cnf ?: null,
            ]);
            if ($res === false) {
                $errs2 = $this->collectOpenSslErrors();
                $msg = "Unable to generate RSA keypair. "
                    . ($cnf ? "Tried config: $cnf. " : "")
                    . ($errs1 ? '[pass1] ' . implode(' | ', $errs1) . '. ' : '')
                    . ($errs2 ? '[pass2] ' . implode(' | ', $errs2) . '. ' : '');
                throw new RuntimeException(rtrim($msg));
            }
        }

        // export with a temporary OPENSSL_CONF / RANDFILE
        $privatePem = '';
        $exportOk = $this->withOpenSslEnv($cnf, function () use ($res, &$privatePem) {
            // avoid encryption to reduce RNG/config needs
            $args = ['config' => getenv('OPENSSL_CONF') ?: null, 'encrypt_key' => false];
            return @openssl_pkey_export($res, $privatePem, null, array_filter($args));
        });
        if (!$exportOk) {
            $errs = $this->collectOpenSslErrors();
            throw new RuntimeException('Unable to export private key. ' . implode(' | ', $errs));
        }

        $details = @openssl_pkey_get_details($res);
        if (!$details || empty($details['key'])) {
            throw new RuntimeException('Unable to extract public key.');
        }
        $publicPem = $details['key'];
        $fingerprint = $this->fingerprint($publicPem);

        return HostKey::create([
            'purpose' => $purpose,
            'public_pem' => $publicPem,
            'private_pem' => $privatePem,
            'fingerprint' => $fingerprint,
        ]);
    }

    private function tryMakeKey(array $args): OpenSSLAsymmetricKey|false
    {
        return @openssl_pkey_new(array_filter($args, static fn($v) => $v !== null));
    }

    private function collectOpenSslErrors(): array
    {
        $out = [];
        while ($e = openssl_error_string()) {
            $out[] = $e;
        }
        return $out;
    }

    /**
     * Temporarily sets OPENSSL_CONF (if provided) and a writable RANDFILE on Windows,
     * runs $fn, then restores the environment.
     */
    private function withOpenSslEnv(?string $cnf, callable $fn)
    {
        $restore = [];

        if ($cnf && is_file($cnf)) {
            $restore['OPENSSL_CONF'] = getenv('OPENSSL_CONF') !== false ? getenv('OPENSSL_CONF') : null;
            putenv('OPENSSL_CONF=' . $cnf);
        }

        // Windows-only: ensure a writable RANDFILE to satisfy configs referencing it
        if (DIRECTORY_SEPARATOR === '\\') {
            $hadRand = getenv('RANDFILE') !== false;
            if (!$hadRand || !is_file((string)getenv('RANDFILE'))) {
                $randPath = storage_path('app/forti/openssl/randseed.rnd');
                // Idempotent: no warnings if directory already exists
                File::ensureDirectoryExists(dirname($randPath), 0777);
                // Create the file atomically if missing (no error if it already exists)
                $this->ensureFileExistsAtomic($randPath);
                $restore['RANDFILE'] = $hadRand ? getenv('RANDFILE') : null;
                putenv('RANDFILE=' . $randPath);
            }
        }

        try {
            return $fn();
        } finally {
            foreach ($restore as $k => $v) {
                if ($v === null) {
                    // unset
                    putenv($k);
                } else {
                    putenv($k . '=' . $v);
                }
            }
        }
    }

    /** Ensure a file exists without race-condition warnings (atomic create). */
    private function ensureFileExistsAtomic(string $path): void
    {
        clearstatcache(true, $path);
        if (is_file($path)) {
            return;
        }

        $dir = dirname($path);
        File::ensureDirectoryExists($dir, 0777);

        // Try atomic create; if another process wins, this returns false but that's fine.
        $h = @fopen($path, 'xb');
        if ($h !== false) {
            fclose($h);
        } elseif (!is_file($path)) {
            // If it still doesn't exist (other error), best-effort create.
            @touch($path);
        }

        // Ensure writable (best-effort; ignores failures)
        if (is_file($path) && !is_writable($path)) {
            @chmod($path, 0666 & ~umask());
        }
    }

    /** Best-effort openssl.cnf discovery for Windows PHP bundles. */
    private function resolveOpensslConfigPath(): ?string
    {
        $env = getenv('OPENSSL_CONF');
        if ($env && is_file($env)) return $env;

        $candidates = [
            dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf',
            dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'openssl.cnf',
        ];
        foreach ($candidates as $p) {
            if (is_file($p)) return $p;
        }
        return null;
    }

    /** Mark current key rotated and generate a new one. */
    public function rotate(string $purpose): HostKey
    {
        $current = HostKey::query()
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        if ($current && !$current->rotated_at) {
            $current->rotated_at = now();
            $current->save();
        }

        return $this->generate($purpose);
    }

    // ---- internals ----

    private function publicByFingerprint(string $fp): string
    {
        $key = HostKey::query()->where('fingerprint', $fp)->first();
        if (!$key) {
            throw new RuntimeException('HostKey not found for fingerprint ' . $fp);
        }
        return $key->public_pem;
    }

    /** SHA-256 over DER SubjectPublicKeyInfo bytes (stable fingerprint). */
    public function fingerprint(string $publicPem): string
    {
        $der = $this->pemToDer($publicPem);
        return hash('sha256', $der);
    }

    private function pemToDer(string $pem): string
    {
        $clean = preg_replace('/-----BEGIN PUBLIC KEY-----|-----END PUBLIC KEY-----|\s+/', '', $pem);
        $bin = base64_decode($clean, true);
        if ($bin === false) {
            throw new RuntimeException('Invalid PEM format.');
        }
        return $bin;
    }
}
```

---
#### 65


` File: src/Services/PolicyService.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Services;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use JsonException;
use RuntimeException;
use Timeax\FortiPlugin\Core\PluginPolicy;

final readonly class PolicyService
{
    public function __construct(private Filesystem $fs)
    {
    }

    /** Canonical policy snapshot (normalized)
     * @throws JsonException|FileNotFoundException
     */
    public function snapshot(): array
    {
        $raw = $this->loadRaw();        // array in legacy or new shape
        $snap = $this->normalize($raw); // canonical shape for API
        return $this->ensureVersion($snap);
    }

    /**
     * @throws FileNotFoundException
     * @throws JsonException
     */
    public function version(): string
    {
        return (string)Arr::get($this->snapshot(), 'version', '1');
    }

    /**
     * @throws JsonException|FileNotFoundException
     */
    public function hash(): string
    {
        return hash('sha256', json_encode($this->snapshot(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @throws FileNotFoundException
     * @throws JsonException
     */
    public function makePolicy(): PluginPolicy
    {
        return new PluginPolicy($this->snapshot());
    }

    // ---------------------------------------------------------------------
    // Loading
    // ---------------------------------------------------------------------

    /**
     * @throws FileNotFoundException
     * @throws JsonException
     */
    private function loadRaw(): array
    {
        // 1) Inline config (already a PHP array under config/fortiplugin.php)
        $cfg = config('fortiplugin.policy');
        if (is_array($cfg)) {
            return $cfg;
        }

        // 2) Legacy PHP file that returns an array (exactly the shape you pasted)
        $phpPath = (string)config('fortiplugin.policy_php_path', '');
        if ($phpPath !== '' && $this->fs->exists($phpPath)) {
            $arr = include $phpPath;
            if (!is_array($arr)) {
                throw new RuntimeException("Policy PHP file did not return an array: $phpPath");
            }
            return $arr;
        }

        // 3) JSON file
        $jsonPath = (string)config('fortiplugin.policy_path', '');
        if ($jsonPath !== '' && $this->fs->exists($jsonPath)) {
            $json = json_decode($this->fs->get($jsonPath), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($json)) {
                throw new RuntimeException("Policy JSON invalid: $jsonPath");
            }
            return $json;
        }

        // 4) Fallback defaults
        return $this->fallback();
    }

    // ---------------------------------------------------------------------
    // Normalization (legacy → canonical)
    // ---------------------------------------------------------------------

    private function normalize(array $raw): array
    {
        // Legacy detection: top-level 'validator' array
        $legacy = is_array(Arr::get($raw, 'validator'));

        if (!$legacy) {
            // Assume it's already canonical; still ensure consistent keys
            return $this->canonicalize($raw);
        }

        // Map legacy keys to canonical structure
        $validator = (array)$raw['validator'];

        $canonical = [
            // Host loader & layout
            'directory' => Arr::get($raw, 'directory', 'Plugins'),
            'loader' => Arr::get($raw, 'loader', 'default'),
            'stack_depth' => Arr::get($raw, 'stack_depth', 1),

            // Scanner rules (flattened from validator.*)
            'tokens' => array_values(array_unique((array)Arr::get($validator, 'tokens', []))),
            'ignore' => array_values((array)Arr::get($validator, 'ignore', [])),
            'whitelist' => array_values((array)Arr::get($validator, 'whitelist', [])),
            'blocklist' => (array)Arr::get($validator, 'blocklist', []),
            'dangerous_functions' => array_values(array_unique((array)Arr::get($validator, 'dangerous_functions', []))),
            'scan_size' => (array)Arr::get($validator, 'scan_size', ['php' => 5000000]),
            'max_flagged' => (int)Arr::get($validator, 'max_flagged', 5),

            // Compliance/Admin knobs (pass through)
            'security' => [
                'must_kyc' => (bool)Arr::get($raw, 'must_kyc', false),
            ],
            'publishing' => [
                'max_token_lifetime_days' => (int)Arr::get($raw, 'max_token_lifetime_days', 30),
                'allow_public_plugins' => (bool)Arr::get($raw, 'allow_public_plugins', false),
                'require_plugin_review' => (bool)Arr::get($raw, 'require_plugin_review', true),
            ],
            'admin' => [
                'allow_admin_override' => (bool)Arr::get($raw, 'allow_admin_override', true),
            ],

            // Preserve full legacy blob for traceability
            '_legacy' => $raw,
        ];

        // Ensure no duplicates in danger lists
        $canonical['dangerous_functions'] = array_values(array_unique($canonical['dangerous_functions']));
        $canonical['tokens'] = array_values(array_unique($canonical['tokens']));

        return $canonical;
    }

    /** If caller already provides canonical shape, ensure keys & defaults. */
    private function canonicalize(array $p): array
    {
        $p['directory'] = $p['directory'] ?? 'Plugins';
        $p['loader'] = $p['loader'] ?? 'default';
        $p['stack_depth'] = $p['stack_depth'] ?? 1;
        $p['tokens'] = array_values(array_unique((array)($p['tokens'] ?? [])));
        $p['ignore'] = array_values((array)($p['ignore'] ?? []));
        $p['whitelist'] = array_values((array)($p['whitelist'] ?? []));
        $p['blocklist'] = (array)($p['blocklist'] ?? []);
        $p['dangerous_functions'] = array_values(array_unique((array)($p['dangerous_functions'] ?? [])));
        $p['scan_size'] = (array)($p['scan_size'] ?? ['php' => 5000000]);
        $p['max_flagged'] = (int)($p['max_flagged'] ?? 5);
        return $p;
    }

    /**
     * @throws JsonException
     */
    private function ensureVersion(array $policy): array
    {
        if (!isset($policy['version'])) {
            $policy['version'] = substr(hash('sha256',
                json_encode($policy, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ), 0, 12);
        }
        return $policy;
    }

    private function fallback(): array
    {
        return [
            'directory' => 'Plugins',
            'loader' => 'default',
            'stack_depth' => 1,
            'tokens' => ['file_get_contents', 'fopen', 'fwrite', 'fread', 'rename', 'copy', 'scandir', 'glob'],
            'ignore' => [],
            'whitelist' => [],
            'blocklist' => ['DB' => ['transactions', 'rollback', 'commit'], 'File' => ['exists'], 'Storage' => []],
            'dangerous_functions' => ['eval', 'exec', 'shell_exec', 'system', 'passthru', 'unlink'],
            'scan_size' => ['php' => 5000000],
            'max_flagged' => 5,
            'security' => ['must_kyc' => false],
            'publishing' => [
                'max_token_lifetime_days' => 30,
                'allow_public_plugins' => false,
                'require_plugin_review' => true,
            ],
            'admin' => ['allow_admin_override' => true],
        ];
    }

    /**
     * Metadata for caching: strong ETag + last-modified (when available).
     * @return array{etag:string;last_modified:?string,version:string,hash:string,source:string}
     * @throws FileNotFoundException
     * @throws JsonException
     */
    public function meta(): array
    {
        $snap = $this->snapshot();
        $hash = $this->hash();               // sha256 over normalized policy JSON
        $etag = '"forti-' . $hash . '"';     // strong ETag

        $src = $this->detectSource();       // ['type'=>..., 'path'=>..., 'mtime'=>?int]
        $lm = $src['mtime'] ? gmdate('D, d M Y H:i:s', $src['mtime']) . ' GMT' : null;

        return [
            'etag' => $etag,
            'last_modified' => $lm,
            'version' => (string)Arr::get($snap, 'version', '1'),
            'hash' => $hash,
            'source' => $src['type'],
        ];
    }

    // -------------------- internals --------------------

    private function detectSource(): array
    {
        // 1) Inline config
        if (is_array(config('fortiplugin.policy'))) {
            return ['type' => 'config', 'path' => null, 'mtime' => null];
        }

        // 2) Legacy PHP file
        $phpPath = (string)config('fortiplugin.policy_php_path', '');
        if ($phpPath !== '' && $this->fs->exists($phpPath)) {
            return ['type' => 'php', 'path' => $phpPath, 'mtime' => @filemtime($phpPath) ?: null];
        }

        // 3) JSON file
        $jsonPath = (string)config('fortiplugin.policy_path', '');
        if ($jsonPath !== '' && $this->fs->exists($jsonPath)) {
            return ['type' => 'json', 'path' => $jsonPath, 'mtime' => @filemtime($jsonPath) ?: null];
        }

        // 4) Fallback
        return ['type' => 'fallback', 'path' => null, 'mtime' => null];
    }
}
```

---
#### 66


` File: src/Services/ValidatorService.php`  [↑ Back to top](#index)

```php
<?php /** @noinspection GrazieInspection */
/** @noinspection PhpUnusedParameterInspection */
/** @noinspection PhpUnused */
/** @noinspection PhpUnusedLocalVariableInspection */

declare(strict_types=1);

namespace Timeax\FortiPlugin\Services;

use Illuminate\Support\Arr;
use RuntimeException;
use Throwable;
use Timeax\FortiPlugin\Core\PluginPolicy;
use Timeax\FortiPlugin\Core\Security\ComposerScan;
use Timeax\FortiPlugin\Core\Security\ConfigValidator;
use Timeax\FortiPlugin\Core\Security\ContentValidator;
use Timeax\FortiPlugin\Core\Security\FileScanner;
use Timeax\FortiPlugin\Core\Security\HostConfigValidator;
use Timeax\FortiPlugin\Core\Security\PermissionManifestValidator;
use Timeax\FortiPlugin\Core\Security\PluginSecurityScanner;
use Timeax\FortiPlugin\Core\Security\RouteFileValidator;
use Timeax\FortiPlugin\Core\Security\RouteIdRegistry;
use Timeax\FortiPlugin\Core\Security\TokenUsageAnalyzer;

/**
 * ValidatorService — Orchestrates headline and scanner-driven validations with telemetry and no hard stops.
 *
 * Config keys (all optional):
 *   headline:
 *     composer_json: string|null               Path to composer.json (defaults to <root>/composer.json)
 *     forti_schema: string|null                Path to fortiplugin.json schema (if set, runs ConfigValidator)
 *     host_config: array|null                  Host config array for HostConfigValidator
 *     permission_manifest: string|array|null   Path to manifest.json or decoded array
 *     route_files: array<int,string>           List of JSON route files to validate for unique IDs
 *
 *   scan:
 *     token_list: array<int,string>            Forbidden tokens for TokenUsageAnalyzer (defaults from policy->getForbiddenFunctions())
 *
 *   fail_policy:
 *     types_blocklist: array<int,string>       If any issue type is in this set → fail
 *     severity_threshold: string|null          Not used by current validators but accepted for future
 *     total_error_limit: int|null              If total issues exceed → fail
 *     per_type_limits: array<string,int>       Map of type => max allowed before fail
 *     file_gates: array<int,string>            fnmatch globs; any issue whose file matches → fail
 */
final class ValidatorService
{
    private PluginPolicy $policy;
    private array $config;

    /** @var list<array{0:string,1:string,2:string|null}> */
    private array $log = [];

    /** Extended log items (optional richer fields) */
    private array $extended = [];

    /** Running counters per phase/validator key */
    private array $counters = [];

    /**
     * Last registered emit callback.
     * @var null|callable
     */
    private $emit;

    /**
     * Validator aliases map for setIgnoredValidators.
     * @var array<string,string>
     */
    private array $aliasMap = [
        'composer' => ComposerScan::class,
        'config' => ConfigValidator::class,
        'host' => HostConfigValidator::class,
        'host_config' => HostConfigValidator::class,
        'permission_manifest' => PermissionManifestValidator::class,
        'manifest' => PermissionManifestValidator::class,
        'route' => RouteFileValidator::class,
        'routes' => RouteFileValidator::class,
        'file_scanner' => FileScanner::class,
        'content' => ContentValidator::class,
        'content_validator' => ContentValidator::class,
        'token' => TokenUsageAnalyzer::class,
        'token_usage' => TokenUsageAnalyzer::class,
        'token_analyzer' => TokenUsageAnalyzer::class,
        'ast' => PluginSecurityScanner::class,
        'ast_scanner' => PluginSecurityScanner::class,
    ];

    /**
     * Normalized set of ignored validators (aliases and FQCNs, all lowercase)
     * @var array<string,bool>
     */
    private array $ignored = [];

    private array $stats = [
        'files_scanned' => 0,
        'total_errors' => 0,
    ];

    public function __construct(PluginPolicy $policy, array $config = [])
    {
        $this->policy = $policy;
        $this->config = $config;
    }

    /**
     * Configure validators to ignore by alias or FQCN. Returns $this for chaining.
     * Example: setIgnoredValidators(['config', ConfigValidator::class])
     */
    public function setIgnoredValidators(array $validators): self
    {
        $ignored = [];
        foreach ($validators as $v) {
            if (!is_string($v) || $v === '') continue;
            $key = strtolower($v);
            $ignored[$key] = true;
            // also map known aliases to their class and vice versa
            if (isset($this->aliasMap[$key])) {
                $ignored[strtolower($this->aliasMap[$key])] = true;
            }
            // and if it's a FQCN that matches an alias, add that alias too
            foreach ($this->aliasMap as $alias => $class) {
                if (strcasecmp($class, $v) === 0) {
                    $ignored[strtolower($alias)] = true;
                }
            }
        }
        $this->ignored = $ignored;
        return $this;
    }

    private function isIgnored(string $alias, string $class): bool
    {
        if ($this->ignored === []) return false;
        $alias = strtolower($alias);
        $class = strtolower($class);
        return isset($this->ignored[$alias]) || isset($this->ignored[$class]);
    }

    public function run(string $root, ?callable $emit = null): array
    {
        $this->reset($emit);
        $root = rtrim($root, "\\/");

        $this->emitEvent('Initialize', 'Starting validation pipeline', null, null, null);

        // Headline phase
        $this->emitEvent('Headline', 'Starting headline validators', null, null, null);
        $this->runHeadline($root);
        $this->emitEvent('Headline', 'Completed headline validators', null, null, null);

        // Scanner phase
        $this->emitEvent('Scan', 'Starting file scan', null, null, null);
        $this->runScanner($root);
        $this->emitEvent('Scan', 'Completed file scan', null, null, null);

        // Finalize
        $summary = [
            'files_scanned' => $this->stats['files_scanned'],
            'total_issues' => $this->stats['total_errors'],
            'should_fail' => $this->shouldFail(),
            'log' => $this->log,
            'extended' => $this->extended,
            'formatted' => $this->getFormattedLog(),
        ];

        $this->emitEvent('Finalize', 'Validation complete', [
            'detail' => 'Summary',
            'count' => $this->stats['total_errors'],
        ], null, null);

        return $summary;
    }

    /** Canonical error tuple log accessor */
    public function getLog(): array
    {
        return $this->log;
    }

    /** Return human-friendly, formatted log entries using ErrorReaderService */
    public function getFormattedLog(): array
    {
        try {
            return (new ErrorReaderService())->formatMany($this->extended);
        } catch (Throwable $e) {
            // Never throw; degrade to minimal tuples with message
            $out = [];
            foreach ($this->extended as $raw) {
                if (is_array($raw)) {
                    $out[] = [
                        'slug' => (string)($raw['type'] ?? 'unknown_error'),
                        'name' => 'Issue',
                        'description' => (string)($raw['issue'] ?? ($raw['message'] ?? '')),
                        'severity' => 'high',
                        'file' => $raw['file'] ?? null,
                        'line' => $raw['line'] ?? null,
                        'column' => $raw['column'] ?? null,
                        'snippet' => $raw['snippet'] ?? null,
                        'extra' => $raw,
                    ];
                }
            }
            return $out;
        }
    }

    /** Compute shouldFail decision based on accumulated logs and config policy */
    public function shouldFail(): bool
    {
        $policy = (array)($this->config['fail_policy'] ?? []);
        $typesBlock = array_map('strval', (array)($policy['types_blocklist'] ?? []));
        $totalLimit = $policy['total_error_limit'] ?? null;
        $perTypeLimits = (array)($policy['per_type_limits'] ?? []);
        $fileGates = (array)($policy['file_gates'] ?? []);

        // Build counts per type
        $byType = [];
        foreach ($this->log as [$type, $_issue, $_file]) {
            $byType[$type] = ($byType[$type] ?? 0) + 1;
            // Type blocklist
            if (in_array($type, $typesBlock, true)) {
                return true;
            }
        }

        // Total limit
        if (is_int($totalLimit) && $totalLimit >= 0 && count($this->log) > $totalLimit) {
            return true;
        }

        // Per type limits
        foreach ($perTypeLimits as $t => $limit) {
            if (is_int($limit) && $limit >= 0 && ($byType[$t] ?? 0) > $limit) {
                return true;
            }
        }

        // File gates
        if ($fileGates) {
            foreach ($this->log as [$type, $issue, $file]) {
                $file = (string)$file;
                foreach ($fileGates as $glob) {
                    if (is_string($glob) && $glob !== '' && fnmatch($glob, $file)) {
                        return true;
                    }
                }
            }
        }

        // Optional: severity threshold (not used yet as validators do not emit severities consistently)
        return false;
    }

    // ───────────────────────────── Internals ─────────────────────────────

    private function reset(?callable $emit): void
    {
        $this->log = [];
        $this->extended = [];
        $this->counters = [];
        $this->stats = ['files_scanned' => 0, 'total_errors' => 0];
        $this->emit = $emit;
    }

    private function runHeadline(string $root): void
    {
        // Composer
        if (!$this->isIgnored('composer', ComposerScan::class)) {
            try {
                $composerPath = $this->config['headline']['composer_json'] ?? ($root . DIRECTORY_SEPARATOR . 'composer.json');
                $scanner = new ComposerScan($this->policy);
                $violations = $scanner->scan($composerPath);
                foreach ($violations as $v) {
                    $this->record('composer.' . ($v['type'] ?? 'violation'), (string)($v['issue'] ?? 'Composer violation'), (string)($v['file'] ?? $composerPath), $v);
                    $this->emitEvent('Headline: Composer', $v['issue'] ?? 'Violation', $this->errorCounter('Headline: Composer', $v['issue'] ?? ''), (string)($v['file'] ?? $composerPath), null);
                }
            } catch (Throwable $e) {
                $this->record('composer.exception', $e->getMessage(), $root . DIRECTORY_SEPARATOR . 'composer.json', ['exception' => $e]);
                $this->emitEvent('Headline: Composer', 'Exception', $this->errorCounter('Headline: Composer', $e->getMessage()), null, null);
            }
        }

        // Config schema (fortiplugin.json)
        $schema = $this->config['headline']['forti_schema'] ?? null;
        if (is_string($schema) && $schema !== '' && !$this->isIgnored('config', ConfigValidator::class)) {
            try {
                $cv = new ConfigValidator();
                $res = $cv->validate($root, $schema);
                if (($res['error'] ?? null) !== null) {
                    $details = (array)($res['details'] ?? []);
                    if (!$details) {
                        $this->record('config.schema', (string)$res['error'], $root . DIRECTORY_SEPARATOR . 'fortiplugin.json', $res);
                        $this->emitEvent('Headline: Config', (string)$res['error'], $this->errorCounter('Headline: Config', (string)$res['error']), $root . DIRECTORY_SEPARATOR . 'fortiplugin.json', null);
                    } else {
                        foreach ($details as $d) {
                            $msg = ($d['path'] ?? '') . ' ' . ($d['message'] ?? 'Schema error');
                            $this->record('config.schema', $msg, $root . DIRECTORY_SEPARATOR . 'fortiplugin.json', $d);
                            $this->emitEvent('Headline: Config', $msg, $this->errorCounter('Headline: Config', $msg), $root . DIRECTORY_SEPARATOR . 'fortiplugin.json', null);
                        }
                    }
                }
            } catch (Throwable $e) {
                $this->record('config.exception', $e->getMessage(), $root . DIRECTORY_SEPARATOR . 'fortiplugin.json', ['exception' => $e]);
                $this->emitEvent('Headline: Config', 'Exception', $this->errorCounter('Headline: Config', $e->getMessage()), null, null);
            }
        }

        // Host config (array provided by caller)
        $hostCfg = $this->config['headline']['host_config'] ?? null;
        if (is_array($hostCfg) && !$this->isIgnored('host_config', HostConfigValidator::class)) {
            try {
                HostConfigValidator::validate($hostCfg);
            } catch (Throwable $e) {
                $this->record('hostconfig.error', $e->getMessage(), '[host-config]', ['exception' => $e]);
                $this->emitEvent('Headline: HostConfig', $e->getMessage(), $this->errorCounter('Headline: HostConfig', $e->getMessage()), null, null);
            }
        }

        // Permission manifest (path or array)
        $perm = $this->config['headline']['permission_manifest'] ?? null;
        if ($perm !== null && !$this->isIgnored('permission_manifest', PermissionManifestValidator::class)) {
            try {
                $pmv = new PermissionManifestValidator();
                // validate() throws on errors; we convert to log via catch
                if (is_string($perm)) {
                    $json = @file_get_contents($perm);
                    if ($json === false) {
                        throw new RuntimeException("Cannot read permission manifest: $perm");
                    }
                    $pmv->validate($json);
                } else {
                    $pmv->validate((array)$perm);
                }
            } catch (Throwable $e) {
                $this->record('manifest.invalid', $e->getMessage(), is_string($perm) ? $perm : '[manifest]', ['exception' => $e]);
                $this->emitEvent('Headline: Permission manifest', $e->getMessage(), $this->errorCounter('Headline: Permission manifest', $e->getMessage()), is_string($perm) ? $perm : null, null);
            }
        }

        // Route files (validate IDs + JSON structure)
        $routeFiles = (array)($this->config['headline']['route_files'] ?? []);
        if ($routeFiles && !$this->isIgnored('route', RouteFileValidator::class)) {
            $registry = new RouteIdRegistry();
            foreach ($routeFiles as $rf) {
                try {
                    RouteFileValidator::validateFile($rf, $registry);
                } catch (Throwable $e) {
                    $this->record('route.invalid', $e->getMessage(), (string)$rf, ['exception' => $e]);
                    $this->emitEvent('Headline: Route file', $e->getMessage(), $this->errorCounter('Headline: Route file', $e->getMessage()), (string)$rf, null);
                }
            }
        }
    }

    private function runScanner(string $root): void
    {
        if ($this->isIgnored('file_scanner', FileScanner::class)) {
            return; // skip entire scanning phase
        }
        $scanner = new FileScanner($this->policy);
        $contentValidator = new ContentValidator($this->policy);

        $emitProxy = function (array $e): void {
            // Bridge from FileScanner emit to requested emit schema
            $title = $e['title'] ?? 'Scan';
            $desc = $e['message'] ?? null;
            $file = $e['path'] ?? null;
            //--- check for extra properties
            $extra = array_filter($e, static fn($key) => Arr::has(['file', 'message', 'path'], $key), ARRAY_FILTER_USE_KEY);
            //---
            $this->emitEvent($title, $desc, null, is_string($file) ? $file : null, null, $extra);
        };

        $callback = function (string $file, array $meta = []) use ($contentValidator): array {
            $this->emitEvent('Scan: File', 'Start', null, $file, $this->safeFilesize($file));
            $issues = [];

            // ContentValidator (fast regex-like)
            if (!$this->isIgnored('content', ContentValidator::class)) {
                try {
                    $cv = $contentValidator->scanFile($file);
                    foreach ($cv as $v) {
                        $issues[] = $v;
                    }
                } catch (Throwable $e) {
                    $issues[] = ['type' => 'content.exception', 'issue' => $e->getMessage(), 'file' => $file];
                }
            }

            // TokenUsageAnalyzer (token_get_all based)
            if (!$this->isIgnored('token', TokenUsageAnalyzer::class)) {
                try {
                    $tokens = $this->config['scan']['token_list'] ?? null;
                    if (!is_array($tokens) || !$tokens) {
                        $tokens = $this->policy->getForbiddenFunctions();
                    }
                    $tu = TokenUsageAnalyzer::analyzeFile($file, array_map('strtolower', $tokens));
                    foreach ($tu as $v) {
                        $issues[] = $v;
                    }
                } catch (Throwable $e) {
                    $issues[] = ['type' => 'token.exception', 'issue' => $e->getMessage(), 'file' => $file];
                }
            }

            // PluginSecurityScanner (AST)
            if (!$this->isIgnored('ast', PluginSecurityScanner::class)) {
                try {
                    $src = @file_get_contents($file);
                    if ($src !== false) {
                        $astScanner = new PluginSecurityScanner($this->policy->getConfig(), $file);
                        $astScanner->scanSource($src, $file);
                        foreach ($astScanner->getMatches() as $match) {
                            $issues[] = [
                                'type' => (string)($match['type'] ?? 'ast.violation'),
                                'issue' => (string)($match['message'] ?? ($match['data']['message'] ?? 'AST violation')),
                                'file' => $file,
                                'line' => $match['line'] ?? null,
                            ];
                        }
                    }
                } catch (Throwable $e) {
                    $issues[] = ['type' => 'ast.exception', 'issue' => $e->getMessage(), 'file' => $file];
                }
            }

            // Log+emit
            foreach ($issues as $v) {
                $type = (string)($v['type'] ?? 'scan.issue');
                $issue = (string)($v['issue'] ?? ($v['message'] ?? 'Issue'));
                $this->record($type, $issue, (string)($v['file'] ?? $file), $v);
                $this->emitEvent('Scan: Security', $issue, $this->errorCounter('Scan: Security', $issue), $file, $this->safeFilesize($file));
            }

            $this->stats['files_scanned']++;
            $this->emitEvent('Scan: File', 'End', null, $file, $this->safeFilesize($file));

            return $issues; // return to allow FileScanner to collect, though we do our own logging
        };

        // Drive scanner
        try {
            $scanner->scan($root, $callback, $emitProxy);
        } catch (Throwable $e) {
            // Even FileScanner threw; log and continue finalize
            $this->record('scanner.exception', $e->getMessage(), $root, ['exception' => $e]);
            $this->emitEvent('Scan', 'Scanner exception', $this->errorCounter('Scan', $e->getMessage()), $root, null);
        }
    }

    private function record(string $type, string $issue, ?string $file, array $extended = []): void
    {
        $this->log[] = [$type, $issue, $file];
        $this->extended[] = $extended + ['type' => $type, 'issue' => $issue, 'file' => $file];
        $this->stats['total_errors']++;
    }

    private function emitEvent(string $title, ?string $description, ?array $error, ?string $filePath, ?int $size, ?array $meta = []): void
    {
        if (!$this->emit) {
            return;
        }
        $payload = [
            'title' => $title,
            'description' => $description,
            'error' => $error,
            'stats' => [
                'filePath' => $filePath,
                'size' => $size,
            ],
            'meta' => $meta
        ];
        try {
            ($this->emit)($payload);
        } catch (Throwable $_) { /* never throw */
        }
    }

    private function errorCounter(string $counterKey, string $detail): array
    {
        $this->counters[$counterKey] = ($this->counters[$counterKey] ?? 0) + 1;
        return ['detail' => $detail, 'count' => $this->counters[$counterKey]];
    }

    private function safeFilesize(?string $file): ?int
    {
        if (!$file || !is_file($file)) return null;
        $s = @filesize($file);
        return $s === false ? null : $s;
    }

    /**
     * Public entry to run only the file scanning phase with a provided emitter.
     * Ensures headline validators are ignored; guarantees the scanner stack runs.
     * Restores previous state afterwards.
     */
    public function runFileScan(string $root, callable $emit): void
    {
        $prevEmit = $this->emit;
        $prevIgnored = $this->ignored; // snapshot current ignore set
        $this->emit = $emit;

        try {
            // Headline validators to keep ignored during a pure file scan
            $headline = [
                'composer',
                'config',
                'host',
                'host_config',
                'permission_manifest',
                'manifest',
                'route',
                'routes',
            ];

            // Preserve caller's existing ignores (aliases + FQCNs)…
            $keep = array_keys($prevIgnored); // already lowercase

            // …but make sure the scanning stack is ENABLED (never ignored)
            $scannerAllow = array_map('strtolower', [
                'file_scanner',
                FileScanner::class,
                'content',
                'content_validator',
                ContentValidator::class,
                'token',
                'token_usage',
                'token_analyzer',
                TokenUsageAnalyzer::class,
                'ast',
                'ast_scanner',
                PluginSecurityScanner::class,
            ]);

            // Build the final ignore list: keep previous + force headline ignores,
            // then remove anything from the scannerAllow set.
            $targetIgnores = array_values(array_diff(
                array_unique(array_merge($keep, $headline)),
                $scannerAllow
            ));

            // Apply ignores (aliases ↔ FQCN normalization handled internally)
            $this->setIgnoredValidators($targetIgnores);

            // Run the scanner phase only
            $this->runScanner(rtrim($root, "\\/"));
        } finally {
            // Restore previous state
            $this->ignored = $prevIgnored;
            $this->emit = $prevEmit;
        }
    }
}
```

---
#### 67


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
#### 68


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
#### 69


` File: src/Support/PluginContext.php`  [↑ Back to top](#index)

```php
<?php

namespace Timeax\FortiPlugin\Support;

use Timeax\FortiPlugin\Contracts\ConfigInterface;

/**
 * PluginContext
 *
 * Utility class to detect the calling plugin's base directory, config, and name,
 * by scanning the call stack for the first file inside the configured Plugins directory.
 *
 * - Respects 'secured-plugin.directory' config (default: 'Plugins')
 * - Stack frame scan depth defaults to 10 (configurable, but never less than 10)
 * - No caching for accuracy in multi-plugin requests
 *
 * Usage:
 *   $pluginDir = PluginContext::getCurrentPluginDir();
 *   $configPath = PluginContext::getCurrentConfigPath();
 *   $pluginName = PluginContext::getCurrentPluginName();
 */
class PluginContext
{
    /**
     * Returns the maximum number of call stack frames to scan,
     * always at least 10.
     *
     * @return int
     */
    protected static function getStackDepth(): int
    {
        $extra = (int)config('secured-plugin.stack_depth', 1); // default to 1 if not set
        return (max($extra, 1)) + 9; // always at least 10
    }

    /**
     * Returns the base directory path of the calling plugin, or null if not found.
     *
     * @return string|null
     */
    public static function getCurrentPluginDir(): ?string
    {
        $pluginBase = base_path(config('secured-plugin.directory', 'Plugins'));
        $pluginBase = rtrim($pluginBase, '/\\') . DIRECTORY_SEPARATOR;

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, self::getStackDepth());

        foreach ($trace as $frame) {
            if (!isset($frame['file'])) continue;
            $file = $frame['file'];
            if (str_starts_with($file, $pluginBase)) {
                // File is inside the plugin base directory
                $relPath = substr($file, strlen($pluginBase));
                $parts = explode(DIRECTORY_SEPARATOR, $relPath);
                if (!empty($parts[0])) {
                    // Return the plugin's root directory (e.g., .../Plugins/MyPlugin)
                    return $pluginBase . $parts[0];
                }
            }
        }
        return null;
    }

    /**
     * Returns the full path to the Config.php of the current plugin, or null if not found.
     *
     * @return string|null
     */
    public static function getCurrentConfigPath(): ?string
    {
        $pluginDir = self::getCurrentPluginDir();
        if ($pluginDir) {
            $configPath = $pluginDir . DIRECTORY_SEPARATOR . '.internal/Config.php';
            return file_exists($configPath) ? $configPath : null;
        }
        return null;
    }

    /**
     * Returns the name (folder) of the current plugin, or null if not found.
     *
     * @return string|null
     */
    public static function getCurrentPluginName(): ?string
    {
        $pluginDir = self::getCurrentPluginDir();
        return $pluginDir ? basename($pluginDir) : null;
    }

    /**
     * Returns the config class FQCN for the current plugin,
     * or null if not found. Use static methods on the returned class name.
     *
     * @return class-string<ConfigInterface>|null
     */
    public static function getCurrentConfigClass(): ?string
    {
        $pluginDir = self::getCurrentPluginDir();
        if (!$pluginDir) return null;

        $pluginName = basename($pluginDir); // Studly class
        $class = "Plugins\\$pluginName\\Internal\\Config";
        return class_exists($class) ? $class : null;
    }

    /**
     * @return object{name:string, directory:string, config: class-string<ConfigInterface>|null, config_path: string}|null
     */
    public static function getCurrentContext(): ?object
    {
        $pluginDir = self::getCurrentPluginDir();
        $pluginName = $pluginDir ? basename($pluginDir) : null;
        $configPath = self::getCurrentConfigPath();
        $config = self::getCurrentConfigClass();

        if (!$pluginDir && !$config && !$pluginName) {
            return null;
        }

        return (object)[
            'name' => $pluginName,
            'directory' => $pluginDir,
            'config' => $config,
            'config_path' => $configPath,
        ];
    }
}
```

---
#### 70


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
#### 71


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
#### 72


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
<!-- PRODEx v1.4.7 | 2025-12-03T08:59:30.405Z -->