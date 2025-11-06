<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

/**
 * Tiny helpers to build canonical emitter payloads consistently.
 *
 * Keys follow your unified emitter contract:
 *   - title: string
 *   - description?: string|null
 *   - error?: array{code:string,message:string,extra?:array}
 *   - stats?: array{filePath?:string|null,size?:int|null}
 *   - meta?: array
 *
 * Usage:
 *   $payload = $this->finalize(
 *       $this->merge(
 *           $this->makePayload(Events::PSR4_CHECK_FAIL, 'Mismatch', ['zip_id'=>$zipId]),
 *           ['error' => $this->error(ErrorCodes::COMPOSER_PSR4_MISSING_OR_MISMATCH, 'Expected X → Y', ['expected'=>$exp])],
 *           ['stats' => $this->stats($composerJsonPath, $size)]
 *       )
 *   );
 */
trait EmitPayload
{
    /**
     * Base payload with title/description/meta.
     *
     * @param non-empty-string $title
     * @param string|null      $description
     * @param array            $meta
     * @return array{title:string,description?:string,meta?:array}
     */
    protected function makePayload(string $title, ?string $description = null, array $meta = []): array
    {
        $out = ['title' => $title, 'description' => $description];
        if ($meta !== []) {
            $out['meta'] = $meta; // NEVER mutate caller meta
        }
        return $out;
    }

    /**
     * Standard error block.
     *
     * @param non-empty-string $code
     * @param non-empty-string $message
     * @param array            $extra
     * @return array{code:string,message:string,extra?:array}
     */
    protected function error(string $code, string $message, array $extra = []): array
    {
        $err = ['code' => $code, 'message' => $message];
        if ($extra !== []) {
            $err['extra'] = $extra;
        }
        return $err;
    }

    /**
     * Stats block (filePath/size are optional).
     *
     * @return array{filePath?:string|null,size?:int|null}
     */
    protected function stats(?string $filePath = null, ?int $size = null): array
    {
        $s = [];
        if ($filePath !== null) $s['filePath'] = $filePath;
        if ($size !== null)     $s['size']     = $size;
        return $s;
    }

    /**
     * Merge multiple partial payload arrays (left→right), shallowly.
     *
     * @param array ...$parts
     * @return array
     */
    protected function merge(array ...$parts): array
    {
        $out = [];
        foreach ($parts as $p) {
            foreach ($p as $k => $v) {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    /**
     * Ensure required keys exist (title/description/stats/meta) for consistency.
     *
     * @param array $payload
     * @return array
     */
    protected function finalize(array $payload): array
    {
        $payload['title']       = (string)($payload['title'] ?? '');
        $payload['description'] = $payload['description'] ?? null;

        if (!isset($payload['stats']) || !is_array($payload['stats'])) {
            $payload['stats'] = ['filePath' => null, 'size' => null];
        } else {
            $payload['stats']['filePath'] = $payload['stats']['filePath'] ?? null;
            $payload['stats']['size']     = $payload['stats']['size']     ?? null;
        }

        if (!isset($payload['meta']) || !is_array($payload['meta'])) {
            $payload['meta'] = [];
        }

        return $payload;
    }
}