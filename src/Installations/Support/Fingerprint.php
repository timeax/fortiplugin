<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

use JsonException;
use RuntimeException;

/**
 * Simple SHA-256 file hashing and deterministic config hashing.
 */
final class Fingerprint
{
    public function fileSha256(string $absolutePath): string
    {
        $h = @hash_file('sha256', $absolutePath);
        if ($h === false) {
            throw new RuntimeException("Unable to hash file: $absolutePath");
        }
        return $h;
    }

    /** @param array<string,mixed> $validatorConfig
     * @throws JsonException
     */
    public function validatorConfigHash(array $validatorConfig): string
    {
        $normalized = $this->stableJson($validatorConfig);
        return hash('sha256', $normalized);
    }

    /**
     * @param mixed $value
     * @return string
     * @throws JsonException
     */
    private function stableJson(mixed $value): string
    {
        if (is_array($value)) {
            // sort associative keys for stability
            if ($this->isAssoc($value)) {
                ksort($value);
                $value = array_map(fn($v) => $this->stableJson($v), $value);
                return '{'.implode(',', array_map(
                        static fn($k, $v) => json_encode((string)$k, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) .':'.$v,
                        array_keys($value),
                        array_values($value)
                    )).'}';
            }
            // numeric arrays keep order
            return '['.implode(',', array_map(fn($v) => $this->stableJson($v), $value)).']';
        }
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array $a
     * @return bool
     */
    private function isAssoc(array $a): bool
    {
        return $a !== [] && !array_is_list($a);
    }
}