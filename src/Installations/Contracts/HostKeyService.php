<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Contracts;

use RuntimeException;
use Timeax\FortiPlugin\Installations\DTO\TokenContext;

/**
 * Cryptographic envelope for issuing and validating installer tokens.
 *
 * Requirements:
 *  - Encrypt/sign payloads (support key rotation via 'kid')
 *  - Validate integrity & expiry
 *  - NEVER persist raw/encrypted tokens to DB/logs; only safe metadata elsewhere
 */
interface HostKeyService
{
    /**
     * Issue an encrypted/signed token for the given claims.
     *
     * @param TokenContext $claims Mandatory fields (purpose, zip_id, fingerprint, config hash, actor, exp, nonce, run_id)
     * @return non-empty-string     Opaque token
     *
     * @throws RuntimeException On crypto/key issues.
     */
    public function issue(TokenContext $claims): string;

    /**
     * Validate/decrypt a token and return its claims if valid.
     *
     * @param non-empty-string $token Opaque token previously issued by issue()
     * @return TokenContext           Decoded claims
     *
     * @throws RuntimeException If invalid, expired, or unrecognized.
     */
    public function validate(string $token): TokenContext;
}