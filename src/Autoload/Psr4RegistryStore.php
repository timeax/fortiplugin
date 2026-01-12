<?php
/** @noinspection GrazieInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Autoload;

use Random\RandomException;
use RuntimeException;

final class Psr4RegistryStore
{
    /** @return array<string,mixed> */
    public function read(): array
    {
        $path = $this->registryPath();

        if (is_file($path)) {
            $data = require $path;
            return $this->normalizeRegistry($data);
        }

        // Fallback to backup if final is missing
        $bak = $this->backupPath();
        if (is_file($bak)) {
            $data = require $bak;
            return $this->normalizeRegistry($data);
        }

        return [
            'generated_at' => null,
            'plugins' => [],
        ];
    }

    /**
     * Write registry to a temp file IN THE SAME DIRECTORY as the final registry.
     * This guarantees the subsequent rename() is atomic.
     *
     * @return string temp file path
     * @throws RandomException
     */
    public function stage(array $registry): string
    {
        $final = $this->registryPath();
        $dir = dirname($final);

        $this->ensureDirectoryExists($dir);

        // Hidden-ish temp file in same directory (atomic rename guarantee)
        $tmpPath = $dir . DIRECTORY_SEPARATOR
            . '.' . basename($final)
            . '.tmp.' . bin2hex(random_bytes(8))
            . '.php';

        $payload = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($registry, true) . ";\n";

        $bytes = @file_put_contents($tmpPath, $payload, LOCK_EX);
        if ($bytes === false) {
            throw new RuntimeException("Failed to write staged registry file: $tmpPath");
        }

        return $tmpPath;
    }

    /**
     * Atomic commit with backup:
     * - if final exists, rename it to .bak
     * - rename tmp -> final
     * - if rename fails, restore .bak
     */
    public function commit(string $tmpPath): void
    {
        $final = $this->registryPath();
        $this->ensureDirectoryExists(dirname($final));

        $bak = $this->backupPath();

        // Backup existing final (must succeed if final exists)
        if (is_file($final)) {
            @unlink($bak);
            if (!@rename($final, $bak)) {
                // If we can’t backup, we must not proceed
                @unlink($tmpPath);
                throw new RuntimeException("Failed to backup registry file: $final -> $bak");
            }
        }

        // Now move tmp -> final (same directory => atomic swap)
        if (!@rename($tmpPath, $final)) {
            // Try restore final from backup if possible
            if (is_file($bak)) {
                @rename($bak, $final);
            }
            @unlink($tmpPath);
            throw new RuntimeException("Failed to commit registry file from $tmpPath to $final");
        }
    }

    public function registryPath(): string
    {
        $raw = config('fortiplugin.autoload_registry');

        if (!is_string($raw) || trim($raw) === '') {
            $raw = 'bootstrap/fortiplugin.autoload_psr4.php';
        }

        $raw = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($raw));

        if ($this->isAbsolutePath($raw)) {
            return $raw;
        }

        return $this->basePath($raw);
    }

    private function backupPath(): string
    {
        return $this->registryPath() . '.bak';
    }

    private function isAbsolutePath(string $path): bool
    {
        // Linux/macOS absolute
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return true;
        }

        // Windows absolute: C:\... or C:/...
        return (bool) preg_match('/^[A-Za-z]:[\/\\\\]/', $path);
    }

    private function basePath(string $path = ''): string
    {
        if (function_exists('base_path')) {
            return base_path($path);
        }

        $root = getcwd() ?: __DIR__;
        return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }

    private function ensureDirectoryExists(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }

        if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create directory: $dir");
        }
    }

    /** @return array<string,mixed> */
    private function normalizeRegistry(mixed $data): array
    {
        if (!is_array($data)) {
            $data = [];
        }

        $data['generated_at'] ??= null;
        $data['plugins'] ??= [];

        if (!is_array($data['plugins'])) {
            $data['plugins'] = [];
        }

        return $data;
    }
}
