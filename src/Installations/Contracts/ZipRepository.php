<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Contracts;

use RuntimeException;
use Timeax\FortiPlugin\Installations\Enums\ZipValidationStatus;

/**
 * Accessor for plugin zip metadata & lifecycle.
 *
 * Central truth for:
 *  - zip file path (staging/extract),
 *  - plugin identity (Placeholder.name, placeholder id),
 *  - validation status (verified/pending/failed),
 *  - immutable fingerprints & config hashes for token binding,
 *  - operational pointers (installation.json path, timestamps).
 */
interface ZipRepository
{
    /**
     * Retrieve an arbitrary zip record (implementation-defined shape) or null.
     *
     * @param int|string $zipId
     * @return array|null
     */
    public function getZip(int|string $zipId): ?array;

    /**
     * Current validation status for the zip (verified|pending|failed|unknown).
     *
     * @param int|string $zipId
     * @return ZipValidationStatus
     */
    public function getValidationStatus(int|string $zipId): ZipValidationStatus;

    /**
     * Set validation status for the zip.
     *
     * @param int|string $zipId
     * @param ZipValidationStatus $status
     * @return void
     */
    public function setValidationStatus(int|string $zipId, ZipValidationStatus $status): void;

    /**
     * Absolute filesystem path to the zip (for extraction).
     *
     * @param int|string $zipId
     * @return string
     *
     * @throws RuntimeException If not available.
     */
    public function getZipPath(int|string $zipId): string;

    /**
     * Canonical plugin unique name (Studly): Placeholder.name.
     *
     * @param int|string $zipId
     * @return string
     */
    public function getPlaceholderName(int|string $zipId): string;

    /**
     * Plugin placeholder id for DB linking.
     *
     * @param int|string $zipId
     * @return int|string
     */
    public function getPluginPlaceholderId(int|string $zipId): int|string;

    /**
     * Optional human/kebab slug if maintained separately.
     *
     * @param int|string $zipId
     * @return string|null
     */
    public function getSlug(int|string $zipId): ?string;

    /**
     * Strong content fingerprint (e.g., sha256 of the zip).
     *
     * @param int|string $zipId
     * @return string
     */
    public function getFingerprint(int|string $zipId): string;

    /**
     * Hash of the validator configuration used for scans (binds tokens to config).
     *
     * @param int|string $zipId
     * @return string|null Null if not computed.
     */
    public function getValidatorConfigHash(int|string $zipId): ?string;

    /**
     * Persist the absolute path to the canonical installation.json for this zip.
     *
     * @param int|string $zipId
     * @param string $installationJsonPath
     * @return void
     */
    public function recordLogPath(int|string $zipId, string $installationJsonPath): void;

    /**
     * Audit hook: mark the time a validation run completed.
     *
     * @param int|string $zipId
     * @return void
     */
    public function touchValidatedAt(int|string $zipId): void;
}