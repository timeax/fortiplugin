<?php

namespace Timeax\FortiPlugin\Installations\Contracts;

use Timeax\FortiPlugin\Installations\Enums\ZipValidationStatus;

interface ZipRepository
{
    /** @return array|null Arbitrary zip record or null */
    public function getZip(int|string $zipId): array|null;

    public function getValidationStatus(int|string $zipId): ZipValidationStatus;

    public function setValidationStatus(int|string $zipId, ZipValidationStatus $status): void;
}
