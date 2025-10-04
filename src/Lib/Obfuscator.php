<?php /** @noinspection EncryptionInitializationVectorRandomnessInspection */

/** @noinspection SpellCheckingInspection */

namespace Timeax\FortiPlugin\Lib;

use DeflateContext;
use InflateContext;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Timeax\FortiPlugin\Exceptions\PermissionDeniedException;
use Timeax\FortiPlugin\Lib\Utils\ObfuscatorUtil;
use Timeax\FortiPlugin\Core\ChecksModulePermission;

/**
 * Permission-gated wrappers for sensitive encoder/decoder / obfuscator functions.
 *
 * Plugins MUST call these methods instead of calling the PHP functions directly.
 */
class Obfuscator
{
    use ObfuscatorUtil;

    protected string $type = 'module';
    protected string $target = 'obfuscator';

    use ChecksModulePermission;

    /**
     * Ensure the plugin has permission to use a given obfuscator function.
     *
     * @throws PermissionDeniedException
     */
    protected function ensurePermission(string $capability): void
    {
        $permission = 'use-obfuscator:' . $capability;
        $this->checkModulePermission($permission);
    }


    // ----------------------
    // Base64
    // ----------------------

    public function encodeBase64(string $input): string
    {
        $this->ensurePermission('base64_encode');
        if (!function_exists('base64_encode')) {
            throw new RuntimeException('base64_encode is not available');
        }
        return base64_encode($input);
    }

    public function decodeBase64(string $input, bool $strict = false): string|false
    {
        $this->ensurePermission('base64_decode');
        if (!function_exists('base64_decode')) {
            throw new RuntimeException('base64_decode is not available');
        }
        return base64_decode($input, $strict);
    }

    // ----------------------
    // JSON
    // ----------------------

    /**
     * @throws JsonException
     */
    public function encodeJson(mixed $input, int $flags = 0, int $depth = 512): string|false
    {
        $this->ensurePermission('json_encode');
        if (!function_exists('json_encode')) {
            throw new RuntimeException('json_encode is not available');
        }
        return json_encode($input, JSON_THROW_ON_ERROR | $flags, $depth);
    }

    /**
     * @throws JsonException
     */
    public function decodeJson(string $input, bool $assoc = false, int $depth = 512, int $flags = 0): mixed
    {
        $this->ensurePermission('json_decode');
        if (!function_exists('json_decode')) {
            throw new RuntimeException('json_decode is not available');
        }
        return json_decode($input, $assoc, $depth, JSON_THROW_ON_ERROR | $flags);
    }

    // ----------------------
    // GZIP / zlib
    // ----------------------

    public function compressGz(string $input, int $level = -1, ?int $encoding = null): string|false
    {
        $this->ensurePermission('gzencode');
        if (!function_exists('gzencode')) {
            throw new RuntimeException('gzencode is not available');
        }
        return gzencode($input, $level, $encoding);
    }

    public function decompressGz(string $input): string|false
    {
        $this->ensurePermission('gzdecode');
        if (!function_exists('gzdecode')) {
            throw new RuntimeException('gzdecode is not available');
        }
        return gzdecode($input);
    }

    public function deflateCompress(string $input, int $level = -1): string|false
    {
        $this->ensurePermission('gzdeflate');
        if (!function_exists('gzdeflate')) {
            throw new RuntimeException('gzdeflate is not available');
        }
        return gzdeflate($input, $level);
    }

    public function deflateDecompress(string $input): string|false
    {
        $this->ensurePermission('gzinflate');
        if (!function_exists('gzinflate')) {
            throw new RuntimeException('gzinflate is not available');
        }
        return gzinflate($input);
    }

    // ----------------------
    // BZ2
    // ----------------------

    public function compressBz(string $input, int $blocksize = 4, int $workfactor = 0): string|false
    {
        $this->ensurePermission('bzcompress');
        if (!function_exists('bzcompress')) {
            throw new RuntimeException('bzcompress is not available');
        }
        return bzcompress($input, $blocksize, $workfactor);
    }

    public function decompressBz(string $input, int $small = 0): string|false
    {
        $this->ensurePermission('bzdecompress');
        if (!function_exists('bzdecompress')) {
            throw new RuntimeException('bzdecompress is not available');
        }
        return bzdecompress($input, $small);
    }

