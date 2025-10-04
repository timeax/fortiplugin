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