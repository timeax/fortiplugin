<?php /** @noinspection PhpUnused */

namespace Timeax\FortiPlugin\Services;

use Illuminate\Support\Facades\File;
use JsonException;
use OpenSSLAsymmetricKey;
use RuntimeException;
use SodiumException;
use Timeax\FortiPlugin\Enums\KeyPurpose;
use Timeax\FortiPlugin\Models\HostKey;
use Timeax\FortiPlugin\Support\Encryption;

final class HostKeyService
{
    /**
     * Return the current verifying key (public) for installers.
     * @return array{fingerprint:string, public_pem:string}
     */
    public function currentVerifyKey(string|KeyPurpose $purpose = null): array
    {
        $purpose ?: config('fortiplugin.keys.verify_purpose', 'installer_verify');

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
     * @throws JsonException
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
            'signature_b64' => Encryption::encrypt(base64_encode($sigBin)),
        ];
    }

    /**
     * Verify a signature using a public key (by fingerprint or provided PEM).
     * @throws JsonException|SodiumException
     */
    public function verify(string $data, string $signatureB64, ?string $fingerprint = null, ?string $publicPem = null): bool
    {
        $sig = base64_decode(Encryption::decrypt($signatureB64), true);
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

    public function generate(string $purpose): HostKey
    {
        $bits = (int)config('fortiplugin.keys.bits', 2048);
        $cnf = config('fortiplugin.keys.openssl_cnf') ?: $this->resolveOpensslConfigPath();

        // try with configured bits
        $res = $this->tryMakeKey([
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'config' => $cnf ?: null,
        ]);
        if ($res === false) {
            $errs1 = $this->collectOpenSslErrors();

            // retry with 2048 (Windows builds can reject larger sizes)
            $res = $this->tryMakeKey([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
                'config' => $cnf ?: null,
            ]);
            if ($res === false) {
                $errs2 = $this->collectOpenSslErrors();
                $msg = "Unable to generate RSA keypair. "
                    . ($cnf ? "Tried config: $cnf. " : "")
                    . ($errs1 ? '[pass1] ' . implode(' | ', $errs1) . '. ' : '')
                    . ($errs2 ? '[pass2] ' . implode(' | ', $errs2) . '. ' : '');
                throw new RuntimeException(rtrim($msg));
            }
        }

        // export with a temporary OPENSSL_CONF / RANDFILE
        $privatePem = '';
        $exportOk = $this->withOpenSslEnv($cnf, function () use ($res, &$privatePem) {
            // avoid encryption to reduce RNG/config needs
            $args = ['config' => getenv('OPENSSL_CONF') ?: null, 'encrypt_key' => false];
            return @openssl_pkey_export($res, $privatePem, null, array_filter($args));
        });
        if (!$exportOk) {
            $errs = $this->collectOpenSslErrors();
            throw new RuntimeException('Unable to export private key. ' . implode(' | ', $errs));
        }

        $details = @openssl_pkey_get_details($res);
        if (!$details || empty($details['key'])) {
            throw new RuntimeException('Unable to extract public key.');
        }
        $publicPem = $details['key'];
        $fingerprint = $this->fingerprint($publicPem);

        return HostKey::create([
            'purpose' => $purpose,
            'public_pem' => $publicPem,
            'private_pem' => $privatePem,
            'fingerprint' => $fingerprint,
        ]);
    }

    private function tryMakeKey(array $args): OpenSSLAsymmetricKey|false
    {
        return @openssl_pkey_new(array_filter($args, static fn($v) => $v !== null));
    }

    private function collectOpenSslErrors(): array
    {
        $out = [];
        while ($e = openssl_error_string()) {
            $out[] = $e;
        }
        return $out;
    }

    /**
     * Temporarily sets OPENSSL_CONF (if provided) and a writable RANDFILE on Windows,
     * runs $fn, then restores the environment.
     */
    private function withOpenSslEnv(?string $cnf, callable $fn)
    {
        $restore = [];

        if ($cnf && is_file($cnf)) {
            $restore['OPENSSL_CONF'] = getenv('OPENSSL_CONF') !== false ? getenv('OPENSSL_CONF') : null;
            putenv('OPENSSL_CONF=' . $cnf);
        }

        // Windows-only: ensure a writable RANDFILE to satisfy configs referencing it
        if (DIRECTORY_SEPARATOR === '\\') {
            $hadRand = getenv('RANDFILE') !== false;
            if (!$hadRand || !is_file((string)getenv('RANDFILE'))) {
                $randPath = storage_path('app/forti/openssl/randseed.rnd');
                // Idempotent: no warnings if directory already exists
                File::ensureDirectoryExists(dirname($randPath), 0777);
                // Create the file atomically if missing (no error if it already exists)
                $this->ensureFileExistsAtomic($randPath);
                $restore['RANDFILE'] = $hadRand ? getenv('RANDFILE') : null;
                putenv('RANDFILE=' . $randPath);
            }
        }

        try {
            return $fn();
        } finally {
            foreach ($restore as $k => $v) {
                if ($v === null) {
                    // unset
                    putenv($k);
                } else {
                    putenv($k . '=' . $v);
                }
            }
        }
    }

    /** Ensure a file exists without race-condition warnings (atomic create). */
    private function ensureFileExistsAtomic(string $path): void
    {
        clearstatcache(true, $path);
        if (is_file($path)) {
            return;
        }

        $dir = dirname($path);
        File::ensureDirectoryExists($dir, 0777);

        // Try atomic create; if another process wins, this returns false but that's fine.
        $h = @fopen($path, 'xb');
        if ($h !== false) {
            fclose($h);
        } elseif (!is_file($path)) {
            // If it still doesn't exist (other error), best-effort create.
            @touch($path);
        }

        // Ensure writable (best-effort; ignores failures)
        if (is_file($path) && !is_writable($path)) {
            @chmod($path, 0666 & ~umask());
        }
    }

    /** Best-effort openssl.cnf discovery for Windows PHP bundles. */
    private function resolveOpensslConfigPath(): ?string
    {
        $env = getenv('OPENSSL_CONF');
        if ($env && is_file($env)) return $env;

        $candidates = [
            dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf',
            dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'openssl.cnf',
        ];
        foreach ($candidates as $p) {
            if (is_file($p)) return $p;
        }
        return null;
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