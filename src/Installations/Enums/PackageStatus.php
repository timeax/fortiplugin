<?php

namespace Timeax\FortiPlugin\Installations\Enums;

enum PackageStatus: string
{
    case VERIFIED = 'verified';
    case UNVERIFIED = 'unverified';
    case PENDING = 'pending';
    case FAILED = 'failed';
}
