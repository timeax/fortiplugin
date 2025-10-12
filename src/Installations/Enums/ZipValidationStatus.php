<?php

namespace Timeax\FortiPlugin\Installations\Enums;

enum ZipValidationStatus: string
{
    case VERIFIED = 'verified';
    case PENDING = 'pending';
    case FAILED = 'failed';
    case UNKNOWN = 'unknown';
}
