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