    // ----------------------
    // zlib_encode / zlib_decode
    // ----------------------

    public function zlibEncode(string $input, int $encoding = ZLIB_ENCODING_DEFLATE): string|false
    {
        $this->ensurePermission('zlib_encode');
        if (!function_exists('zlib_encode')) {
            throw new RuntimeException('zlib_encode is not available');
        }
        return zlib_encode($input, $encoding);
    }

    public function zlibDecode(string $input): string|false
    {
        $this->ensurePermission('zlib_decode');
        if (!function_exists('zlib_decode')) {
            throw new RuntimeException('zlib_decode is not available');
        }
        return zlib_decode($input);
    }

    // ----------------------
    // Deflate/Inflate stream helpers (if used)
    // ----------------------

    public function deflateInit(int $mode = ZLIB_ENCODING_DEFLATE, array $options = []): false|DeflateContext
    {
        $this->ensurePermission('deflate_init');
        if (!function_exists('deflate_init')) {
            throw new RuntimeException('deflate_init is not available');
        }
        // signature deflate_init(int $encoding, array $options = ?)
        return deflate_init($mode, $options);
    }

    public function deflateAdd($context, string $data, int $flush = ZLIB_SYNC_FLUSH): string|false
    {
        $this->ensurePermission('deflate_add');
        if (!function_exists('deflate_add')) {
            throw new RuntimeException('deflate_add is not available');
        }
        return deflate_add($context, $data, $flush);
    }

    public function inflateInit(int $encoding, array $options = []): false|InflateContext
    {
        $this->ensurePermission('inflate_init');
        if (!function_exists('inflate_init')) {
            throw new RuntimeException('inflate_init is not available');
        }
        return inflate_init($encoding, $options);
    }

    public function inflateAdd($context, string $data): string|false
    {
        $this->ensurePermission('inflate_add');
        if (!function_exists('inflate_add')) {
            throw new RuntimeException('inflate_add is not available');
        }
        return inflate_add($context, $data);
    }

    // ----------------------
    // ROT13 and simple transforms
    // ----------------------

    public function rot13(string $input): string
    {
        $this->ensurePermission('str_rot13');
        if (!function_exists('str_rot13')) {
            throw new RuntimeException('str_rot13 is not available');
        }
        return str_rot13($input);
    }

    public function reverseString(string $input): string
    {
        $this->ensurePermission('strrev');
        if (!function_exists('strrev')) {
            throw new RuntimeException('strrev is not available');
        }
        return strrev($input);
    }

    public function addSlashes(string $input): string
    {
        $this->ensurePermission('addslashes');
        if (!function_exists('addslashes')) {
            throw new RuntimeException('addslashes is not available');
        }
        return addslashes($input);
    }

    public function stripSlashes(string $input): string
    {
        $this->ensurePermission('stripslashes');
        if (!function_exists('stripslashes')) {
            throw new RuntimeException('stripslashes is not available');
        }
        return stripslashes($input);
    }

    public function quoteMeta(string $input): string
    {
        $this->ensurePermission('quotemeta');
        if (!function_exists('quotemeta')) {
            throw new RuntimeException('quotemeta is not available');
        }
        return quotemeta($input);
    }

    public function stripTags(string $input, ?string $allowed = null): string
    {
        $this->ensurePermission('strip_tags');
        if (!function_exists('strip_tags')) {
            throw new RuntimeException('strip_tags is not available');
        }
        return strip_tags($input, $allowed);
    }

    // ----------------------
    // Hex / binary conversions
    // ----------------------

    public function encodeHex(string $input): string
    {
        $this->ensurePermission('bin2hex');
        if (!function_exists('bin2hex')) {
            throw new RuntimeException('bin2hex is not available');
        }
        return bin2hex($input);
    }

    public function decodeHex(string $input): string|false
    {
        $this->ensurePermission('hex2bin');
        if (!function_exists('hex2bin')) {
            throw new RuntimeException('hex2bin is not available');
        }
        return hex2bin($input);
    }

    // ----------------------
    // chr / ord
    // ----------------------

    public function chr(int $ascii): string
    {
        $this->ensurePermission('chr');
        if (!function_exists('chr')) {
            throw new RuntimeException('chr is not available');
        }
        return chr($ascii);
    }

