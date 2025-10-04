<?php /** @noinspection PhpUnused */

namespace Timeax\FortiPlugin\Services;

use RuntimeException;
use Timeax\FortiPlugin\Models\HostKey;

final class HostKeyService
{
    /**
     * Return the current verifying key (public) for installers.
     * @return array{fingerprint:string, public_pem:string}
     */
    public function currentVerifyKey(): array
    {
        $purpose = config('fortiplugin.keys.verify_purpose', 'installer_verify');

        $key = HostKey::query()
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        if (!$key) {
            throw new RuntimeException('No host verify key found (purpose=' . $purpose . ').');
        }

        return [
            'fingerprint' => $key->fingerprint,
            'public_pem' => $key->public_pem,
        ];
    }

    /**
     * Sign arbitrary data with the current signing key.
     * @return array{alg:string,fingerprint:string,signature_b64:string}
     */
    public function sign(string $data): array
    {
        $purpose = config('fortiplugin.keys.sign_purpose', 'packager_sign');
        $digest = (int)config('fortiplugin.keys.digest', OPENSSL_ALGO_SHA256);

        $key = HostKey::query()
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        if (!$key || empty($key->private_pem)) {
            throw new RuntimeException('No host signing key available (purpose=' . $purpose . ').');
        }

        $privateKey = openssl_pkey_get_private($key->private_pem);
        if (!$privateKey) {
            throw new RuntimeException('Invalid private key in HostKey#' . $key->id);
        }

        $ok = openssl_sign($data, $sigBin, $privateKey, $digest);
        // NOTE: openssl_free_key() is deprecated; let GC handle the resource/object.

        if (!$ok) {
            throw new RuntimeException('Signing failed.');
        }

        return [
            'alg' => (string)config('fortiplugin.keys.algo', 'RS256'),
            'fingerprint' => $key->fingerprint,
            'signature_b64' => base64_encode($sigBin),
        ];
    }

    /** Verify a signature using a public key (by fingerprint or provided PEM). */
    public function verify(string $data, string $signatureB64, ?string $fingerprint = null, ?string $publicPem = null): bool
    {
        $sig = base64_decode($signatureB64, true);
        if ($sig === false) {
            return false;
        }

        if (!$publicPem) {
            if (!$fingerprint) {
                throw new RuntimeException('Either publicPem or fingerprint must be provided for verification.');
            }
            $publicPem = $this->publicByFingerprint($fingerprint);
        }

        $publicKey = openssl_pkey_get_public($publicPem);
        if (!$publicKey) {
            return false;
        }

        $digest = (int)config('fortiplugin.keys.digest', OPENSSL_ALGO_SHA256);
        $res = openssl_verify($data, $sig, $publicKey, $digest);

        return $res === 1; // 1 = valid, 0 = invalid, -1 = error
    }

    /** Create and persist a new keypair for a purpose. */
    public function generate(string $purpose): HostKey
    {
        $bits = (int)config('fortiplugin.keys.bits', 2048);

        $res = openssl_pkey_new([
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if (!$res) {
            throw new RuntimeException('Unable to generate RSA keypair.');
        }

        if (!openssl_pkey_export($res, $privatePem)) {
            throw new RuntimeException('Unable to export private key.');
        }

        $details = openssl_pkey_get_details($res);
        if (!$details || empty($details['key'])) {
            throw new RuntimeException('Unable to extract public key.');
        }
        $publicPem = $details['key'];

        $fingerprint = $this->fingerprint($publicPem);

        return HostKey::create([
            'purpose' => $purpose,
            'public_pem' => $publicPem,
            'private_pem' => $privatePem, // Consider encrypting or storing in KMS.
            'fingerprint' => $fingerprint,
        ]);
    }

    /** Mark current key rotated and generate a new one. */
    public function rotate(string $purpose): HostKey
    {
        $current = HostKey::query()
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        if ($current && !$current->rotated_at) {
            $current->rotated_at = now();
            $current->save();
        }

        return $this->generate($purpose);
    }

    // ---- internals ----

    private function publicByFingerprint(string $fp): string
    {
        $key = HostKey::query()->where('fingerprint', $fp)->first();
        if (!$key) {
            throw new RuntimeException('HostKey not found for fingerprint ' . $fp);
        }
        return $key->public_pem;
    }

    /** SHA-256 over DER SubjectPublicKeyInfo bytes (stable fingerprint). */
    public function fingerprint(string $publicPem): string
    {
        $der = $this->pemToDer($publicPem);
        return hash('sha256', $der);
    }

    private function pemToDer(string $pem): string
    {
        $clean = preg_replace('/-----BEGIN PUBLIC KEY-----|-----END PUBLIC KEY-----|\s+/', '', $pem);
        $bin = base64_decode($clean, true);
        if ($bin === false) {
            throw new RuntimeException('Invalid PEM format.');
        }
        return $bin;
    }
}