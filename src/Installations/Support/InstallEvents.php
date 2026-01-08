<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

/**
 * Machine-stable dotted event keys for the installation package.
 * Prefix: installation.*
 */
final class InstallEvents
{
    // — Run Lifecycle
    public const RUN_START             = 'installation.run.start';
    public const RUN_END               = 'installation.run.end';
    public const RUN_FAIL              = 'installation.run.fail';

    // — Validation & Scanning
    public const VALIDATION_START      = 'installation.validation.start';
    public const VALIDATION_END        = 'installation.validation.end';
    public const FILE_SCAN_START       = 'installation.filescan.start';
    public const FILE_SCAN_END         = 'installation.filescan.end';

    // — Tokens
    public const TOKEN_ISSUED          = 'installation.token.issued';
    public const TOKEN_VALID           = 'installation.token.valid';
    public const TOKEN_INVALID         = 'installation.token.invalid';

    // — Decisions
    public const DECISION_ASK          = 'installation.decision.ask';
    public const DECISION_INSTALL      = 'installation.decision.install';
    public const DECISION_BREAK        = 'installation.decision.break';

    // — Assets & Summary
    public const UI_ASSETS_START       = 'installation.ui_assets.publish_start';
    public const UI_ASSETS_END         = 'installation.ui_assets.publish_end';
    public const SUMMARY_PERSIST_START = 'installation.summary.persist_start';
    public const SUMMARY_PERSIST_END   = 'installation.summary.persist_end';

    // — Cleanup
    public const CLEANUP_END           = 'installation.cleanup.end';
}
