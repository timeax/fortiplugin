<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations;

use InvalidArgumentException;
use Timeax\FortiPlugin\Installations\Enums\VendorMode;

/**
 * Centralized, chainable policy that drives installer behavior.
 *
 * Defaults match your requirements:
 *  - File scan OFF by default (host may enable)
 *  - Vendor mode = STRIP_BUNDLED_VENDOR
 *  - Verification errors → BREAK
 *  - File-scan errors (when enabled) → ASK
 *
 * You can hydrate from config via ::fromArray() and inspect/serialize via toArray().
 */
final class InstallerPolicy
{
    /** Whether security file scanning (content/token/AST) is enabled. */
    private bool $fileScanEnabled = false;

    /** How to treat a bundled vendor directory in the plugin. */
    private VendorMode $vendorMode = VendorMode::STRIP_BUNDLED_VENDOR;

    /** Token TTLs (seconds) for flows coordinated by InstallerTokenManager. */
    private int $backgroundScanTtl = 600; // default 10 minutes
    private int $installOverrideTtl = 600;

    /** Host PSR-4 root (used for per-plugin mapping checks). */
    private string $psr4Root = 'Plugins';

    /** Absolute path to the routes JSON schema (optional, if host wants strict schema validation). */
    private ?string $routeSchemaPath = null;

    /** Names of middleware allowed in route files (empty = host checks elsewhere). */
    private array $middlewareAllowlist = [];

    /** Packages that must never be introduced/overridden by a plugin (e.g., php, laravel/framework). */
    private array $corePackageBlocklist = ['php', 'laravel/framework'];

    /** Decision behaviors */
    private bool $askOnFileScanErrors = true;          // when file scan is enabled and emits errors → ASK
    private bool $breakOnVerificationErrors = true;    // headline verification (composer/config/host/manifest/routes) → BREAK on any error
    private bool $presentForeignPackagesForScan = true; // show foreign packages and offer scan pre-activation

    /** Log locations inside the plugin dir. */
    private string $logsDirName = '.internal/logs';
    private string $installationLogFilename = 'installation.json';

    // ───────────────────────────── Mutators (chainable) ─────────────────────────────
    private bool $breakOnFileScanErrors;

    /** Enable/disable security file scanning (token/AST/etc.). */
    public function enableFileScan(bool $enable = true): self
    {
        $this->fileScanEnabled = $enable;
        return $this;
    }

    public function isFileScanEnabled(): bool
    {
        return $this->fileScanEnabled;
    }

    public function setVendorMode(VendorMode $mode): self
    {
        $this->vendorMode = $mode;
        return $this;
    }

    public function getVendorMode(): VendorMode
    {
        return $this->vendorMode;
    }

    /** Background-scan token TTL (seconds). Clamped to >= 60s. */
    public function setBackgroundScanTtl(int $seconds): self
    {
        $this->backgroundScanTtl = max(60, $seconds);
        return $this;
    }

    public function getBackgroundScanTtl(): int
    {
        return $this->backgroundScanTtl;
    }

    /** Install-override token TTL (seconds). Clamped to >= 60s. */
    public function setInstallOverrideTtl(int $seconds): self
    {
        $this->installOverrideTtl = max(60, $seconds);
        return $this;
    }

    public function getInstallOverrideTtl(): int
    {
        return $this->installOverrideTtl;
    }

    /** Host PSR-4 root (e.g., 'Plugins'). */
    public function setPsr4Root(string $root): self
    {
        $root = trim($root);
        if ($root === '') {
            throw new InvalidArgumentException('psr4Root cannot be empty');
        }
        $this->psr4Root = $root;
        return $this;
    }

    public function getPsr4Root(): string
    {
        return $this->psr4Root;
    }

    /** Absolute path to the route schema json (optional). */
    public function setRouteSchemaPath(?string $path): self
    {
        $this->routeSchemaPath = $path ? rtrim($path) : null;
        return $this;
    }

    public function getRouteSchemaPath(): ?string
    {
        return $this->routeSchemaPath;
    }

    /** Replace the middleware allow-list for route validation. */
    public function setMiddlewareAllowlist(array $names): self
    {
        $this->middlewareAllowlist = array_values(array_unique(array_map('strval', $names)));
        return $this;
    }

    /** @return list<string> */
    public function getMiddlewareAllowlist(): array
    {
        return $this->middlewareAllowlist;
    }

    /** Replace the core package blocklist (packages a plugin must not introduce/override). */
    public function setCorePackageBlocklist(array $packages): self
    {
        $this->corePackageBlocklist = array_values(array_unique(array_map('strval', $packages)));
        return $this;
    }

    /** @return list<string> */
    public function getCorePackageBlocklist(): array
    {
        return $this->corePackageBlocklist;
    }

    /** If true and file scan is enabled, installer returns ASK on scan errors (with token). */
    public function setAskOnFileScanErrors(bool $ask = true): self
    {
        $this->askOnFileScanErrors = $ask;
        return $this;
    }

    public function shouldAskOnFileScanErrors(): bool
    {
        return $this->askOnFileScanErrors;
    }

    /** If true, any verification error (composer/config/host/manifest/routes) forces BREAK. */
    public function setBreakOnVerificationErrors(bool $break = true): self
    {
        $this->breakOnVerificationErrors = $break;
        return $this;
    }

    public function shouldBreakOnVerificationErrors(): bool
    {
        return $this->breakOnVerificationErrors;
    }

