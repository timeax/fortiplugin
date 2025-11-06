<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

/**
 * Canonical event titles for installer + validator streams.
 *
 * Use these for $payload['title'] so the UI and logs are consistent.
 * The payload shape stays the unified emitter contract:
 *   [
 *     'title'       => Events::VALIDATION_START,
 *     'description' => string|null,
 *     'error'       => array|null,               // optional structured error
 *     'stats'       => ['filePath'=>?, 'size'=>?],
 *     'meta'        => array|null                // ALWAYS pass through verbatim if provided by validators
 *   ]
 */
final class Events
{
    // — Lifecycle
    public const INIT                = 'INIT';
    public const VALIDATION_START    = 'VALIDATION_START';
    public const VALIDATION_END      = 'VALIDATION_END';
    public const INSTALLER_START     = 'INSTALLER_START';
    public const INSTALLER_END       = 'INSTALLER_END';

    // — PSR-4 checks
    public const PSR4_CHECK_START    = 'PSR4_CHECK_START';
    public const PSR4_CHECK_OK       = 'PSR4_CHECK_OK';
    public const PSR4_CHECK_FAIL     = 'PSR4_CHECK_FAIL';

    // — Route validation / queuing
    public const ROUTES_CHECK_START  = 'ROUTES_CHECK_START';
    public const ROUTES_CHECK_OK     = 'ROUTES_CHECK_OK';
    public const ROUTES_CHECK_FAIL   = 'ROUTES_CHECK_FAIL';
    public const ROUTES_QUEUED       = 'ROUTES_QUEUED';

    // — Security scanning (when host enables file scan)
    public const FILE_SCAN_START     = 'FILE_SCAN_START';
    public const FILE_SCAN_END       = 'FILE_SCAN_END';
    public const FILE_SCAN_ERRORS    = 'FILE_SCAN_ERRORS';

    // — Zip / status gate
    public const ZIP_STATUS_CHECK    = 'ZIP_STATUS_CHECK';
    public const ZIP_STATUS_PENDING  = 'ZIP_STATUS_PENDING';
    public const ZIP_STATUS_VERIFIED = 'ZIP_STATUS_VERIFIED';
    public const ZIP_STATUS_FAILED   = 'ZIP_STATUS_FAILED';

    // — Composer / packages
    public const COMPOSER_COLLECT    = 'COMPOSER_COLLECT';     // build package map
    public const COMPOSER_PLAN_READY = 'COMPOSER_PLAN_READY';  // dry-run actions built
    public const VENDOR_POLICY       = 'VENDOR_POLICY';        // allow/strip bundled vendor

    // — Tokens (ASK + resumes)
    public const TOKEN_ISSUED        = 'TOKEN_ISSUED';
    public const TOKEN_VALID         = 'TOKEN_VALID';
    public const TOKEN_INVALID       = 'TOKEN_INVALID';

    // — Decision + persistence
    public const DECISION_ASK        = 'DECISION_ASK';
    public const DECISION_INSTALL    = 'DECISION_INSTALL';
    public const DECISION_BREAK      = 'DECISION_BREAK';
    public const SUMMARY_PERSISTED   = 'SUMMARY_PERSISTED';

    // — Activation (reserved for the Activator module)
    public const ACTIVATION_START    = 'ACTIVATION_START';
    public const ACTIVATION_END      = 'ACTIVATION_END';
    public const INSTALL_DECISION = 'INSTALL_DECISION';
    public const ZIP_STATUS_UNKNOWN = 'ZIP_STATUS_UNKNOWN';
    public const TOKEN_EXTENDED = 'TOKEN_EXTENDED';
    public const COMPOSER_PLAN_FAIL = 'COMPOSER_PLAN_FAIL';
}