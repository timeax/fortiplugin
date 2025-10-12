<?php

namespace Timeax\FortiPlugin\Installations\Infra;

use Timeax\FortiPlugin\Installations\Contracts\ZipRepository;
use Timeax\FortiPlugin\Installations\Enums\ZipValidationStatus;

class InMemoryZipRepository implements ZipRepository
{
    /** @var array<string,array{status: ZipValidationStatus, data: array}> */
    private array $store = [];

    public function __construct(array $seed = [])
    {
        foreach ($seed as $id => $status) {
            $this->setValidationStatus($id, is_string($status) ? ZipValidationStatus::from($status) : $status);
        }
    }

    public function getZip(int|string $zipId): array|null
    {
        $key = (string)$zipId;
        if (!isset($this->store[$key])) return null;
        return ['id' => $key, 'validation_status' => $this->store[$key]['status']->value] + ($this->store[$key]['data'] ?? []);
    }

    public function getValidationStatus(int|string $zipId): ZipValidationStatus
    {
        $key = (string)$zipId;
        return $this->store[$key]['status'] ?? ZipValidationStatus::UNKNOWN;
    }

    public function setValidationStatus(int|string $zipId, ZipValidationStatus $status): void
    {
        $key = (string)$zipId;
        $this->store[$key]['status'] = $status;
        $this->store[$key]['data'] = $this->store[$key]['data'] ?? [];
    }
}
