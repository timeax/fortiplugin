<?php /** @noinspection PhpUnusedLocalVariableInspection */

/** @noinspection PhpUnused */

namespace Timeax\FortiPlugin\Services;

use GuzzleHttp\Client;
use JsonException;
use SodiumException;
use Throwable;
use Timeax\FortiPlugin\Support\Encryption;

class SigningService
{
    /**
     * Create the signature block using Encryption class for encryption/embedding.
     * @param array $author
     * @param string $hostDomain
     * @param array $policy
     * @param array $pluginInfo
     * @return string  Block comment for embedding in Config.php
     * @throws JsonException
     */
    public static function makeSignature(
        array  $author,
        string $hostDomain,
        array  $policy,
        array  $pluginInfo
    ): string
    {
        $context = [
            'timestamp' => date('c'),
            'author' => $author,
            'host' => $hostDomain,
            'policy' => $policy,
            'plugin' => $pluginInfo,
        ];

        // Convert to JSON with pretty print
        $contextJson = json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        // Use Encryption::encrypt for all encryption and key embedding
        $encryptedBlock = Encryption::encrypt($contextJson);

        // Wrap as a signature block comment
        return <<<BLOCK
/**
 *  !! AUTO-GENERATED – DO NOT EDIT !!
 *  Reads plugin.config.json during development.
 *  ENCRYPTED_SIGNATURE:
 *  ----BEGIN----
 *  {$encryptedBlock}
 *  ----END----
 */
BLOCK;
    }

    /**
     * Extract and decrypt the context using Encryption::decrypt.
     * @param string $configPhpPath
     * @return array|null
     * @throws SodiumException|JsonException
     */
    public static function extractAndDecryptContext(string $configPhpPath): ?array
    {
        $contents = file_get_contents($configPhpPath);
        if (!preg_match('/----BEGIN----(.*?)----END----/s', $contents, $matches)) return null;
        $encryptedBlock = trim($matches[1]);
        $plaintext = Encryption::decrypt($encryptedBlock);
        return $plaintext ? json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR) : null;
    }

    /**
     * Verifies that the signature in Config.php matches the expected manifest/context.
     * Returns true if valid, false otherwise.
     *
     * @param string $configPhpPath Path to Config.php (signed file)
     * @param array $expectedContext Reference array for expected fields (from manifest)
     * @return bool
     * @noinspection PhpUnusedLocalVariableInspection
     */
    public static function verifySignature(string $configPhpPath, array $expectedContext): bool
    {
        try {
            $actualContext = self::extractAndDecryptContext($configPhpPath);
            if (!$actualContext) return false;

            // You can customize these checks—these are recommended core comparisons
            foreach (['author', 'host', 'policy', 'plugin'] as $key) {
                if (
                    !isset($actualContext[$key], $expectedContext[$key]) || $actualContext[$key] !== $expectedContext[$key]
                ) {
                    return false; // Fail if any required field doesn't match
                }
            }
            // Optionally, check timestamp (within N minutes), version, etc.

            return true;
        } catch (Throwable $e) {
            // Optionally log error: $e->getMessage()
            return false;
        }
    }

    /**
     * Checks if the author (by email) exists/ever existed on the original host.
     * Returns true if confirmed, false otherwise.
     *
     * @param string $email The author's email (from manifest)
     * @param string $hostDomain The host domain (from manifest)
     * @return bool
     */
    public static function verifyAuthorEmailRemotely(string $email, string $hostDomain): bool
    {
        if (empty($hostDomain) || empty($email)) return false;

        $endpoint = "https://$hostDomain/fortiplugin/api/verify-author";
        try {
            $client = new Client(['timeout' => 5]);
            $res = $client->get($endpoint, ['query' => ['email' => $email]]);
            $data = json_decode($res->getBody(), true, 512, JSON_THROW_ON_ERROR);

            return isset($data['exists']) && $data['exists'] === true;
        } catch (Throwable $e) {
            // Optionally: log error
            return false;
        }
    }
}
