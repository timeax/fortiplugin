<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\DTO;

/**
 * @phpstan-type TSectionStatus 'skipped'|'ok'|'warn'|'fail'|'pending'
 * @phpstan-type TVerificationSection array{
 *   status: TSectionStatus,
 *   errors?: list<string>,
 *   warnings?: list<string>
 * }
 * @phpstan-type TFileScanSection array{
 *   enabled: bool,
 *   status: TSectionStatus,
 *   errors?: list<string>
 * }
 * @phpstan-type TZipGate array{ plugin_zip_status: 'verified'|'pending'|'failed'|'unknown' }
 * @phpstan-type TVendorPolicy array{ mode: 'STRIP_BUNDLED_VENDOR'|'ALLOW_BUNDLED_VENDOR' }
 * @phpstan-type TComposerPlan TComposerPlan
 * @phpstan-type TInstallSummary array{
 *   verification: TVerificationSection,
 *   file_scan: TFileScanSection,
 *   zip_validation?: TZipGate,
 *   vendor_policy?: TVendorPolicy,
 *   composer_plan?: TComposerPlan,
 *   packages?: array<string, array{is_foreign:bool,status:'verified'|'unverified'|'pending'|'failed'}>
 * }
 * @noinspection PhpUndefinedClassInspection
 */
final readonly class InstallSummary implements ArraySerializable
{
    /**
     * @param array $verification
     * @param array $file_scan
     * @param array|null $zip_validation
     * @param array|null $vendor_policy
     * @param array|null $composer_plan
     * @param array|null $packages
     */
    public function __construct(
        public array  $verification,
        public array  $file_scan,
        public ?array $zip_validation = null,
        public ?array $vendor_policy = null,
        public ?array $composer_plan = null,
        public ?array $packages = null,
    ) {}

    /** @param TInstallSummary $data */
    public static function fromArray(array $data): static
    {
        return new self(
            $data['verification'],
            $data['file_scan'],
            $data['zip_validation'] ?? null,
            $data['vendor_policy'] ?? null,
            $data['composer_plan'] ?? null,
            $data['packages'] ?? null,
        );
    }

    /** @return TInstallSummary */
    public function toArray(): array
    {
        return [
            'verification' => $this->verification,
            'file_scan' => $this->file_scan,
            'zip_validation' => $this->zip_validation,
            'vendor_policy' => $this->vendor_policy,
            'composer_plan' => $this->composer_plan,
            'packages' => $this->packages,
        ];
    }
}