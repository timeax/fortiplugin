<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\DTO;

/**
 * Simple array-serialization contract for DTOs.
 */
interface ArraySerializable
{
    /**
     * @param array $data
     * @return static
     */
    public static function fromArray(array $data): static;

    /**
     * @return array
     */
    public function toArray(): array;
}