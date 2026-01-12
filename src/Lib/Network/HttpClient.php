<?php

namespace Timeax\FortiPlugin\Lib\Network;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Timeax\FortiPlugin\Core\ChecksModulePermission;
use Timeax\FortiPlugin\Permissions\Evaluation\Dto\NetworkRequest;
use Timeax\FortiPlugin\Support\PluginContext;

class HttpClient extends PendingRequest
{
    use ChecksModulePermission;

    /** @var int max bytes to log for string bodies */
    protected int $maxBodyBytes = 16_384;

    /** @var string[] header keys to redact (case-insensitive) */
    protected array $redactHeaders = [
        'authorization',
        'proxy-authorization',
        'cookie',
        'set-cookie',
        'x-api-key',
        'api-key',
        'x-auth-token',
        'x-csrf-token',
    ];

    protected function checkPermissionFor(string $url, string $verb): void
    {
        $request = new NetworkRequest(
            method: strtoupper($verb),
            url: $url,
            headers: [] // Headers are not easily available here in a normalized way without parsing options
        );

        $this->checkModulePermission($request);
    }

    protected function logRequest(string $method, string $url, array $context = []): void
    {
        $pluginName = PluginContext::getCurrentPluginName() ?? 'unknown';

        // Optional: if your PluginContext supports it, log plugin id too.
        $pluginId = method_exists(PluginContext::class, 'getCurrentPluginId')
            ? PluginContext::getCurrentConfigClass()
            : null;

        Log::channel('plugin')->info('Plugin HTTP request', array_filter([
            'plugin'     => $pluginName,
            'plugin_id'  => $pluginId,
            'method'     => strtoupper($method),
            'url'        => $url,
            'headers'    => $this->sanitizeHeaders($this->options['headers'] ?? []),
            'context'    => $this->sanitizeContext($context),
            'timestamp'  => now()->toIso8601String(),
        ], static fn ($v) => $v !== null));
    }

    protected function sanitizeHeaders(array $headers): array
    {
        $out = [];

        foreach ($headers as $key => $value) {
            $k = is_string($key) ? $key : (string) $key;
            $lower = strtolower($k);

            if (in_array($lower, $this->redactHeaders, true)) {
                $out[$k] = '[REDACTED]';
                continue;
            }

            // Normalize header values (can be string|array)
            if (is_array($value)) {
                $out[$k] = array_map(static fn ($v) => is_scalar($v) ? $v : '[NON_SCALAR]', $value);
            } else {
                $out[$k] = is_scalar($value) ? $value : '[NON_SCALAR]';
            }
        }

        return $out;
    }

    protected function sanitizeContext(array $ctx): array
    {
        // Avoid logging huge/binary/objects directly

        return array_map(function ($value) {
            return $this->sanitizeValue($value);
        }, $ctx);
    }

    protected function sanitizeValue(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return [
                '__type' => 'UploadedFile',
                'name'   => $value->getClientOriginalName(),
                'size'   => $value->getSize(),
                'mime'   => $value->getMimeType(),
            ];
        }

        if (is_resource($value)) {
            return ['__type' => 'resource'];
        }

        if (is_object($value)) {
            // Don’t accidentally serialize big objects / streams
            return ['__type' => 'object', 'class' => $value::class];
        }

        if (is_string($value)) {
            if (strlen($value) > $this->maxBodyBytes) {
                return [
                    '__type' => 'string',
                    'truncated' => true,
                    'length' => strlen($value),
                    'preview' => substr($value, 0, $this->maxBodyBytes),
                ];
            }
            return $value;
        }

        if (is_array($value)) {
            // Keep recursion safe-ish
            return array_map(function ($v) {
                return $this->sanitizeValue($v);
            }, $value);
        }

        return $value; // int/float/bool/null
    }

    public function get($url, $query = []): PromiseInterface|Response
    {
        $this->checkPermissionFor($url, 'get');
        $this->logRequest('get', (string) $url, ['query' => $query]);
        return parent::get($url, $query);
    }

    public function post($url, $data = []): PromiseInterface|Response
    {
        $this->checkPermissionFor($url, 'post');
        $this->logRequest('post', (string) $url, ['payload' => $data]);
        return parent::post($url, $data);
    }

    public function put($url, $data = []): PromiseInterface|Response
    {
        $this->checkPermissionFor($url, 'put');
        $this->logRequest('put', (string) $url, ['payload' => $data]);
        return parent::put($url, $data);
    }

    public function patch($url, $data = []): PromiseInterface|Response
    {
        $this->checkPermissionFor($url, 'patch');
        $this->logRequest('patch', (string) $url, ['payload' => $data]);
        return parent::patch($url, $data);
    }

    public function delete($url, $data = []): PromiseInterface|Response
    {
        $this->checkPermissionFor($url, 'delete');
        $this->logRequest('delete', (string) $url, ['payload' => $data]);
        return parent::delete($url, $data);
    }

    public function send($method, $url, array $options = []): PromiseInterface|Response
    {
        $m = strtolower((string) $method);

        $this->checkPermissionFor((string) $url, $m);

        // Extract the most useful signal for logs depending on how the request is built
        $context = [];
        if (isset($options['query'])) {
            $context['query'] = $options['query'];
        }
        if (array_key_exists('json', $options)) {
            $context['json'] = $options['json'];
        } elseif (array_key_exists('form_params', $options)) {
            $context['form_params'] = $options['form_params'];
        } elseif (array_key_exists('body', $options)) {
            $context['body'] = $options['body'];
        }

        // Also log per-request headers (merged view)
        if (isset($options['headers'])) {
            $context['request_headers'] = $this->sanitizeHeaders($options['headers']);
        }

        $this->logRequest($m, (string) $url, $context);

        return parent::send(strtoupper($m), $url, $options);
    }
}