    public function ord(string $char): int
    {
        $this->ensurePermission('ord');
        if (!function_exists('ord')) {
            throw new RuntimeException('ord is not available');
        }
        return ord($char);
    }

    // ----------------------
    // pack / unpack
    // ----------------------

    /**
     * Pack values according to format.
     * Example: pack('H*', $data)
     *
     * @param string $format
     * @param mixed ...$values
     * @return string
     */
    public function pack(string $format, mixed ...$values): string
    {
        $this->ensurePermission('pack');
        if (!function_exists('pack')) {
            throw new RuntimeException('pack is not available');
        }
        return pack($format, ...$values);
    }

    /**
     * Unpack data according to format.
     *
     * @param string $format
     * @param string $data
     * @return array|false
     */
    public function unpack(string $format, string $data): array|false
    {
        $this->ensurePermission('unpack');
        if (!function_exists('unpack')) {
            throw new RuntimeException('unpack is not available');
        }
        return unpack($format, $data);
    }

    // ----------------------
    // URL encoding
    // ----------------------

    public function encodeUrl(string $input): string
    {
        $this->ensurePermission('urlencode');
        if (!function_exists('urlencode')) {
            throw new RuntimeException('urlencode is not available');
        }
        return urlencode($input);
    }

    public function decodeUrl(string $input): string
    {
        $this->ensurePermission('urldecode');
        if (!function_exists('urldecode')) {
            throw new RuntimeException('urldecode is not available');
        }
        return urldecode($input);
    }

    public function rawEncodeUrl(string $input): string
    {
        $this->ensurePermission('rawurlencode');
        if (!function_exists('rawurlencode')) {
            throw new RuntimeException('rawurlencode is not available');
        }
        return rawurlencode($input);
    }

    public function rawDecodeUrl(string $input): string
    {
        $this->ensurePermission('rawurldecode');
        if (!function_exists('rawurldecode')) {
            throw new RuntimeException('rawurldecode is not available');
        }
        return rawurldecode($input);
    }

    // ----------------------
    // convert_uuencode / convert_uudecode
    // ----------------------

    public function convertUuEncode(string $input): string
    {
        $this->ensurePermission('convert_uuencode');
        if (!function_exists('convert_uuencode')) {
            throw new RuntimeException('convert_uuencode is not available');
        }
        return convert_uuencode($input);
    }

    public function convertUuDecode(string $input): string|false
    {
        $this->ensurePermission('convert_uudecode');
        if (!function_exists('convert_uudecode')) {
            throw new RuntimeException('convert_uudecode is not available');
        }
        return convert_uudecode($input);
    }

    // ----------------------
    // serialize / unserialize
    // ----------------------

    public function encodeSerialize(mixed $input): string
    {
        $this->ensurePermission('serialize');
        if (!function_exists('serialize')) {
            throw new RuntimeException('serialize is not available');
        }
        return serialize($input);
    }

    public function decodeSerialize(string $input, array $options = []): mixed
    {
        $this->ensurePermission('unserialize');
        if (!function_exists('unserialize')) {
            throw new RuntimeException('unserialize is not available');
        }
        // use php's second param options if provided (PHP 7.0+)
        return unserialize($input, $options);
    }

    // ----------------------
    // Hashing (md5, sha1, hash, hmac)
    // ----------------------

    public function md5(string $data, bool $rawOutput = false): string
    {
        $this->ensurePermission('md5');
        if (!function_exists('md5')) {
            throw new RuntimeException('md5 is not available');
        }
        return md5($data, $rawOutput);
    }

    public function sha1(string $data, bool $rawOutput = false): string
    {
        $this->ensurePermission('sha1');
        if (!function_exists('sha1')) {
            throw new RuntimeException('sha1 is not available');
        }
        return sha1($data, $rawOutput);
    }

    public function hash(string $algo, string $data, bool $rawOutput = false): string
    {
        $this->ensurePermission('hash');
        if (!function_exists('hash')) {
            throw new RuntimeException('hash is not available');
        }
        return hash($algo, $data, $rawOutput);
    }

    public function hashHmac(string $algo, string $data, string $key, bool $rawOutput = false): string
    {
        $this->ensurePermission('hash_hmac');
        if (!function_exists('hash_hmac')) {
            throw new RuntimeException('hash_hmac is not available');
        }
        return hash_hmac($algo, $data, $key, $rawOutput);
    }

