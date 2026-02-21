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

        return match ($normalized) {
            'monthly' => '1 month',
            'quarterly' => '3 months',
            'semi-annually', 'semiannually' => '6 months',
            'annually' => '1 year',
            'biennially' => '2 years',
            'triennially' => '3 years',
            default => '1 month',
        };
    }
}
