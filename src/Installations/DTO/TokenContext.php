<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\DTO;

/**
 * @phpstan-type TTokenPurpose 'background_scan'|'install_override'
 * @phpstan-type TTokenClaims array{
 *   purpose: TTokenPurpose,
 *   zip_id: int|string,
 *   fingerprint: string,
 *   validator_config_hash: string,
 *   actor: string,
 *   exp: int,
 *   nonce: string,
 *   run_id: string
 * }
 */
final readonly class TokenContext implements ArraySerializable
{
    public function __construct(
        public string     $purpose,               /** @var TTokenPurpose */
        public int|string $zip_id,
        public string     $fingerprint,
        public string     $validator_config_hash,
        public string     $actor,
        public int        $exp,
        public string     $nonce,
        public string     $run_id,
    ) {}

    /** @param TTokenClaims $data */
    public static function fromArray(array $data): static
    {
        return new self(
            $data['purpose'],
            $data['zip_id'],
            $data['fingerprint'],
            $data['validator_config_hash'],
            $data['actor'],
            (int)$data['exp'],
            $data['nonce'],
            $data['run_id'],
        );
    }

    /** @return TTokenClaims */
    public function toArray(): array
    {
        return [
            'purpose' => $this->purpose,
            'zip_id' => $this->zip_id,
            'fingerprint' => $this->fingerprint,
            'validator_config_hash' => $this->validator_config_hash,
            'actor' => $this->actor,
            'exp' => $this->exp,
            'nonce' => $this->nonce,
            'run_id' => $this->run_id,
        ];
    }
}