<?php

declare(strict_types=1);

namespace glpzzz\cron\tests\Unit;

use glpzzz\cron\components\Crontab;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CrontabTest extends TestCase
{
    public function testReadLinesThrowsRuntimeExceptionWhenExecIsUnavailable(): void
    {
        $crontab = new class extends Crontab {
            protected function assertExecAvailable(): void
            {
                throw new RuntimeException('exec() is disabled');
            }
        };

        $this->expectException(RuntimeException::class);
        $crontab->readLines();
    }

    public function testWriteLinesThrowsRuntimeExceptionWhenExecIsUnavailable(): void
    {
        $crontab = new class extends Crontab {
            protected function assertExecAvailable(): void
            {
                throw new RuntimeException('exec() is disabled');
            }
        };

        $this->expectException(RuntimeException::class);
        $crontab->writeLines(['* * * * * true']);
    }

    public function testParseLineSplitsScheduleAndCommand(): void
    {
        $parsed = Crontab::parseLine("0 0 * * * cd '/app' && php yii cron/maintenance/remove-logs '6' > /dev/null 2>&1");

        self::assertNotNull($parsed);
        self::assertSame('0 0 * * *', $parsed['schedule']);
        self::assertSame("cd '/app' && php yii cron/maintenance/remove-logs '6' > /dev/null 2>&1", $parsed['command']);
    }

    public function testParseLineReturnsNullForBlankLines(): void
    {
        self::assertNull(Crontab::parseLine(''));
        self::assertNull(Crontab::parseLine('   '));
    }

    public function testParseLineReturnsNullForComments(): void
    {
        self::assertNull(Crontab::parseLine('# a comment'));
        self::assertNull(Crontab::parseLine('   # indented comment'));
    }

    public function testParseLineReturnsNullForLinesWithoutFiveScheduleFields(): void
    {
        self::assertNull(Crontab::parseLine('MAILTO=someone@example.com'));
    }
}