    // ----------------------
    // OpenSSL
    // ----------------------
    /**
     * Encrypt with OpenSSL and return payload with IV prepended (raw binary).
     *
     * Returns raw binary string: iv || ciphertext (OPENSSL_RAW_DATA).
     */
    public function opensslEncryptWithIv(string $data, string $method, string $key, int $options = OPENSSL_RAW_DATA, ?string $iv = null): string
    {
        $this->ensurePermission('openssl_encrypt');

        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException('openssl_encrypt is not available');
        }

        $ivLength = openssl_cipher_iv_length($method);
        if ($ivLength === false) {
            throw new RuntimeException("Could not determine IV length for cipher: $method");
        }

        if ($ivLength > 0) {
            if ($iv === null) {
                $iv = $this->generateIv($method); // reuse your generateIv() helper
            }
            if (!is_string($iv) || strlen($iv) !== $ivLength) {
                throw new InvalidArgumentException("Invalid IV length for cipher $method. Expected $ivLength bytes.");
            }
        } else {
            $iv = $iv ?? '';
        }

        $ciphertext = openssl_encrypt($data, $method, $key, $options, $iv);
        if ($ciphertext === false) {
            return false;
        }

        // return iv + ciphertext (raw)
        return $iv . $ciphertext;
    }

    /**
     * Decrypt a payload produced by opensslEncryptWithIv (iv prepended).
     */
    public function opensslDecryptWithIv(string $payload, string $method, string $key, int $options = OPENSSL_RAW_DATA): string|false
    {
        $this->ensurePermission('openssl_decrypt');

        if (!function_exists('openssl_decrypt')) {
            throw new RuntimeException('openssl_decrypt is not available');
        }

        $ivLength = openssl_cipher_iv_length($method);
        if ($ivLength === false) {
            throw new RuntimeException("Could not determine IV length for cipher: $method");
        }

        if ($ivLength > 0) {
            if (strlen($payload) <= $ivLength) {
                throw new InvalidArgumentException('Payload too short to contain IV + ciphertext');
            }
            $iv = substr($payload, 0, $ivLength);
            $ciphertext = substr($payload, $ivLength);
        } else {
            $iv = '';
            $ciphertext = $payload;
        }

        return openssl_decrypt($ciphertext, $method, $key, $options, $iv);
    }
    // ----------------------
    // mcrypt (legacy) - only if available
    // ----------------------
    /**
     * mcryptEncrypt: generate IV first (via secureRandom), validate, encrypt.
     *
     * @throws RuntimeException|InvalidArgumentException
     */
    public function mcryptEncrypt(string $cipher, string $data, string $key, string $mode, ?string $iv = null): string|false
    {
        $this->ensurePermission('mcrypt_encrypt');
        $this->warnMcryptDeprecated();

        if (!function_exists('mcrypt_encrypt')) {
            throw new RuntimeException('mcrypt_encrypt is not available on this PHP build');
        }

        $ivSize = $this->ivSizeForMcrypt($cipher, $mode); // <- your utility

        // If IV required and not supplied, generate securely
        if ($ivSize > 0 && $iv === null) {
            $iv = $this->secureRandom($ivSize); // <- your utility
        }

        // Validate IV when required
        if ($ivSize > 0) {
            if (!is_string($iv) || strlen($iv) !== $ivSize) {
                throw new InvalidArgumentException(
                    "Invalid IV for cipher '$cipher' mode '$mode'. Expected $ivSize bytes, got " .
                    (is_string($iv) ? strlen($iv) : gettype($iv))
                );
            }
        } else {
            $iv = $iv ?? '';
        }

        // mcrypt_encrypt(string $cipher, string $key, string $data, string $mode [, string $iv ])
        return mcrypt_encrypt($cipher, $key, $data, $mode, $iv);
    }

    /**
     * mcryptDecrypt: require the same IV the encrypt used (no auto-generation).
     *
     * @throws RuntimeException|InvalidArgumentException
     */
    public function mcryptDecrypt(string $cipher, string $data, string $key, string $mode, ?string $iv = null): string|false
    {
        $this->ensurePermission('mcrypt_decrypt');
        $this->warnMcryptDeprecated();

        if (!function_exists('mcrypt_decrypt')) {
            throw new RuntimeException('mcrypt_decrypt is not available on this PHP build');
        }

        $ivSize = $this->ivSizeForMcrypt($cipher, $mode);
        if ($ivSize > 0 && $iv === null) {
            $iv = $this->generateLegacyIvForMcrypt($ivSize);
        }

        return mcrypt_decrypt($cipher, $key, $data, $mode, $iv);
    }

    /**
     * mcryptEncryptWithIv: generates IV (secureRandom) and returns ['iv'=>..., 'ciphertext'=>...].
     */
    public function mcryptEncryptWithIv(string $cipher, string $data, string $key, string $mode, ?string $iv = null): array
    {
        $this->ensurePermission('mcrypt_encrypt');
        $this->warnMcryptDeprecated();

        $ivSize = $this->ivSizeForMcrypt($cipher, $mode);
        if ($ivSize > 0 && $iv === null) {
            $iv = $this->generateLegacyIvForMcrypt($ivSize);
        }

        $ciphertext = $this->mcryptEncrypt($cipher, $data, $key, $mode, $iv);

        return ['iv' => $iv ?? '', 'ciphertext' => $ciphertext];
    }

    /**
     * mcryptDecryptWithIv: accepts payload with IV prepended or separate IV.
     * If $ivSize not provided, it is derived via ivSizeForMcrypt().
     */
    public function mcryptDecryptWithIv(string $payload, string $cipher, string $key, string $mode, ?int $ivSize = null): string|false
    {
        $this->ensurePermission('mcrypt_decrypt');
        $this->warnMcryptDeprecated();

        $ivSize = $ivSize ?? $this->ivSizeForMcrypt($cipher, $mode); // <- your utility

        if ($ivSize > 0) {
            if (strlen($payload) <= $ivSize) {
                throw new InvalidArgumentException('Payload too short to contain IV + ciphertext');
            }
            $iv = substr($payload, 0, $ivSize);
            $ciphertext = substr($payload, $ivSize);
        } else {
            $iv = '';
            $ciphertext = $payload;
        }

        return $this->mcryptDecrypt($cipher, $ciphertext, $key, $mode, $iv);
    }

    // ----------------------
    // Convenience: allow callers to list available wrappers
    // ----------------------

    public function available(): array
    {
        return [
            // grouped list of the exposed wrappers and their underlying functions
            'base64_encode' => 'encodeBase64',
            'base64_decode' => 'decodeBase64',
            'json_encode' => 'encodeJson',
            'json_decode' => 'decodeJson',
            'gzencode' => 'compressGz',
            'gzdecode' => 'decompressGz',
            'gzdeflate' => 'deflateCompress',
            'gzinflate' => 'deflateDecompress',
            'bzcompress' => 'compressBz',
            'bzdecompress' => 'decompressBz',
            'zlib_encode' => 'zlibEncode',
            'zlib_decode' => 'zlibDecode',
            'deflate_init' => 'deflateInit',
            'deflate_add' => 'deflateAdd',
            'inflate_init' => 'inflateInit',
            'inflate_add' => 'inflateAdd',
            'str_rot13' => 'rot13',
            'strrev' => 'reverseString',
            'addslashes' => 'addSlashes',
            'stripslashes' => 'stripSlashes',
            'quotemeta' => 'quoteMeta',
            'strip_tags' => 'stripTags',
            'bin2hex' => 'encodeHex',
            'hex2bin' => 'decodeHex',
            'chr' => 'chr',
            'ord' => 'ord',
            'pack' => 'pack',
            'unpack' => 'unpack',
            'urlencode' => 'encodeUrl',
            'urldecode' => 'decodeUrl',
            'rawurlencode' => 'rawEncodeUrl',
            'rawurldecode' => 'rawDecodeUrl',
            'convert_uuencode' => 'convertUuEncode',
            'convert_uudecode' => 'convertUuDecode',
            'serialize' => 'encodeSerialize',
            'unserialize' => 'decodeSerialize',
            'md5' => 'md5',
            'sha1' => 'sha1',
            'hash' => 'hash',
            'hash_hmac' => 'hashHmac',
            'openssl_encrypt' => 'opensslEncrypt',
            'openssl_decrypt' => 'opensslDecrypt',
            'mcrypt_encrypt' => 'mcryptEncrypt',
            'mcrypt_decrypt' => 'mcryptDecrypt',
        ];
    }
}