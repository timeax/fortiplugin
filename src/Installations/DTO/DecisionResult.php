<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\DTO;

/**
 * @phpstan-type TDecisionStatus 'installed'|'ask'|'break'
 * @phpstan-type TDecisionTokenMeta array{purpose:'background_scan'|'install_override',expires_at:string}|null
 * @phpstan-type TDecisionCounts array{validation_errors:int,scan_errors:int}
 * @phpstan-type TDecision array{
 *   status: TDecisionStatus,
 *   reason?: string,
 *   at: string,
 *   run_id: string,
 *   zip_id: int|string,
 *   fingerprint: string,
 *   validator_config_hash: string,
 *   file_scan_enabled: bool,
 *   token: TDecisionTokenMeta,
 *   last_error_codes?: list<string>,
 *   counts?: TDecisionCounts
 * }
 * @noinspection PhpUndefinedClassInspection
 */
final readonly class DecisionResult implements ArraySerializable
{
    public function __construct(
        public string     $status,                /** @var TDecisionStatus */
        public string     $at,
        public string     $run_id,
        public int|string $zip_id,
        public string     $fingerprint,
        public string     $validator_config_hash,
        public bool       $file_scan_enabled,
        public ?array     $token = null,         /** @var TDecisionTokenMeta */
        public ?string    $reason = null,
        /** @var list<string>|null */
        public ?array     $last_error_codes = null,
        /** @var array{validation_errors:int,scan_errors:int}|null */
        public ?array     $counts = null,
    ) {}

    /** @param TDecision $data */
    public static function fromArray(array $data): static
    {
        return new self(
            $data['status'],
            $data['at'],
            $data['run_id'],
            $data['zip_id'],
            $data['fingerprint'],
            $data['validator_config_hash'],
            (bool)$data['file_scan_enabled'],
            $data['token'] ?? null,
            $data['reason'] ?? null,
            $data['last_error_codes'] ?? null,
            $data['counts'] ?? null,
        );
    }

    /** @return TDecision */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'reason' => $this->reason,
            'at' => $this->at,
            'run_id' => $this->run_id,
            'zip_id' => $this->zip_id,
            'fingerprint' => $this->fingerprint,
            'validator_config_hash' => $this->validator_config_hash,
            'file_scan_enabled' => $this->file_scan_enabled,
            'token' => $this->token,
            'last_error_codes' => $this->last_error_codes,
            'counts' => $this->counts,
        ];
    }
}