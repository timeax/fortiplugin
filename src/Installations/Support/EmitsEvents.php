<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Support;

/**
 * Convenience wrappers around EmitterMux that use EmitPayload to build
 * consistent payloads and guarantee verbatim meta passthrough.
 *
 * Drop this trait into classes that need to emit installer/validation events.
 */
trait EmitsEvents
{
    use EmitPayload;

    /** @var EmitterMux|null */
    protected ?EmitterMux $emitterMux = null;

    public function setEmitterMux(EmitterMux $mux): void
    {
        $this->emitterMux = $mux;
    }

    /**
     * Emit a **success/info** event on the installer channel.
     *
     * @param non-empty-string $title
     * @param string|null      $description
     * @param array            $meta
     */
    protected function emitOk(string $title, ?string $description = null, array $meta = []): void
    {
        if (!$this->emitterMux) return;
        $payload = $this->finalize($this->makePayload($title, $description, $meta));
        $this->emitterMux->emitInstaller($payload);
    }

    /**
     * Emit an **error** event (with standardized error block) on the installer channel.
     *
     * @param non-empty-string $title
     * @param non-empty-string $code   One of ErrorCodes::*
     * @param non-empty-string $message
     * @param array            $extra  Any structured details (kept verbatim)
     * @param string|null      $filePath
     * @param int|null         $size
     * @param array            $meta
     */
    protected function emitFail(
        string $title,
        string $code,
        string $message,
        array $extra = [],
        ?string $filePath = null,
        ?int $size = null,
        array $meta = []
    ): void {
        if (!$this->emitterMux) return;

        $payload = $this->merge(
            $this->makePayload($title, $message, $meta),
            ['error' => $this->error($code, $message, $extra)]
        );

        if ($filePath !== null || $size !== null) {
            $payload['stats'] = $this->stats($filePath, $size);
        }

        $this->emitterMux->emitInstaller($this->finalize($payload));
    }

    /**
     * Emit a **validation-side** event (rarely needed—validators emit directly).
     * Use only when the installer must mirror something into the validation stream.
     *
     * @param non-empty-string $title
     * @param string|null      $description
     * @param array            $meta
     * @param string|null      $filePath
     * @param int|null         $size
     */
    protected function emitValidationSide(
        string $title,
        ?string $description = null,
        array $meta = [],
        ?string $filePath = null,
        ?int $size = null
    ): void {
        if (!$this->emitterMux) return;
        $payload = $this->makePayload($title, $description, $meta);
        if ($filePath !== null || $size !== null) {
            $payload['stats'] = $this->stats($filePath, $size);
        }
        $this->emitterMux->emitValidation($this->finalize($payload));
    }
}