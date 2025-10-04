<?php

namespace Timeax\FortiPlugin\Lib\Network;

use Illuminate\Support\Facades\Log;
use Timeax\FortiPlugin\Support\PluginContext;

trait CurlExecTrait
{
    protected mixed $handle;
    protected string $url;

    public function exec(): string|false
    {
        $this->logExecutionPermission();

        ob_start();
        curl_exec($this->handle);
        $output = ob_get_clean();

        Log::channel('plugin')->info('Plugin CURL exec', [
            'plugin' => PluginContext::getCurrentPluginName(),
            'url' => $this->url,
            'info' => curl_getinfo($this->handle),
            'error' => curl_error($this->handle),
            'output_snippet' => substr($output, 0, 500),
            'timestamp' => now()->toIso8601String(),
        ]);

        return $output;
    }

    public function getInfo(): array
    {
        return curl_getinfo($this->handle);
    }

    public function getError(): string
    {
        return curl_error($this->handle);
    }

    public function getErrno(): int
    {
        return curl_errno($this->handle);
    }

    public function reset(): void
    {
        curl_reset($this->handle);
    }

    public function pause(int $bitmask): int
    {
        return curl_pause($this->handle, $bitmask);
    }

    public function strerror(int $errorNumber): string
    {
        return curl_strerror($errorNumber);
    }

    public function escape(string $string): string|false
    {
        return curl_escape($this->handle, $string);
    }

    public function unescape(string $string): string|false
    {
        return curl_unescape($this->handle, $string);
    }

    public function upkeep(): bool
    {
        return curl_upkeep($this->handle);
    }

    protected function logExecutionPermission(): void
    {
        $host = parse_url($this->url, PHP_URL_HOST) ?? '*';

        if (method_exists($this, 'checkModulePermission')) {
            $this->checkModulePermission('execute', $host, 'curl');
        }
    }
}