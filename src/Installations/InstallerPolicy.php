<?php

namespace Timeax\FortiPlugin\Installations;

use Timeax\FortiPlugin\Installations\Enums\VendorMode;

class InstallerPolicy
{
    private bool $fileScanEnabled = false;
    private VendorMode $vendorMode = VendorMode::STRIP_BUNDLED_VENDOR;

    public function enableFileScan(bool $enable = true): void
    {
        $this->fileScanEnabled = $enable;
    }

    public function isFileScanEnabled(): bool
    {
        return $this->fileScanEnabled;
    }

    public function setVendorMode(VendorMode $mode): void
    {
        $this->vendorMode = $mode;
    }

    public function getVendorMode(): VendorMode
    {
        return $this->vendorMode;
    }
}
