<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Facades;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Timeax\FortiPlugin\Models\PluginZip as PluginZipModel;
use Timeax\FortiPlugin\Services\Plugins\PluginZipService;

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
