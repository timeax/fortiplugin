<?php /** @noinspection DuplicatedCode */

namespace Timeax\FortiPlugin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use JsonException;
use Throwable;
use Timeax\FortiPlugin\Traits\AuthenticateSession;
use Timeax\FortiPlugin\Traits\Stubber;

class RedirectPlugin extends Command
{
    use AuthenticateSession, Stubber;

    protected $signature = 'fp:redirect
        {name : Plugin directory name, e.g., OrdersPlugin}
        {--force : Overwrite if necessary}';

    protected $description = 'Redirect an existing plugin to a new host, creating a new placeholder on the new host.';

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

        $name = $this->argument('name');
        $base = config('fortiplugin.dev_directory', 'Plugins');
        $path = $base . DIRECTORY_SEPARATOR . $name;

        if (!$this->files->exists($path)) {
            $this->error("Plugin '$name' does not exist locally.");
            return self::FAILURE;
        }

        // 2) Read existing configuration to get slug and name
        $fortiConfigPath = "$path/fortiplugin.json";
        if (!$this->files->exists($fortiConfigPath)) {
            $this->error("fortiplugin.json not found in '$name'.");
            return self::FAILURE;
        }

        try {
            $fortiConfig = json_decode($this->files->get($fortiConfigPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error("Invalid fortiplugin.json: " . $e->getMessage());
            return self::FAILURE;
        }

        $studly = $fortiConfig['name'] ?? $name;
        $kebab = $fortiConfig['alias'] ?? Str::kebab($studly);

        $client = $this->getHttp();
        if (!$client) {
            $this->error('Could not create API client from your session.');
            return self::FAILURE;
        }

        // 3) Contact host: handshake (policy + verify + signature block)
        $handshake = $this->getJson($client->get('/forti/handshake'));
        if (!($handshake['ok'] ?? false)) {
            $this->error('Handshake failed.');
            return self::FAILURE;
        }

        $signatureBlock = $handshake['signature_block'] ?? null;
        if (!$signatureBlock) {
            $this->warn('Host did not return a signature_block. Continuing without it.');
        }

        // 4) Ask/derive author info (prefer from session)
        $author = [
            'name' => $session['name'] ?? $this->ask('Author name'),
            'email' => $session['email'] ?? $this->ask('Author email'),
        ];

        // 5) Init placeholder (+ placeholder token) on the NEW host
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

        // 6) Update .internal files
        $internalDir = "$path/.internal";
        if (!$this->files->exists($internalDir)) {
            $this->files->makeDirectory($internalDir, 0755, true);
        }

        // 6a) Host-provided signature block into Config.php (via stub or direct)
        $psr4Root = $init['psr4_root'] ?? 'Plugins'; // e.g. "Plugins"
        
        // We need to regenerate Config.php with the new host's signature block and plugin ID
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

        // 6b) Store placeholder token (single-use raw)
        if ($phToken) {
            $this->files->put(
                "$internalDir/placeholder.token.json",
                json_encode(['token' => $phToken, 'placeholder_id' => $placeholderId, 'slug' => $kebab], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
            );
        }

        // 7) Update publish.json
        $publishPath = $path . "/publish.json";
        
        // We overwrite publish.json because we are redirecting to a new host
        $this->files->put($publishPath, json_encode([
            'host' => $session['host'],
            'plugin_slug' => $kebab,
            'plugin_key' => $pluginKey,
            'author' => $author,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("Plugin '$studly' redirected to host '{$session['host']}'.");
        return self::SUCCESS;
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
}
