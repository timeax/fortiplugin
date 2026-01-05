<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Facades;

use Illuminate\Support\Facades\Facade;
use Timeax\FortiPlugin\Services\PluginZipService;
use Timeax\FortiPlugin\Models\PluginZip as PluginZipModel;
use Illuminate\Support\Collection;

/**
 * @method static Collection list(int $limit = 100)
 * @method static array install(int $zipId, ?string $installerToken = null, ?string $runId = null, ?string $actor = null)
 * @method static array delete(int $zipId)
 * @method static PluginZipModel getZip(int $zipId)
 *
 * @see PluginZipService
 */
final class PluginZip extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PluginZipService::class;
    }
}
