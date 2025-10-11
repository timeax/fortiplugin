<?php

namespace Tests\Support;

use PHPUnit\Framework\TestCase;
use Timeax\FortiPlugin\Support\Encryption;

class EncryptionTest extends TestCase
{
    private function requireSodiumOrSkip(): void
    {
        if (!function_exists('sodium_crypto_secretbox')) {
            $this->markTestSkipped('ext-sodium is not available in this environment.');
        }
    }

    public function test_encrypt_decrypt_round_trip_various_messages_and_shards(): void
    {
        $this->requireSodiumOrSkip();

        $messages = [
            '',
            'hello world',
            'Привет, мир! こんにちは世界 مرحبا بالعالم',
            str_repeat('A', 10_000),
            random_bytes(256),
        ];
        $shardCounts = [1, 2, 3, 7, 16];

        foreach ($messages as $msg) {
            foreach ($shardCounts as $n) {
                $encrypted = Encryption::encrypt($msg, $n);

                // Format sanity checks
                $this->assertStringContainsString('--KEYMAP:', $encrypted, 'Encrypted block should contain KEYMAP suffix');
                $this->assertMatchesRegularExpression('/--KEYMAP:[A-Za-z0-9+\/=_-]+--/', $encrypted);

                // Ensure markers for all shards are present
                for ($i = 0; $i < $n; $i++) {
                    $this->assertMatchesRegularExpression('/\[KEY' . $i . '=[A-Za-z0-9+\/=_-]+\]/', $encrypted);
                }

                $decrypted = Encryption::decrypt($encrypted);
                $this->assertNotNull($decrypted, 'Decryption should not return null for untampered data');
                $this->assertSame($msg, $decrypted, 'Decrypted plaintext should equal original');
            }
        }
    }

    public function test_decrypt_returns_null_when_keymap_missing(): void
    {
        $this->requireSodiumOrSkip();

        $encrypted = Encryption::encrypt('data', 5);
        // Remove KEYMAP suffix entirely
        $tampered = preg_replace('/--KEYMAP:[A-Za-z0-9+\/=_-]+--/', '', $encrypted);
        $this->assertNotSame($encrypted, $tampered);

        $this->assertNull(Encryption::decrypt($tampered));
    }

    public function test_decrypt_returns_null_when_shard_removed(): void
    {
        $this->requireSodiumOrSkip();

        $encrypted = Encryption::encrypt('secret', 4);
        // Remove one shard marker, e.g., KEY2
        $tampered = preg_replace('/\[KEY2=[A-Za-z0-9+\/=_-]+\]/', '', $encrypted, 1);
        $this->assertNotSame($encrypted, $tampered);

        $this->assertNull(Encryption::decrypt($tampered));
    }

    public function test_decrypt_returns_null_when_keymap_count_too_large(): void
    {
        $this->requireSodiumOrSkip();

        $encrypted = Encryption::encrypt('payload', 3);
        // Increase the KEYMAP count value to an unrealistically high number to make shard extraction incomplete
        $tampered = preg_replace_callback(
            '/--KEYMAP:([A-Za-z0-9+\/=_-]+)--/',
            function ($m) {
                $decoded = json_decode(base64_decode($m[1]), true);
                $decoded['count'] = ($decoded['count'] ?? 3) + 5; // ask for more shards than present
                $new = base64_encode(json_encode($decoded));
                return "--KEYMAP:" . $new . "--";
            },
            $encrypted
        );
        $this->assertNotSame($encrypted, $tampered);

        $this->assertNull(Encryption::decrypt($tampered));
    }

    public function test_decrypt_invalid_input_returns_null(): void
    {
        $this->requireSodiumOrSkip();

        $this->assertNull(Encryption::decrypt('not an encrypted block'));
        // Looks like a payload but not valid base64 JSON
        $garbage = base64_encode('garbage') . "[KEY0=abc]--KEYMAP:" . base64_encode(json_encode(['count' => 1])) . "--";
        $this->assertNull(Encryption::decrypt($garbage));
    }

    public function test_encryptfile_and_decryptfile_round_trip_with_temp_files(): void
    {
        $this->requireSodiumOrSkip();

        $tmpDir = sys_get_temp_dir();
        $in = $tmpDir . DIRECTORY_SEPARATOR . 'enc_in_' . uniqid('', true) . '.bin';
        $enc = $tmpDir . DIRECTORY_SEPARATOR . 'enc_out_' . uniqid('', true) . '.bin';
        $out = $tmpDir . DIRECTORY_SEPARATOR . 'dec_out_' . uniqid('', true) . '.bin';

        try {
            $original = random_bytes(1024);
            file_put_contents($in, $original);

            // Note: encryptFile's third param is named $encryptionKey but currently acts as shard count.
            // We'll pass a shard count value to reflect current implementation.
            Encryption::encryptFile($in, $enc, 6);
            Encryption::decryptFile($enc, $out);

            $this->assertFileExists($out);
            $this->assertSame($original, file_get_contents($out));
        } finally {
            // Clean up temp files
            foreach ([$in, $enc, $out] as $f) {
                if (is_file($f)) {
                    @unlink($f);
                }
            }
        }
    }
}
