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


