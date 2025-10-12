<?php

namespace Timeax\FortiPlugin\Installations\Enums;

enum VendorMode: string
{
    case STRIP_BUNDLED_VENDOR = 'strip_bundled_vendor';
    case ALLOW_BUNDLED_VENDOR = 'allow_bundled_vendor';
}
