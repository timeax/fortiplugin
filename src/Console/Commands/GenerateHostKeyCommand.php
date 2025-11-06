<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Throwable;
use Timeax\FortiPlugin\Services\HostKeyService;

class GenerateHostKeyCommand extends Command
{
    /** @var string */
    protected $signature = 'fp:generate
        {purpose? : Optional purpose/label for this host key}
        {--purpose= : Optional purpose/label (overrides the positional argument if set)}';

    /** @var string */
    protected $description = 'Generate a new host key pair using HostKeyService.';

    public function handle(): int
    {
        try {
            $purposeArg = $this->argument('purpose');
            $purposeOpt = $this->option('purpose');
            $purpose = $purposeOpt !== null && $purposeOpt !== '' ? $purposeOpt : ($purposeArg ?: null);

            /** @var HostKeyService $service */
            $service = $this->laravel->make(HostKeyService::class);

            // Call generate with optional purpose
            $result = $service->generate($purpose);

            // Prefer not to print private key material. Summarize safely.
            $payload = is_object($result) && method_exists($result, 'toArray')
                ? $result->toArray()
                : (is_array($result) ? $result : []);

            $id = Arr::get($payload, 'id') ?? Arr::get($payload, 'key_id') ?? Arr::get($payload, 'kid');
            $purposeOut = Arr::get($payload, 'purpose', $purpose);
            $fingerprint = Arr::get($payload, 'fingerprint') ?? Arr::get($payload, 'fp');
            $publicKey = Arr::get($payload, 'public_key') ?? Arr::get($payload, 'public') ?? Arr::get($payload, 'public_pem');

            $this->info('Host key generated successfully.');
            $this->line('');
            $this->line('== Host Key =======================================');
            if ($id !== null) {
                $this->line('ID:           ' . $id);
            }
            if ($purposeOut !== null) {
                $this->line('Purpose:      ' . $purposeOut);
            }
            if ($fingerprint) {
                $this->line('Fingerprint:  ' . $fingerprint);
            }
            if ($publicKey) {
                $this->line('Public Key:');
                $this->line($publicKey);
            }
            $this->line('===================================================');
            $this->line('');
            $this->line('Note: Private key material is stored securely and not printed here.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Failed to generate host key: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}