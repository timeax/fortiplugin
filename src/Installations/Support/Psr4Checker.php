<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

use RuntimeException;

/**
 * Verifies per-plugin PSR-4 mapping in host composer.json:
 *   "<psr4_root>\\<Placeholder.name>\\" : "<psr4_root>/<Placeholder.name>/"
 */
final readonly class Psr4Checker
{
    public function __construct(private AtomicFilesystem $fs)
    {
    }

    public function assertMapping(string $composerJsonPath, string $psr4Root, string $placeholderName): void
    {
        $expected = $this->expected($psr4Root, $placeholderName);
        [$ns, $dir] = $expected;

        $composer = $this->fs->fs()->readJson($composerJsonPath);
        $autoload = (array)($composer['autoload']['psr-4'] ?? []);
        $found = $autoload[$ns] ?? null;

        if (!is_string($found) || rtrim($found, '/\\') !== rtrim($dir, '/\\')) {
            throw new RuntimeException("PSR-4 mapping missing or mismatched for {$ns} → expected '{$dir}'");
        }
    }

    /** @return array{0:string,1:string} */
    public function expected(string $psr4Root, string $placeholderName): array
    {
        $ns = rtrim($psr4Root, '\\') . '\\' . $placeholderName . '\\';
        $dir = rtrim($psr4Root, '/\\') . '/' . $placeholderName . '/';
        return [$ns, $dir];
    }
}