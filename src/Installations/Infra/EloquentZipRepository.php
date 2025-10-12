<?php

namespace Timeax\FortiPlugin\Installations\Infra;

use Timeax\FortiPlugin\Installations\Contracts\ZipRepository;
use Timeax\FortiPlugin\Installations\Enums\ZipValidationStatus;
use Timeax\FortiPlugin\Models\PluginZip;
use Timeax\FortiPlugin\Enums\ValidationStatus as ModelValidationStatus;

class EloquentZipRepository implements ZipRepository
{
    public function __construct()
    {
    }

    public function getZip(int|string $zipId): array|null
    {
        $zip = PluginZip::query()->find($zipId);
        if (!$zip) return null;
        return [
            'id' => (string)$zip->id,
            'validation_status' => $zip->validation_status?->value ?? null,
            'path' => $zip->path,
            'meta' => (array)$zip->meta,
            'placeholder_id' => $zip->placeholder_id,
            'uploaded_by_author_id' => $zip->uploaded_by_author_id,
            'created_at' => (string)$zip->created_at,
        ];
    }

    public function getValidationStatus(int|string $zipId): ZipValidationStatus
    {
        $zip = PluginZip::query()->select(['id', 'validation_status'])->find($zipId);
        if (!$zip) return ZipValidationStatus::UNKNOWN;
        return $this->mapModelToGate($zip->validation_status);
    }

    public function setValidationStatus(int|string $zipId, ZipValidationStatus $status): void
    {
        $zip = PluginZip::query()->findOrFail($zipId);
        $zip->validation_status = $this->mapGateToModel($status);
        $zip->save();
    }

    private function mapModelToGate(?ModelValidationStatus $s): ZipValidationStatus
    {
        return match ($s) {
            ModelValidationStatus::valid => ZipValidationStatus::VERIFIED,
            ModelValidationStatus::pending => ZipValidationStatus::PENDING,
            ModelValidationStatus::failed => ZipValidationStatus::FAILED,
            default => ZipValidationStatus::UNKNOWN,
        };
    }

    private function mapGateToModel(ZipValidationStatus $s): ModelValidationStatus
    {
        return match ($s) {
            ZipValidationStatus::VERIFIED => ModelValidationStatus::valid,
            ZipValidationStatus::PENDING => ModelValidationStatus::pending,
            ZipValidationStatus::FAILED => ModelValidationStatus::failed,
            ZipValidationStatus::UNKNOWN => ModelValidationStatus::unverified,
        };
    }
}
