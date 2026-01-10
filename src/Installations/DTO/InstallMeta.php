<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\DTO;

/**
 * @phpstan-type TInstallPaths array{
 *   staging?: string,
 *   install?: string,
 *   logs?: string
 * }
 * @phpstan-type TInstallMeta array{
 *   psr4_root: string,
 *   placeholder_name: string,
 *   placeholder_slug: string,
 *   plugin_placeholder_id: int|string,
 *   zip_id: int|string,
 *   actor: string,
 *   paths: TInstallPaths,
 *   started_at: string,
 *   updated_at: string,
 *   fingerprint: string,
 *   validator_config_hash: string
 * }
 */
final readonly class InstallMeta implements ArraySerializable
{
    public function __construct(
        public string     $psr4_root,
        public string     $placeholder_name,
        public string     $placeholder_slug,
        public int|string $plugin_placeholder_id,
        public int|string $zip_id,
        public string     $actor,
        /** @var array{staging?:string,install?:string,logs?:string} */
        public array      $paths,
        public string     $started_at,
        public string     $updated_at,
        public string     $fingerprint,
        public string     $validator_config_hash,
    )
    {
    }

    /** @param TInstallMeta $data */
    public static function fromArray(array $data): static
    {
        return new self(
            $data['psr4_root'],
            $data['placeholder_name'],
            $data['placeholder_slug'],
            $data['plugin_placeholder_id'],
            $data['zip_id'],
            $data['actor'],
            $data['paths'] ?? [],
            $data['started_at'],
            $data['updated_at'],
            $data['fingerprint'],
            $data['validator_config_hash'],
        );
    }

    /** @return TInstallMeta */
    public function toArray(): array
    {
        return [
            'psr4_root' => $this->psr4_root,
            'placeholder_name' => $this->placeholder_name,
            'placeholder_slug' => $this->placeholder_slug,
            'plugin_placeholder_id' => $this->plugin_placeholder_id,
            'zip_id' => $this->zip_id,
            'actor' => $this->actor,
            'paths' => $this->paths,
            'started_at' => $this->started_at,
            'updated_at' => $this->updated_at,
            'fingerprint' => $this->fingerprint,
            'validator_config_hash' => $this->validator_config_hash,
        ];
    }

    /**
     * Same shape as toArray(), but safe for logs/UI:
     * - paths are project-relative (base_path)
     * - never leaks absolute paths outside project root
     *
     * @return TInstallMeta
     */
    public function toLogArray(?string $projectRoot = null): array
    {
        return $this->toBaseRelativeArray();
    }

    /**
     * Same shape as toArray(), but meta.paths are relative to base_path().
     *
     * @return TInstallMeta
     */
    public function toBaseRelativeArray(): array
    {
        $out = $this->toArray();
        $out['paths'] = self::pathsRelativeToBasePath($this->paths);

        return $out;
    }

    /** @param array{staging?:string,install?:string,logs?:string} $paths */
    private static function pathsRelativeToBasePath(array $paths): array
    {
        $base = self::normalizePath(base_path());

        $out = [];
        foreach ($paths as $k => $p) {
            if (!is_string($p) || $p === '') {
                continue;
            }

            $out[$k] = self::relativeToBasePath($p, $base);
        }

        /** @var array{staging?:string,install?:string,logs?:string} $out */
        return $out;
    }

    private static function relativeToBasePath(string $path, string $base): string
    {
        $p = self::normalizePath($path);
        $b = rtrim($base, '/');

        if ($p === $b) {
            return '.';
        }

        $prefix = $b . '/';

        // require it to be inside base_path()
        if (!str_starts_with($p . '/', $prefix)) {
            throw new \RuntimeException("InstallMeta.paths must be under base_path(): {$path}");
        }

        return ltrim(substr($p, strlen($prefix)), '/');
    }

    private static function normalizePath(string $path): string
    {
        $p = str_replace('\\', '/', $path);
        $p = preg_replace('#/+#', '/', $p) ?: $p;

        return rtrim($p, '/');
    }
}