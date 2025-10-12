<?php

namespace Timeax\FortiPlugin\Installations\Support;

use DateInterval;
use DateTimeImmutable;

/**
 * Minimal Phase 2/3 token manager sufficient for wiring ZipValidationGate and FileScanSection.
 * NOTE: This is a stub: it does not persist server-side hashes.
 */
class InstallerTokenManager
{
    /**
     * Issues a background_scan token. Returns [tokenEncrypted, expiresAt (ISO8601), ctx].
     */
    public function issueBackgroundScanToken(int|string $zipId, string $fingerprint, string $configHash, string $actor, ?DateTimeImmutable $now = null, ?int $ttlSeconds = null): array
    {
        $now = $now ?? new DateTimeImmutable('now');
        // Configurable TTL with sane bounds
        $ttl = $ttlSeconds ?? (\function_exists('config') ? (int)(config('fortiplugin.installations.tokens.background_scan_ttl') ?? 600) : 600);
        $ttl = max(60, min(3600, $ttl));
        $expires = $now->add(new DateInterval('PT' . $ttl . 'S'));
        $token = base64_encode(random_bytes(24)); // placeholder encrypted token
        $ctx = [
            'purpose' => 'background_scan',
            'zipId' => (string)$zipId,
            'fingerprint' => $fingerprint,
            'configHash' => $configHash,
            'actor' => $actor,
            'expiresAt' => $expires->format(DATE_ATOM),
        ];
        return [$token, $ctx['expiresAt'], $ctx];
    }

    /**
     * Issues an install_override token. Returns [tokenEncrypted, expiresAt (ISO8601), ctx].
     */
    public function issueToken(string $purpose, int|string $zipId, string $fingerprint, string $validatorConfigHash, string $actor, ?DateTimeImmutable $now = null, ?int $ttlSeconds = null): array
    {
        $now = $now ?? new DateTimeImmutable('now');
        // Select TTL based on purpose
        $key = $purpose === 'install_override' ? 'install_override_ttl' : 'background_scan_ttl';
        $default = $purpose === 'install_override' ? 600 : 600;
        $ttl = $ttlSeconds ?? (\function_exists('config') ? (int)(config('fortiplugin.installations.tokens.' . $key) ?? $default) : $default);
        $ttl = max(60, min(3600, $ttl));
        $expires = $now->add(new DateInterval('PT' . $ttl . 'S'));
        $token = base64_encode(random_bytes(24)); // placeholder encrypted token
        $ctx = [
            'purpose' => $purpose,
            'zipId' => (string)$zipId,
            'fingerprint' => $fingerprint,
            'configHash' => $validatorConfigHash,
            'actor' => $actor,
            'expiresAt' => $expires->format(DATE_ATOM),
        ];
        return [$token, $ctx['expiresAt'], $ctx];
    }
}
