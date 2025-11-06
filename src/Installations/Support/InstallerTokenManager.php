<?php /** @noinspection GrazieInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

use JsonException;
use Random\RandomException;
use RuntimeException;
use SodiumException;
use Timeax\FortiPlugin\Installations\DTO\TokenContext;
use Timeax\FortiPlugin\Installations\Contracts\HostKeyService as HostKeyServiceContract;
use Timeax\FortiPlugin\Services\HostKeyService as CryptoHostKeys;

/**
 * Token envelope built on top of the crypto HostKey service (sign/verify).
 *
 * Opaque token (string) = base64url(
 *   json_encode({
 *     v: 1,
 *     claims: {
 *       purpose, zip_id, fingerprint, validator_config_hash,
 *       actor, exp, nonce, run_id
 *     },
 *     sig: { alg, fingerprint, signature_b64 } // 'fingerprint' acts like a KID
 *   })
 * )
 *
 * Security notes:
 *  - Signs a deterministic JSON representation of the claims (stable key order).
 *  - NEVER log or persist the opaque token; expose only summarize() output if needed.
 */
final readonly class InstallerTokenManager implements HostKeyServiceContract
{
    public function __construct(private CryptoHostKeys $keys)
    {
    }

    /**
     * Issue an encrypted/signed token for given claims.
     * The TokenContext should already contain a sensible exp.
     * @throws JsonException
     * @throws JsonException
     * @throws JsonException
     */
    public function issue(TokenContext $claims): string
    {
        $arr = $claims->toArray();        // DTO → array
        $this->assertClaims($arr);        // sanity checks
        $data = $this->stableJson($arr);  // deterministic representation

        // Sign with current host key; returns ['alg','fingerprint','signature_b64'] (b64 is already encrypted by your service)
        $sig = $this->keys->sign($data);

        $env = ['v' => 1, 'claims' => $arr, 'sig' => $sig];
        return $this->encode($env);
    }

    /**
     * Issue a background_scan token (fingerprint is resolved internally).
     *
     * @param int|string $zipId
     * @param string $validatorConfigHash
     * @param string $actor
     * @param string $runId
     * @param int $ttlSeconds Desired TTL; bounded to 60–3600 seconds.
     * @return non-empty-string
     *
     * @throws JsonException
     * @throws RandomException
     * @throws RuntimeException
     */
    public function issueBackgroundScanToken(
        int|string $zipId,
        string     $validatorConfigHash,
        string     $actor,
        string     $runId,
        int        $ttlSeconds
    ): string
    {
        // Bound TTL to a sane window (adjust if you prefer different bounds)
        $ttl = min(3600, max(60, $ttlSeconds));

        // Resolve current verify-key fingerprint (acts like KID)
        $fp = $this->keys->currentVerifyKey()['fingerprint'];

        // Build claims DTO and issue the signed/enveloped token
        $claims = $this->makeBackgroundScanClaims(
            zipId: $zipId,
            fingerprint: $fp,
            validatorConfigHash: $validatorConfigHash,
            actor: $actor,
            runId: $runId,
            ttlSeconds: $ttl
        );

        return $this->issue($claims);
    }

    /**
     * Validate/decode a token and return its claims DTO.
     * @throws JsonException
     * @throws JsonException|SodiumException
     */
    public function validate(string $token): TokenContext
    {
        $env = $this->decode($token);
        if (!is_array($env) || ($env['v'] ?? null) !== 1 || !isset($env['claims'], $env['sig'])) {
            throw new RuntimeException('Invalid token envelope');
        }

        $claims = $env['claims'];
        $sig = $env['sig'];

        $this->assertClaims($claims);

        // Recreate deterministic string and verify with the host keys
        $data = $this->stableJson($claims);
        $ok = $this->keys->verify(
            data: $data,
            signatureB64: (string)($sig['signature_b64'] ?? ''),
            fingerprint: (string)($sig['fingerprint'] ?? '')
        );
        if (!$ok) {
            throw new RuntimeException('Invalid token signature');
        }

        $exp = (int)$claims['exp'];
        if ($exp < time()) {
            throw new RuntimeException('Token expired');
        }

        // Normalize into DTO
        return new TokenContext(
            purpose: (string)$claims['purpose'],
            zip_id: $claims['zip_id'],
            fingerprint: (string)$claims['fingerprint'],
            validator_config_hash: (string)$claims['validator_config_hash'],
            actor: (string)$claims['actor'],
            exp: $exp,
            nonce: (string)$claims['nonce'],
            run_id: (string)$claims['run_id'],
        );
    }

    /** Safe metadata for logs/UI (never include the token). */
    public function summarize(string $purpose, int $exp): array
    {
        return ['purpose' => $purpose, 'expires_at' => gmdate('c', $exp)];
    }

    // ── Optional helpers to build common claim sets (host can ignore if not needed) ──

    /**
     * @throws RandomException
     */
    public function makeBackgroundScanClaims(
        int|string $zipId,
        string     $fingerprint,
        string     $validatorConfigHash,
        string     $actor,
        string     $runId,
        int        $ttlSeconds
    ): TokenContext
    {
        return new TokenContext(
            purpose: 'background_scan',
            zip_id: $zipId,
            fingerprint: $fingerprint,
            validator_config_hash: $validatorConfigHash,
            actor: $actor,
            exp: time() + max(60, $ttlSeconds),
            nonce: bin2hex(random_bytes(12)),
            run_id: $runId,
        );
    }

    /**
     * Issue an install_override token (fingerprint resolved internally).
     *
     * @param int|string $zipId
     * @param string $validatorConfigHash
     * @param string $actor
     * @param string $runId
     * @param int $ttlSeconds Desired TTL; bounded to 60–3600 seconds.
     * @return non-empty-string
     *
     * @throws JsonException
     * @throws RandomException
     * @throws RuntimeException
     */
    public function issueInstallOverrideToken(
        int|string $zipId,
        string     $validatorConfigHash,
        string     $actor,
        string     $runId,
        int        $ttlSeconds
    ): string
    {
        $ttl = min(3600, max(60, $ttlSeconds));

        // Resolve current verify-key fingerprint (KID)
        $fp = $this->keys->currentVerifyKey()['fingerprint'];

        // Build claims DTO and issue the signed/enveloped token
        $claims = $this->makeInstallOverrideClaims(
            zipId: $zipId,
            fingerprint: $fp,
            validatorConfigHash: $validatorConfigHash,
            actor: $actor,
            runId: $runId,
            ttlSeconds: $ttl
        );

        return $this->issue($claims);
    }

    /**
     * @throws RandomException
     */
    public function makeInstallOverrideClaims(
        int|string $zipId,
        string     $fingerprint,
        string     $validatorConfigHash,
        string     $actor,
        string     $runId,
        int        $ttlSeconds
    ): TokenContext
    {
        return new TokenContext(
            purpose: 'install_override',
            zip_id: $zipId,
            fingerprint: $fingerprint,
            validator_config_hash: $validatorConfigHash,
            actor: $actor,
            exp: time() + max(60, $ttlSeconds),
            nonce: bin2hex(random_bytes(12)),
            run_id: $runId,
        );
    }

    // ── internals ───────────────────────────────────────────────────────────

    /** @param array<string,mixed> $claims */
    private function assertClaims(array $claims): void
    {
        foreach (['purpose', 'zip_id', 'fingerprint', 'validator_config_hash', 'actor', 'exp', 'nonce', 'run_id'] as $k) {
            if (!array_key_exists($k, $claims)) {
                throw new RuntimeException("Missing claim: $k");
            }
        }
        if (!is_int($claims['exp'])) {
            throw new RuntimeException('Claim exp must be an integer epoch');
        }
        if (!is_string($claims['purpose']) || $claims['purpose'] === '') {
            throw new RuntimeException('Claim purpose must be a non-empty string');
        }
    }

    /**
     * @throws JsonException
     */
    private function encode(array $env): string
    {
        $json = json_encode($env, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Token encoding failed');
        }
        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        // (No encryption of the envelope itself; the signature is already protected via your HostKeyService)
    }

    /**
     * @throws JsonException
     */
    private function decode(string $token): array
    {
        $json = base64_decode(strtr($token, '-_', '+/'), true);
        if ($json === false) {
            throw new RuntimeException('Token decoding failed');
        }
        $env = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($env)) {
            throw new RuntimeException('Token JSON invalid');
        }
        return $env;
    }

    /** Deterministic JSON for signing/verification (assoc keys sorted, recursive).
     * @throws JsonException
     * @throws JsonException
     */
    private function stableJson(mixed $value): string
    {
        if (is_array($value)) {
            // associative?
            if ($value !== [] && !array_is_list($value)) {
                ksort($value);
                $pairs = [];
                foreach ($value as $k => $v) {
                    $pairs[] = json_encode((string)$k, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ':' . $this->stableJson($v);
                }
                return '{' . implode(',', $pairs) . '}';
            }
            // sequential
            return '[' . implode(',', array_map(fn($v) => $this->stableJson($v), $value)) . ']';
        }
        $enc = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($enc === false) {
            throw new RuntimeException('Stable JSON encode failed');
        }
        return $enc;
    }
}