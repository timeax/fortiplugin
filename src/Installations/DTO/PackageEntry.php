<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\DTO;

use Timeax\FortiPlugin\Installations\Enums\PackageStatus;

/**
 * @phpstan-type TPackageEntry array{
 *   name: string,
 *   is_foreign: bool,
 *   status: 'verified'|'unverified'|'pending'|'failed'
 * }
 */
final readonly class PackageEntry implements ArraySerializable
{
    public function __construct(
        public string        $name,
        public bool          $is_foreign,
        public PackageStatus $status,
    )
    {
    }

    /** @param TPackageEntry $data */
    public static function fromArray(array $data): static
    {
        return new self(
            $data['name'],
            (bool)$data['is_foreign'],
            PackageStatus::from($data['status']),
        );
    }

    /** @return TPackageEntry */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'is_foreign' => $this->is_foreign,
            'status' => $this->status->value,
        ];
    }
}