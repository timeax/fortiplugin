<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Enums;

/**
 * High-level validation status for an uploaded plugin zip (PluginZip.validation_status).
 *
 * - VERIFIED → Headline checks passed (and any host-required scans completed).
 * - PENDING  → Background validation/scans in progress.
 * - FAILED   → One or more blocking issues detected.
 * - UNKNOWN  → Not checked or source didn’t provide a recognized status.
 *
 * NOTE: When mapping from Eloquent models, keep the translation consistent
 * with your model enum (e.g., valid/pending/failed/unverified → VERIFIED/PENDING/FAILED/UNKNOWN).
 */
enum ZipValidationStatus: string
{
    case VERIFIED = 'verified';
    case PENDING  = 'pending';
    case FAILED   = 'failed';
    case UNKNOWN  = 'unknown';
}