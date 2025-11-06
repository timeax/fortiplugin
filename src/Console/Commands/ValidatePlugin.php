<?php

namespace Timeax\FortiPlugin\Console\Commands;

use Closure;
use Illuminate\Console\Command;
use Throwable;
use Timeax\FortiPlugin\Services\PolicyService;
use Timeax\FortiPlugin\Services\ValidatorService;
use Timeax\FortiPlugin\Traits\AuthenticateSession;

class ValidatePlugin extends Command
{
    use AuthenticateSession, Shared;

    protected $signature = 'fp:validate
        {name : Plugin directory name, e.g., OrdersPlugin}
        {--host-config : Fetch validator config from the connected host}
        {--quiet : Suppress validation progress output}';

    protected $description = 'Run plugin validation only (no packaging).';

    public function handle(): int
    {
        try {
            $name = (string)$this->argument('name');
            $pluginPath = $this->getPath($name);
            if (!is_dir($pluginPath)) {
                $this->error("Plugin not found: $pluginPath");
                return self::FAILURE;
            }

            $validatorConfig = [];

            if ($this->option('host-config')) {
                // Ensure session and retrieve validator_config via pack/handshake
                $session = $this->auth();
                if (!$session) return self::FAILURE;

                $resp = $this->getHttp()?->post('/forti/pack/handshake');
                $handshake = $this->safeJson($resp);
                if (!($handshake['ok'] ?? false)) {
                    $this->error('Failed to retrieve host validator configuration.');
                    return self::FAILURE;
                }
                $validatorConfig = (array)($handshake['validator_config'] ?? []);
            }

            /** @var PolicyService $policySvc */
            $policySvc = app(PolicyService::class);
            $policy = $policySvc->makePolicy();

            $validator = new ValidatorService($policy, $validatorConfig);
            $emit = $this->option('quiet') ? null : $this->makeEmitCallback();
            $summary = $validator->run($pluginPath, $emit);

            // Final output
            $issues = (int)($summary['total_issues'] ?? 0);
            $files = (int)($summary['files_scanned'] ?? 0);
            $shouldFail = (bool)($summary['should_fail'] ?? false);

            if (!$this->option('quiet')) {
                $this->line("");
                $this->info("Validation finished. Files scanned: $files, Issues: $issues");
                if ($shouldFail) {
                    $this->warn('Fail policy triggered by validation results.');
                } else {
                    $this->info('Validation passed according to current fail policy.');
                }
            }

            return $shouldFail ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    protected function makeEmitCallback(): Closure
    {
        return $this->initializeShared();
    }

    /**
     * Tiny wrapper to safely decode Guzzle responses.
     */
    protected function safeJson($response): array
    {
        try {
            $code = $response?->getStatusCode();
            $body = (string)$response?->getBody();
            if (!$code || $code < 200 || $code >= 300) {
                return ['ok' => false, 'error' => $body ?: 'Request failed'];
            }
            return json_decode($body, true, 512, JSON_THROW_ON_ERROR) ?? [];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
