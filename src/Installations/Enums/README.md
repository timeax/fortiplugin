# Enums

This README lists all files in this folder and their source code.

## Install.php

```php
<?php

namespace Timeax\FortiPlugin\Installations\Enums;

enum Install: string
{
    case BREAK = 'break';
    case INSTALL = 'install';
    case ASK = 'ask';
}
```

## PackageStatus.php

```php
<?php

namespace Timeax\FortiPlugin\Installations\Enums;

enum PackageStatus: string
{
    case VERIFIED = 'verified';
    case UNVERIFIED = 'unverified';
    case PENDING = 'pending';
    case FAILED = 'failed';
}
```

## VendorMode.php

```php
<?php

namespace Timeax\FortiPlugin\Installations\Enums;

enum VendorMode: string
{
    case STRIP_BUNDLED_VENDOR = 'strip_bundled_vendor';
    case ALLOW_BUNDLED_VENDOR = 'allow_bundled_vendor';
}
```

## ZipValidationStatus.php

```php
<?php

namespace Timeax\FortiPlugin\Installations\Enums;

enum ZipValidationStatus: string
{
    case VERIFIED = 'verified';
    case PENDING = 'pending';
    case FAILED = 'failed';
    case UNKNOWN = 'unknown';
}
```
