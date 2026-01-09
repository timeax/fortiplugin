<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

use JsonException;
use RuntimeException;

/**
 * Verifies per-plugin PSR-4 mapping in the host composer.json.
 *
 * Expected mapping:
 *   "<psr4_root>\\<Placeholder.name>\\": ["src", ".internal"]
 */
final readonly class Psr4Checker
{
    public function __construct(private AtomicFilesystem $fs)
    {
    }

    /**
     * @throws JsonException
     */
    public function assertMapping(string $composerJsonPath, string $psr4Root, string $placeholderName): void
    {
        [$ns, $expectedDirs] = $this->expected($psr4Root, $placeholderName);

        $composer = $this->fs->fs()->readJson($composerJsonPath);
        $autoload = (array)($composer['autoload']['psr-4'] ?? []);
        $found = $autoload[$ns] ?? null;

        if (!is_array($found)) {
            throw new RuntimeException("PSR-4 mapping missing or not an array for $ns → expected " . json_encode($expectedDirs, JSON_THROW_ON_ERROR));
        }

        $foundDirs = [];
        foreach ($found as $v) {
            if (!is_string($v) || trim($v) === '') {
                throw new RuntimeException("PSR-4 mapping for $ns contains invalid path entries.");
            }
            $foundDirs[] = rtrim($v, "/\\");
        }

        $expectedDirs = array_map(static fn($v) => rtrim($v, "/\\"), $expectedDirs);

        if ($foundDirs !== $expectedDirs) {
            throw new RuntimeException("PSR-4 mapping mismatched for $ns → expected " . json_encode($expectedDirs, JSON_THROW_ON_ERROR) .  "found " . json_encode($foundDirs, JSON_THROW_ON_ERROR));
        }
    }

    /** @return array{0:string,1:list<string>} */
    public function expected(string $psr4Root, string $placeholderName): array
    {
        $ns = rtrim($psr4Root, '\\') . '\\' . $placeholderName . '\\';
        return [$ns, ['src', '.internal']];
    }

}