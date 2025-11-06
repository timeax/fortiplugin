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
