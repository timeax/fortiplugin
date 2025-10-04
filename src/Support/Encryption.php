<?php

namespace Timeax\FortiPlugin\Support;


use JsonException;
use SodiumException;

class Encryption
{
    /**
     * @throws
     */
    public static function encrypt(string $plaintext, int $numShards = 7): string
    {
        $encryptionKey = sodium_bin2base64(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES), SODIUM_BASE64_VARIANT_URLSAFE);
        $shards = self::splitKeyIntoShards($encryptionKey, $numShards);

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, sodium_base642bin($encryptionKey, SODIUM_BASE64_VARIANT_URLSAFE));
        $payload = [
            'nonce' => base64_encode($nonce),
            'ciphertext' => base64_encode($ciphertext),
        ];
        $payloadEncoded = base64_encode(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        // Embed key shards as labeled markers in pseudo-random positions in payload
        $block = $payloadEncoded;
        $positions = self::calculateShardPositions($block, $numShards);
        foreach ($positions as $i => $pos) {
            $block = substr($block, 0, $pos) . "[KEY$i=$shards[$i]]" . substr($block, $pos);
        }
        // Encode the map as a hidden suffix
        $block .= "\n--KEYMAP:" . base64_encode(json_encode(['count' => $numShards], JSON_THROW_ON_ERROR)) . "--";
        return $block;
    }

    /**
     * @throws SodiumException|JsonException
     */
    public static function decrypt(string $encrypted): ?string
    {
        // Find keymap (required for number of shards)
        if (!preg_match('/--KEYMAP:([A-Za-z0-9+\/=_-]+)--/', $encrypted, $m)) {
            return null;
        }
        $keymap = json_decode(base64_decode($m[1]), true, 512, JSON_THROW_ON_ERROR);
        $numShards = $keymap['count'] ?? 7;

        // Remove keymap for base64 decode
        $main = preg_replace('/--KEYMAP:([A-Za-z0-9+\/=_-]+)--/', '', $encrypted);

        // Extract shards in order
        $shards = [];
        for ($i = 0; $i < $numShards; $i++) {
            if (preg_match("/\[KEY$i=([A-Za-z0-9+\/=_-]+)]/", $main, $matches)) {
                $shards[$i] = $matches[1];
                // Remove marker from main so it doesn't mess up offsets for next
                $main = str_replace($matches[0], '', $main);
            }
        }
        ksort($shards);
        $key = implode('', $shards);

        // Now base64-decode, then decrypt
        $payload = json_decode(base64_decode($main), true, 512, JSON_THROW_ON_ERROR);
        if (!$payload || !isset($payload['nonce'], $payload['ciphertext'])) {
            return null;
        }

        $nonce = base64_decode($payload['nonce']);
        $ciphertext = base64_decode($payload['ciphertext']);
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, sodium_base642bin($key, SODIUM_BASE64_VARIANT_URLSAFE));
        return $plaintext === false ? null : $plaintext;
    }

    // Helper: split key into N shards
    private static function splitKeyIntoShards(string $key, int $numShards): array
    {
        $len = strlen($key);
        $shardSize = (int)ceil($len / $numShards);
        $shards = [];
        for ($i = 0; $i < $numShards; $i++) {
            $shards[] = substr($key, $i * $shardSize, $shardSize);
        }
        return $shards;
    }

    // Helper: find pseudo-random insert positions for markers
    private static function calculateShardPositions(string $block, int $numShards): array
    {
        $positions = [];
        $len = strlen($block);
        for ($i = 0; $i < $numShards; $i++) {
            // Example: offset is (block len / (numShards+1)) * (i+1), +i to scramble a bit
            $positions[] = min(
                (int)(($len / ($numShards + 1)) * ($i + 1)) + $i,
                $len - 1
            );
        }
        arsort($positions); // Insert from the end for stable offsets
        return array_values($positions);
    }

    /**
     * @throws JsonException
     */
    public static function encryptFile($inputFile, $outputFile, $encryptionKey): void
    {
        $data = file_get_contents($inputFile);
        $encrypted = self::encrypt($data, $encryptionKey); // Pass the key explicitly
        file_put_contents($outputFile, $encrypted);
    }

    /**
     * @throws SodiumException
     * @throws JsonException
     */
    public static function decryptFile($inputFile, $outputFile): void
    {
        $data = file_get_contents($inputFile);
        $encrypted = self::decrypt($data); // Pass the key explicitly
        file_put_contents($outputFile, $encrypted);
    }
}
