# Exceptions

This README lists all files in this folder and their source code.

## ComposerConflict.php

```php
<?php

namespace Timeax\FortiPlugin\Installations\Exceptions;

use RuntimeException;

class ComposerConflict extends RuntimeException
{
    public function __construct(string $message = 'COMPOSER_CORE_CONFLICT', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
```

## DbPersistError.php

```php
<?php

namespace Timeax\FortiPlugin\Installations\Exceptions;

use RuntimeException;

class DbPersistError extends RuntimeException
{
    public function __construct(string $message = 'DB_PERSIST_FAILED', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
```

## FilesystemError.php

```php
<?php

namespace Timeax\FortiPlugin\Installations\Exceptions;

use RuntimeException;

class FilesystemError extends RuntimeException
{
    public function __construct(string $message = 'INSTALL_COPY_OR_PROMOTION_FAILED', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
```

## TokenInvalid.php

```php
<?php

namespace Timeax\FortiPlugin\Installations\Exceptions;

use RuntimeException;

class TokenInvalid extends RuntimeException
{
    public function __construct(string $message = 'TOKEN_INVALID', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
```

## ValidationFailed.php

```php
<?php

namespace Timeax\FortiPlugin\Installations\Exceptions;

use RuntimeException;

class ValidationFailed extends RuntimeException
{
    public function __construct(string $message = 'VALIDATION_FAILED', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
```

## ZipValidationFailed.php

```php
<?php

namespace Timeax\FortiPlugin\Installations\Exceptions;

use RuntimeException;

class ZipValidationFailed extends RuntimeException
{
    public function __construct(string $message = 'ZIP_VALIDATION_FAILED', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
```
