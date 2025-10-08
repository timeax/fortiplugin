<?php

namespace Timeax\FortiPlugin\Permissions\Contracts;

interface PermissionRequestInterface
{
    /**
     * Create a request DTO from an associative array representation.
     *
     * @param array $a
     * @return self
     */
    public static function fromArray(array $a): self;

    /**
     * Convert the request DTO to an associative array representation.
     *
     * @return array
     */
    public function toArray(): array;
}