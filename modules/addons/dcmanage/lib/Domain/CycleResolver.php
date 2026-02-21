<?php

declare(strict_types=1);

namespace DCManage\Domain;

use DateTimeImmutable;

final class CycleResolver
{
    /**
     * Returns [cycleStart(DateTimeImmutable), cycleEnd(DateTimeImmutable)].
     * Rule: cycle_end = nextduedate 00:00:00 - 1 sec
     */
    public static function resolve(string $nextDueDate, string $billingCycle): array
    {
        $due = new DateTimeImmutable($nextDueDate . ' 00:00:00');
        $cycleEnd = $due->modify('-1 second');
        $cycleStart = $due->modify('-' . self::cycleLengthInterval($billingCycle));

        return [$cycleStart, $cycleEnd];
    }

    private static function cycleLengthInterval(string $billingCycle): string
    {
        $normalized = strtolower(trim($billingCycle));
        if ($normalized === 'monthly') {
            return '1 month';
        }
        if ($normalized === 'quarterly') {
            return '3 months';
        }
        if ($normalized === 'semi-annually' || $normalized === 'semiannually') {
            return '6 months';
        }
        if ($normalized === 'annually') {
            return '1 year';
        }
        if ($normalized === 'biennially') {
            return '2 years';
        }
        if ($normalized === 'triennially') {
            return '3 years';
        }
        return '1 month';
    }
}