    /** Whether to present foreign packages for optional scanning before activation. */
    public function setPresentForeignPackagesForScan(bool $present = true): self
    {
        $this->presentForeignPackagesForScan = $present;
        return $this;
    }

    public function shouldBreakOnFileScanErrors(): bool
    {
        return $this->breakOnFileScanErrors;
    }

    public function setBreakOnFileScanErrors(bool $v): void
    {
        $this->breakOnFileScanErrors = $v;
    }

    public function shouldPresentForeignPackagesForScan(): bool
    {
        return $this->presentForeignPackagesForScan;
    }

    /** Customize logs directory name inside the plugin root (default ".internal/logs"). */
    public function setLogsDirName(string $name): self
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('logsDirName cannot be empty');
        }
        $this->logsDirName = $name;
        return $this;
    }

    public function getLogsDirName(): string
    {
        return $this->logsDirName;
    }

    /** Customize installation log filename (default "installation.json"). */
    public function setInstallationLogFilename(string $name): self
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('installationLogFilename cannot be empty');
        }
        $this->installationLogFilename = $name;
        return $this;
    }

    public function getInstallationLogFilename(): string
    {
        return $this->installationLogFilename;
    }

    // ───────────────────────────── Serialization ─────────────────────────────

    /**
     * Build a policy from a config array. Unknown keys are ignored.
     *
     * Supported keys:
     *  - file_scan (bool)
     *  - vendor_mode ('STRIP_BUNDLED_VENDOR'|'ALLOW_BUNDLED_VENDOR')
     *  - token_ttl: { background_scan:int, install_override:int }
     *  - psr4_root (string)
     *  - route_schema (string|null)
     *  - middleware_allowlist (string[])
     *  - core_package_blocklist (string[])
     *  - ask_on_file_scan_errors (bool)
     *  - break_on_verification_errors (bool)
     *  - present_foreign_packages_for_scan (bool)
     *  - logs_dir_name (string)
     *  - installation_log_filename (string)
     */
    public static function fromArray(array $cfg): self
    {
        $p = new self();

        if (array_key_exists('file_scan', $cfg)) {
            $p->enableFileScan((bool)$cfg['file_scan']);
        }
        if (isset($cfg['vendor_mode'])) {
            $p->setVendorMode(
                $cfg['vendor_mode'] instanceof VendorMode
                    ? $cfg['vendor_mode']
                    : VendorMode::from((string)$cfg['vendor_mode'])
            );
        }
        if (isset($cfg['token_ttl']['background_scan'])) {
            $p->setBackgroundScanTtl((int)$cfg['token_ttl']['background_scan']);
        }
        if (isset($cfg['token_ttl']['install_override'])) {
            $p->setInstallOverrideTtl((int)$cfg['token_ttl']['install_override']);
        }
        if (isset($cfg['psr4_root'])) {
            $p->setPsr4Root((string)$cfg['psr4_root']);
        }
        if (array_key_exists('route_schema', $cfg)) {
            $p->setRouteSchemaPath($cfg['route_schema'] !== null ? (string)$cfg['route_schema'] : null);
        }
        if (isset($cfg['middleware_allowlist']) && is_array($cfg['middleware_allowlist'])) {
            $p->setMiddlewareAllowlist($cfg['middleware_allowlist']);
        }
        if (isset($cfg['core_package_blocklist']) && is_array($cfg['core_package_blocklist'])) {
            $p->setCorePackageBlocklist($cfg['core_package_blocklist']);
        }
        if (array_key_exists('ask_on_file_scan_errors', $cfg)) {
            $p->setAskOnFileScanErrors((bool)$cfg['ask_on_file_scan_errors']);
        }
        if (array_key_exists('break_on_verification_errors', $cfg)) {
            $p->setBreakOnVerificationErrors((bool)$cfg['break_on_verification_errors']);
        }
        if (array_key_exists('present_foreign_packages_for_scan', $cfg)) {
            $p->setPresentForeignPackagesForScan((bool)$cfg['present_foreign_packages_for_scan']);
        }
        if (isset($cfg['logs_dir_name'])) {
            $p->setLogsDirName((string)$cfg['logs_dir_name']);
        }
        if (isset($cfg['installation_log_filename'])) {
            $p->setInstallationLogFilename((string)$cfg['installation_log_filename']);
        }

        if (isset($cfg['break_on_file_scan_errors'])) {
            $p->setBreakOnFileScanErrors((bool)$cfg['break_on_file_scan_errors']);
        }

        return $p;
    }

    /** Export a normalized array snapshot for logs/DB. */
    public function toArray(): array
    {
        return [
            'file_scan' => $this->fileScanEnabled,
            'vendor_mode' => $this->vendorMode->name,
            'token_ttl' => [
                'background_scan' => $this->backgroundScanTtl,
                'install_override' => $this->installOverrideTtl,
            ],
            'psr4_root' => $this->psr4Root,
            'route_schema' => $this->routeSchemaPath,
            'middleware_allowlist' => $this->middlewareAllowlist,
            'core_package_blocklist' => $this->corePackageBlocklist,
            'ask_on_file_scan_errors' => $this->askOnFileScanErrors,
            'break_on_verification_errors' => $this->breakOnVerificationErrors,
            'present_foreign_packages_for_scan' => $this->presentForeignPackagesForScan,
            'logs_dir_name' => $this->logsDirName,
            'installation_log_filename' => $this->installationLogFilename,
        ];
    }
}