<?php

declare(strict_types=1);

namespace glpzzz\cron\tests\Unit;

use glpzzz\cron\models\CronEntry;
use PHPUnit\Framework\TestCase;

final class CronEntryTest extends TestCase
{
    public function testGetRouteBuildsControllerActionPathUnderThePrefix(): void
    {
        $entry = new CronEntry('users', 'notify-for-insurance', [], '0 11 * * *', 'test');

        self::assertSame('cron/users/notify-for-insurance', $entry->getRoute('cron'));
        self::assertSame('scheduled/users/notify-for-insurance', $entry->getRoute('scheduled'));
    }

    public function testGetCommandCdsIntoAppPathAndQuotesEachArgument(): void
    {
        $entry = new CronEntry('maintenance', 'remove-logs', ['6'], '59 20 * * *', 'test');

        $expected = "cd '/some/path' && " . escapeshellarg(PHP_BINARY)
            . " yii 'cron/maintenance/remove-logs' '6' > /dev/null 2>&1";

        self::assertSame($expected, $entry->getCommand('/some/path', 'cron'));
    }

    public function testGetCommandOmitsParamsWhenNone(): void
    {
        $entry = new CronEntry('job-seeker', 'increase-otr-experience', [], '0 0 1 * *', 'test');

        $expected = "cd '/some/path' && " . escapeshellarg(PHP_BINARY)
            . " yii 'cron/job-seeker/increase-otr-experience' > /dev/null 2>&1";

        self::assertSame($expected, $entry->getCommand('/some/path', 'cron'));
    }

    public function testIsScheduledReflectsANullSchedule(): void
    {
        self::assertTrue((new CronEntry('c', 'a', [], '0 0 * * *', 't'))->isScheduled());
        self::assertFalse((new CronEntry('c', 'a', [], null, 't'))->isScheduled());
    }
}
