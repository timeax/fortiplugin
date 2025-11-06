<?php /** @noinspection PhpSameParameterValueInspection */
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Infra;

use RuntimeException;
use Timeax\FortiPlugin\Installations\Enums\ZipValidationStatus as ValidationStatus;
use Timeax\FortiPlugin\Enums\ValidationStatus as ModelValidationStatus;
use Timeax\FortiPlugin\Installations\Contracts\ZipRepository;
use Timeax\FortiPlugin\Models\PluginZip;
use ValueError;

/**
 * Eloquent-backed ZipRepository.
 *
 * Schema:
 * - PluginZip: placeholder_id, path, meta (JSON), validation_status, uploaded_by_author_id, timestamps
 *
 * Fields NOT present natively (stored under PluginZip.meta):
 * - placeholder_name              => meta['placeholder_name']
 * - slug                          => meta['slug']
 * - fingerprint                   => meta['fingerprint']
 * - validator_config_hash         => meta['validator_config_hash']
 * - installation_log_path         => meta['installation_log_path']
 * - validated_at (ISO 8601)       => meta['validated_at']
 */
final class EloquentZipRepository implements ZipRepository
{
    /** @inheritDoc */
    public function getZip(int|string $zipId): ?array
    {
        /** @var PluginZip|null $zip */
        $zip = PluginZip::query()->find($zipId);
        if (!$zip) {
            return null;
        }
        $meta = (array)($zip->meta ?? []);

        return [
            'id' => (string)$zip->id,
            'validation_status' => $zip->validation_status?->value ?? null,
            'path' => $zip->path,
            'meta' => $meta,
            'placeholder_id' => $zip->placeholder_id,
            'uploaded_by_author_id' => $zip->uploaded_by_author_id ? (int)$zip->uploaded_by_author_id : null,
            'created_at' => (string)$zip->created_at,
            'updated_at' => (string)$zip->updated_at,
        ];
    }

    /** @inheritDoc */
    public function getValidationStatus(int|string $zipId): ValidationStatus
    {
        /** @var PluginZip|null $zip */
        $zip = PluginZip::query()->select(['id', 'validation_status'])->find($zipId);
        if (!$zip) {
            return ValidationStatus::UNKNOWN;
        }
        return $this->mapModelToGate($zip->validation_status?->value);
    }

    /** @inheritDoc */
    public function setValidationStatus(int|string $zipId, ValidationStatus $status): void
    {
        /** @var PluginZip $zip */
        $zip = PluginZip::query()->findOrFail($zipId);
        $zip->validation_status = $this->mapGateToModel($status);
        $zip->save();
    }

    /** @inheritDoc */
    public function getZipPath(int|string $zipId): string
    {
        /** @var PluginZip $zip */
        $zip = PluginZip::query()->select(['id', 'path'])->findOrFail($zipId);
        $path = $zip->path;
        if (!is_string($path) || $path === '') {
            throw new RuntimeException("PluginZip #$zipId has no path");
        }
        return $path;
    }

    /** @inheritDoc */
    public function getPlaceholderName(int|string $zipId): string
    {
        /** @var PluginZip $zip */
        $zip = PluginZip::query()->select(['id', 'meta'])->findOrFail($zipId);
        $meta = (array)($zip->meta ?? []);
        $name = $meta['placeholder_name'] ?? null;

        // Optional: try relation if present in your model
        if (!$name && method_exists($zip, 'placeholder')) {
            $rel = $zip->placeholder()->first();
            if ($rel && isset($rel->name) && is_string($rel->name) && $rel->name !== '') {
                $name = $rel->name;
            }
        }

        if (!is_string($name) || $name === '') {
            throw new RuntimeException("PluginZip #$zipId missing meta.placeholder_name");
        }
        return $name;
    }

    /** @inheritDoc */
    public function getPluginPlaceholderId(int|string $zipId): int|string
    {
        /** @var PluginZip $zip */
        $zip = PluginZip::query()->select(['id', 'placeholder_id'])->findOrFail($zipId);
        return $zip->placeholder_id;
    }

    /** @inheritDoc */
    public function getSlug(int|string $zipId): ?string
    {
        /** @var PluginZip|null $zip */
        $zip = PluginZip::query()->select(['id', 'meta'])->find($zipId);
        return $zip ? ((array)$zip->meta)['slug'] ?? null : null;
    }

    /** @inheritDoc */
    public function getFingerprint(int|string $zipId): string
    {
        /** @var PluginZip $zip */
        $zip = PluginZip::query()->select(['id', 'meta'])->findOrFail($zipId);
        $fp = ((array)$zip->meta)['fingerprint'] ?? null;
        if (!is_string($fp) || $fp === '') {
            throw new RuntimeException("PluginZip #$zipId missing meta.fingerprint");
        }
        return $fp;
    }

    /** @inheritDoc */
    public function getValidatorConfigHash(int|string $zipId): ?string
    {
        /** @var PluginZip|null $zip */
        $zip = PluginZip::query()->select(['id', 'meta'])->find($zipId);
        return $zip ? ((array)$zip->meta)['validator_config_hash'] ?? null : null;
    }

    /** @inheritDoc */
    public function recordLogPath(int|string $zipId, string $installationJsonPath): void
    {
        /** @var PluginZip $zip */
        $zip = PluginZip::query()->findOrFail($zipId);
        $meta = (array)($zip->meta ?? []);
        $meta['installation_log_path'] = $installationJsonPath;
        $zip->meta = $meta;
        $zip->save();
    }

    /** @inheritDoc */
    public function touchValidatedAt(int|string $zipId): void
    {
        /** @var PluginZip $zip */
        $zip = PluginZip::query()->findOrFail($zipId);
        $meta = (array)($zip->meta ?? []);
        $meta['validated_at'] = gmdate('c');
        $zip->meta = $meta;
        $zip->save();
    }

    /**
     * Map model ValidationStatus (string value) to gate enum.
     *
     * @param string|null $value e.g. 'valid','pending','failed','unverified','unchecked'
     */
    private function mapModelToGate(?string $value): ValidationStatus
    {
        return match ($value) {
            'valid'       => ValidationStatus::VERIFIED,
            'pending'     => ValidationStatus::PENDING,
            'failed'      => ValidationStatus::FAILED,
            default       => ValidationStatus::UNKNOWN,
        };
    }

    /**
     * Map gate enum to model ValidationStatus enum.
     *
     * Handles deployments where the model enum uses either 'unverified' or 'unchecked'.
     */
    private function mapGateToModel(ValidationStatus $s): ModelValidationStatus
    {
        return match ($s) {
            ValidationStatus::VERIFIED => ModelValidationStatus::valid,
            ValidationStatus::PENDING  => ModelValidationStatus::pending,
            ValidationStatus::FAILED   => ModelValidationStatus::failed,
            ValidationStatus::UNKNOWN  => $this->preferModelStatus('unverified', 'unchecked'),
        };
    }

    /**
     * Prefer one of two model enum values, falling back safely if the first is not defined.
     */
    private function preferModelStatus(string $prefer, string $fallback): ModelValidationStatus
    {
        try {
            return ModelValidationStatus::from($prefer);
        } catch (ValueError) {
            return ModelValidationStatus::from($fallback);
        }
    }
}