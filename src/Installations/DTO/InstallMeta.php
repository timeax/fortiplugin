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
        public int|string $plugin_placeholder_id,
        public int|string $zip_id,
        public string     $actor,
        /** @var array{staging?:string,install?:string,logs?:string} */
        public array      $paths,
        public string     $started_at,
        public string     $updated_at,
        public string     $fingerprint,
        public string     $validator_config_hash,
    ) {}

    /** @param TInstallMeta $data */
    public static function fromArray(array $data): static
    {
        return new self(
            $data['psr4_root'],
            $data['placeholder_name'],
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
}