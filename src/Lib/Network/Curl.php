<?php

namespace Timeax\FortiPlugin\Lib\Network;

use JsonException;
use RuntimeException;
use Timeax\FortiPlugin\Core\ChecksModulePermission;
use Timeax\FortiPlugin\Support\PluginContext;
use Illuminate\Support\Facades\Log;

class Curl
{
    use ChecksModulePermission;

    public function exec(string $url, array $options = []): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?? '*';

        $this->checkModulePermission(
            permissions: 'execute',
            type: 'curl',
            target: $host
        );

        $ch = curl_init($url);

        foreach ($options as $key => $value) {
            curl_setopt($ch, $key, $value);
        }

        ob_start();
        curl_exec($ch);
        $output = ob_get_clean();

        $info = curl_getinfo($ch);
        $error = curl_error($ch);

        curl_close($ch);

        Log::channel('plugin')->info('Plugin CURL request', [
            'plugin' => PluginContext::getCurrentPluginName(),
            'url' => $url,
            'options' => $options,
            'info' => $info,
            'error' => $error,
            'output_snippet' => substr($output, 0, 500),
            'timestamp' => now()->toIso8601String()
        ]);

        return $output;
    }

    /**
     * @throws JsonException
     */
    public function execJson(string $url, array $options = []): array
    {
        $output = $this->exec($url, $options);

        $json = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid JSON response from CURL request to $url");
        }

        return $json;
    }

    public static function init(string $url): CurlSession
    {
        return new CurlSession($url);
    }
}