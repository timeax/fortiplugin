<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Core\Security;

use InvalidArgumentException;
use JsonException;
use Timeax\FortiPlugin\Core\Exceptions\HostConfigException;
use Timeax\FortiPlugin\Support\HostConfigToPluginSettings;

final class HostConfigValidator
{
    /**
     * Pack-time validation for hostConfig.settings using HostConfigToPluginSettings rules.
     *
     * @throws HostConfigException
     */
    public static function validate(array $hostConfig): void
    {
        try {
            // Strict validation happens inside makeRows(); plugin_id is irrelevant at pack-time.
            HostConfigToPluginSettings::makeRows(0, $hostConfig);
        } catch (InvalidArgumentException|JsonException $e) {
            // Match old style: throw your domain exception with a message only.
            throw new HostConfigException($e->getMessage());
        }
    }
}
