<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Permissions\Policy;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Evaluates host-defined approval windows on assignments.
 *
 * Window shape (as returned by your repository assignment rows):
 *   [
 *     'limited' => bool,
 *     'type'    => ?string,  // 'until' | 'ttl'
 *     'value'   => ?string,  // ISO-8601 instant for 'until', ISO-8601 duration (e.g. PT1H) or seconds for 'ttl'
 *   ]
 *
 * Usage:
 *   $evaluator->isActive($window, $assignmentCreatedAt); // true if the window permits use "now"
 */
final class TimeWindowEvaluator
{
    /**
     * Evaluate whether the window is currently active.
     *
     * @param array|null               $window    see class doc
     * @param DateTimeInterface|null   $startedAt when the assignment took effect (needed for TTL)
     * @param DateTimeInterface|null   $now       override "now" (default: current time)
     */
    public function isActive(?array $window, ?DateTimeInterface $startedAt = null, ?DateTimeInterface $now = null): bool
    {
        if (!$window || !($window['limited'] ?? false)) {
            return true; // no limit -> always active
        }

        $type  = isset($window['type'])  ? (string)$window['type']  : '';
        $value = isset($window['value']) ? (string)$window['value'] : '';
        $now   = $now ?? new DateTimeImmutable('now');

        if ($type === 'until') {
            // value must be an ISO 8601 timestamp
            try {
                $until = new DateTimeImmutable($value);
            } catch (\Throwable) {
                return false; // malformed -> treat as inactive
            }
            return $now <= $until;
        }

        if ($type === 'ttl') {
            if ($startedAt === null) {
                // cannot evaluate TTL without a start; treat as inactive to be safe
                return false;
            }

            $seconds = $this->parseDurationSeconds($value);
            if ($seconds === null) {
                return false;
            }

            $expires = $startedAt->modify('+' . $seconds . ' seconds');
            return $now <= $expires;
        }

        // Unknown window type -> safest to deny
        return false;
    }

    /**
     * Parse ISO-8601 duration (e.g., "PT1H30M") or raw integer seconds ("3600").
     * Returns null if value is not parseable.
     */
    private function parseDurationSeconds(string $value): ?int
    {
        // raw seconds
        if (ctype_digit($value)) {
            return (int)$value;
        }

        // ISO-8601 duration
        try {
            $interval = new DateInterval($value); // throws on bad format
        } catch (\Throwable) {
            return null;
        }

        // Convert DateInterval to seconds (approximate for months/years using 30/365 day assumptions isn’t ideal;
        // here we only expect time-based units for TTL: H/M/S/D; handle Y/M conservatively as days)
        $days = ($interval->y * 365) + ($interval->m * 30) + $interval->d;
        return ($days * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
    }
}