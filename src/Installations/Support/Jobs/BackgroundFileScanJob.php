<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support\Jobs;

use JsonException;
use Throwable;
use Timeax\FortiPlugin\Core\PluginPolicy;
use Timeax\FortiPlugin\Core\Security\ContentValidator;
use Timeax\FortiPlugin\Core\Security\PluginSecurityScanner;
use Timeax\FortiPlugin\Core\Security\TokenUsageAnalyzer;
use Timeax\FortiPlugin\Installations\Contracts\ZipRepository;
use Timeax\FortiPlugin\Installations\Enums\ZipValidationStatus;
use Timeax\FortiPlugin\Installations\Events\BackgroundScanCompleted;
use Timeax\FortiPlugin\Installations\Events\BackgroundScanStarted;
use Timeax\FortiPlugin\Installations\InstallerPolicy;
use Timeax\FortiPlugin\Installations\Support\AtomicFilesystem;

/**
 * BackgroundFileScanJob
 *
 * NOTE: This is a lean placeholder to show the flow; refine scanners/config as you see fit.
 */
final class BackgroundFileScanJob
{
    /** @var array<int,string> */
    private array $files;

    /** @param array<int,string> $files */
    public function __construct(
        private readonly string $zipId,
        array                   $files,
        private readonly array  $context = []
    )
    {
        $this->files = array_values(array_unique($files));
    }

    /**
     * @throws JsonException
     */
    public function handle(
        InstallerPolicy  $policy,
        PluginPolicy     $pluginPolicy,
        AtomicFilesystem $afs,
        ZipRepository    $zips
    ): void
    {

        $token = $this->context['token'] ?? null;
        $actor = $this->context['actor'] ?? null;
        $runId = $this->context['run_id'] ?? null;

        event(new BackgroundScanStarted(
            zipId: $this->zipId,
            token: is_string($token) ? $token : null,
            context: array_filter([
                'actor' => $actor,
                'run_id' => $runId,
            ], static fn($v) => $v !== null)
        ));

        $pluginDir = (string)($this->context['plugin_dir'] ?? '');
        $logPath = rtrim($pluginDir, "\\/") . DIRECTORY_SEPARATOR
            . trim($policy->getLogsDirName(), "\\/") . DIRECTORY_SEPARATOR
            . $policy->getInstallationLogFilename();

        $events = [];
        $issues = 0;

        // Prepare scanners with the same plugin policy config
        $content = new ContentValidator($pluginPolicy);
        $ast = new PluginSecurityScanner($pluginPolicy->getConfig(), '[file]');
        $tokens = $pluginPolicy->getForbiddenFunctions();

        foreach ($this->files as $file) {
            // emit start
            $events[] = [
                'title' => 'Background Scan: File',
                'description' => 'Start',
                'stats' => ['filePath' => $file, 'size' => $afs->fs()->fileSize($file)],
                'meta' => ['mode' => 'background'],
            ];

            // Content validator
            try {
                foreach ($content->scanFile($file) as $v) {
                    $issues++;
                    $events[] = $this->asEvent($v['issue'] ?? 'Issue', $file);
                }
            } catch (Throwable $e) {
                $issues++;
                $events[] = $this->asEvent('content.exception: ' . $e->getMessage(), $file);
            }

            // Token usage
            try {
                foreach (TokenUsageAnalyzer::analyzeFile($file, array_map('strtolower', $tokens)) as $v) {
                    $issues++;
                    $events[] = $this->asEvent($v['issue'] ?? 'Issue', $file);
                }
            } catch (Throwable $e) {
                $issues++;
                $events[] = $this->asEvent('token.exception: ' . $e->getMessage(), $file);
            }

            // AST
            try {
                $src = @file_get_contents($file);
                if ($src !== false) {
                    $ast->scanSource($src, $file);
                    foreach ($ast->getMatches() as $match) {
                        $issues++;
                        $events[] = $this->asEvent((string)($match['message'] ?? 'AST violation'), $file);
                    }
                }
            } catch (Throwable $e) {
                $issues++;
                $events[] = $this->asEvent('ast.exception: ' . $e->getMessage(), $file);
            }

            // emit end
            $events[] = [
                'title' => 'Background Scan: File',
                'description' => 'End',
                'stats' => ['filePath' => $file, 'size' => $afs->fs()->fileSize($file)],
                'meta' => ['mode' => 'background'],
            ];
        }

        // Persist background_scan block
        $doc = $afs->fs()->exists($logPath) ? $afs->fs()->readJson($logPath) : [];
        $doc['background_scan'] = [
            'started_at' => gmdate('c'),
            'files' => $this->files,
            'events' => $events,        // verbatim-ish events
            'summary' => ['issues' => $issues, 'status' => $issues > 0 ? 'fail' : 'ok'],
            'finished_at' => gmdate('c'),
        ];
        $afs->writeJsonAtomic($logPath, $doc, true);

        // Update ZIP status
        $zips->setValidationStatus($this->zipId, $issues > 0 ? ZipValidationStatus::FAILED : ZipValidationStatus::VERIFIED);

        $finalStatus = $issues > 0 ? 'fail' : 'ok';
        $token = $this->context['token'] ?? null;
        $runId = $this->context['run_id'] ?? null;
        $actor = $this->context['actor'] ?? null;

        event(new BackgroundScanCompleted(
            zipId: $this->zipId,
            token: is_string($token) ? $token : null,
            result: array_filter([
                'status' => $finalStatus,
                'issues' => $issues,
                'log_path' => $logPath,   // from earlier in handle()
                'run_id' => $runId,
                'actor' => $actor,
            ], static fn($v) => $v !== null)
        ));
    }

    private function asEvent(string $message, string $file): array
    {
        return [
            'title' => 'Background Scan: Security',
            'description' => $message,
            'stats' => ['filePath' => $file],
            'meta' => ['mode' => 'background'],
        ];
    }
}