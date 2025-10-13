<?php

namespace Timeax\FortiPlugin\Installations\Sections;

use Timeax\FortiPlugin\Installations\Enums\VendorMode;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;

class VendorPolicySection
{
    /**
     * Decide vendor policy and persist it to installation.json.
     * Returns array with keys: mode (string).
     */
    public function run(InstallerPolicy $policy, InstallationLogStore $logStore, string $installRoot, ?callable $emit = null): array
    {
        $mode = $policy->getVendorMode();
        $modeValue = $mode instanceof VendorMode ? $mode->value : VendorMode::STRIP_BUNDLED_VENDOR->value;

        // Persist to installation.json
        $vendorPolicy = [
            'mode' => $modeValue,
        ];
        $logStore->setVendorPolicy($installRoot, $vendorPolicy);

        // Emit installer event 
        if ($emit) {
            try {
                $emit([
                    'title' => 'Installer: Vendor Policy',
                    'description' => 'Vendor policy set to ' . $modeValue,
                    'error' => null,
                    'stats' => ['filePath' => null, 'size' => null],
                    'meta' => $vendorPolicy,
                ]);
            } catch (\Throwable $_) { /* swallow */ }
        }
        // Also append to installer emits for completeness
        try {
            $logStore->appendInstallerEmit($installRoot, [
                'title' => 'Installer: Vendor Policy',
                'description' => 'Vendor policy set to ' . $modeValue,
                'error' => null,
                'stats' => ['filePath' => null, 'size' => null],
                'meta' => $vendorPolicy,
            ]);
        } catch (\Throwable $_) {}

        return ['mode' => $modeValue];
    }
}
