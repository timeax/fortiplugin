<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Facades;

use Illuminate\Support\Facades\Facade;
use Timeax\FortiPlugin\Services\PluginService;
use Timeax\FortiPlugin\Models\Plugin as PluginModel;

/**
 * @method static PluginModel getPlugin(int $pluginId)
 * @method static string installedRoot(int $pluginId)
 * @method static object loadConfig(int $pluginId)
 * @method static array list()
 *
 * @see PluginService
 */
final class Plugin extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PluginService::class;
    }
}
