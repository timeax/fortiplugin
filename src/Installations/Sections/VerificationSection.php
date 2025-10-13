<?php

namespace Timeax\FortiPlugin\Installations\Sections;

use DateTimeImmutable;
use Timeax\FortiPlugin\Installations\Support\InstallationLogStore;
use Timeax\FortiPlugin\Installations\Support\ValidatorBridge;
use Timeax\FortiPlugin\Services\ValidatorService;

class VerificationSection
{
    /**
     * Runs mandatory program-integrity checks via ValidatorService, bridges emits verbatim
     * to the unified emitter and to InstallationLogStore, persists a verification snapshot,
     * and returns a summary array suitable for onValidationEnd().
     */
    public function run(
        ValidatorService $validator,
        string $stagingRoot,
        InstallationLogStore $logStore,
        string $installRoot,
        ?callable $unifiedEmitter = null,
    ): array {
        // Bridge validator emits to logs (verbatim) and optional unified emitter via ValidatorBridge
        $emitter = $unifiedEmitter ? new class($unifiedEmitter) implements \Timeax\FortiPlugin\Installations\Contracts\Emitter {
            public function __construct(private $fn) {}
            public function __invoke(array $payload): void { ($this->fn)($payload); }
        } : null;
        $bridge = new ValidatorBridge($logStore, $installRoot, $emitter);

        // Execute validator
        $validator->run($stagingRoot, [$bridge, 'emit']);

        // Build summary and persist snapshot
        $summary = [
            'status' => $validator->shouldFail() ? 'fail' : 'pass',
            'errors' => $validator->getFormattedLog(),
            'warnings' => [],
            'finished_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];
        $logStore->setVerification($installRoot, $summary);

        return $summary;
    }
}
