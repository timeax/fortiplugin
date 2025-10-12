<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Facades;

use Illuminate\Support\Facades\Facade;
use Timeax\FortiPlugin\Services\ValidatorService;

/**
 * @method static array run(string $root, ?callable $emit = null)
 * @method static ValidatorService setIgnoredValidators(array $validators)
 * @method static array getLog()
 * @method static array getFormattedLog()
 * @method static bool shouldFail()
 */
final class Validator extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ValidatorService::class;
    }
}
