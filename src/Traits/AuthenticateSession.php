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