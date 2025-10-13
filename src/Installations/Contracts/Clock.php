<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Installations\Contracts;

use DateTimeImmutable;

/**
 * Wall-clock abstraction to make time-based logic deterministic and testable.
 *
 * Use this for token expiry, run timestamps, and log file metadata.
 */
interface Clock
{
    /**
     * Current UTC instant.
     *
     * @return DateTimeImmutable Immutable UTC timestamp.
     */
    public function now(): DateTimeImmutable;
}