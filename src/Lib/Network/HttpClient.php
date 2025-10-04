<?php

namespace Timeax\FortiPlugin\Lib\Network;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Timeax\FortiPlugin\Support\PluginContext;
use Timeax\FortiPlugin\Core\ChecksModulePermission;

class HttpClient extends PendingRequest
{
    use ChecksModulePermission;

    protected function checkPermissionFor(string $url, string $verb): void
    {
        $host = parse_url($url, PHP_URL_HOST) ?? '*';
        $this->checkModulePermission($verb, $host, 'http');
    }

    protected function logRequest(string $method, string $url, array $data = []): void
    {
        $plugin = PluginContext::getCurrentPluginName() ?? 'unknown';

        Log::channel('plugin')->info('Plugin HTTP request', [
            'plugin' => $plugin,
            'method' => strtoupper($method),
            'url' => $url,
            'headers' => $this->options['headers'] ?? [],
            'payload' => $data,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function get($url, $query = []): PromiseInterface|Response
    {
        $this->checkPermissionFor($url, 'get');
        $this->logRequest('get', $url, $query);
        return parent::get($url, $query);
    }

    public function post($url, $data = []): PromiseInterface|Response
    {
        $this->checkPermissionFor($url, 'post');
        $this->logRequest('post', $url, $data);
        return parent::post($url, $data);
    }

    public function put($url, $data = []): PromiseInterface|Response
    {
        $this->checkPermissionFor($url, 'put');
        $this->logRequest('put', $url, $data);
        return parent::put($url, $data);
    }

    public function patch($url, $data = []): PromiseInterface|Response
    {
        $this->checkPermissionFor($url, 'patch');
        $this->logRequest('patch', $url, $data);
        return parent::patch($url, $data);
    }

    public function delete($url, $data = []): PromiseInterface|Response
    {
        $this->checkPermissionFor($url, 'delete');
        $this->logRequest('delete', $url, $data);
        return parent::delete($url, $data);
    }

    public function send($method, $url, array $options = []): PromiseInterface|Response
    {
        $method = strtolower($method);
        $this->checkPermissionFor($url, $method);
        $this->logRequest($method, $url, $options['body'] ?? []);
        return parent::send(strtoupper($method), $url, $options);
    }
}