<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Infra;

use RuntimeException;
use Timeax\FortiPlugin\Installations\Enums\ZipValidationStatus as ValidationStatus;
use Timeax\FortiPlugin\Installations\Contracts\ZipRepository;

/**
 * In-memory ZipRepository for tests/dev.
 */
final class InMemoryZipRepository implements ZipRepository
{
    /**
     * @var array<string,array{
     *   status: ValidationStatus,
     *   path?: string,
     *   placeholder_id?: int|string,
     *   placeholder_name?: string,
     *   slug?: string|null,
     *   fingerprint?: string,
     *   validator_config_hash?: string|null,
     *   installation_log_path?: string|null,
     *   validated_at?: string|null,
     *   meta?: array
     * }>
     */
    private array $store = [];

    /** @param array<string,ValidationStatus|string> $seed */
    public function __construct(array $seed = [])
    {
        foreach ($seed as $id => $status) {
            $this->setValidationStatus($id, is_string($status) ? ValidationStatus::from($status) : $status);
        }
    }

    public function getZip(int|string $zipId): ?array
    {
        return $this->store[(string)$zipId] ?? null;
    }

    public function getValidationStatus(int|string $zipId): ValidationStatus
    {
        return $this->store[(string)$zipId]['status'] ?? ValidationStatus::UNKNOWN;
    }

    public function setValidationStatus(int|string $zipId, ValidationStatus $status): void
    {
        $k = (string)$zipId;
        $this->store[$k]['status'] = $status;
        $this->store[$k]['meta'] ??= [];
    }

    public function getZipPath(int|string $zipId): string
    {
        $p = $this->store[(string)$zipId]['path'] ?? null;
        if (!is_string($p) || $p === '') {
            throw new RuntimeException("Zip $zipId has no path");
        }
        return $p;
    }

    public function getPlaceholderName(int|string $zipId): string
    {
        $v = $this->store[(string)$zipId]['placeholder_name'] ?? null;
        if (!is_string($v) || $v === '') {
            throw new RuntimeException("Zip $zipId missing placeholder_name");
        }
        return $v;
    }

    public function getPluginPlaceholderId(int|string $zipId): int|string
    {
        $v = $this->store[(string)$zipId]['placeholder_id'] ?? null;
        if (!is_int($v) && !is_string($v)) {
            throw new RuntimeException("Zip $zipId missing placeholder_id");
        }
        return $v;
    }

    public function getSlug(int|string $zipId): ?string
    {
        return $this->store[(string)$zipId]['slug'] ?? null;
    }

    public function getFingerprint(int|string $zipId): string
    {
        $v = $this->store[(string)$zipId]['fingerprint'] ?? null;
        if (!is_string($v) || $v === '') {
            throw new RuntimeException("Zip $zipId missing fingerprint");
        }
        return $v;
    }

    public function getValidatorConfigHash(int|string $zipId): ?string
    {
        return $this->store[(string)$zipId]['validator_config_hash'] ?? null;
    }

    public function recordLogPath(int|string $zipId, string $installationJsonPath): void
    {
        $this->store[(string)$zipId]['installation_log_path'] = $installationJsonPath;
    }

    public function touchValidatedAt(int|string $zipId): void
    {
        $this->store[(string)$zipId]['validated_at'] = gmdate('c');
    }

    /* Test utility */
    public function seed(int|string $zipId, array $data): void
    {
        $k = (string)$zipId;
        $this->store[$k] = ($this->store[$k] ?? []) + $data;
        $this->store[$k]['status'] ??= ValidationStatus::UNKNOWN;
        $this->store[$k]['meta'] ??= [];
    }
}