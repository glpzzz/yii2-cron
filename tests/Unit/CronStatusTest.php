<?php

declare(strict_types=1);

namespace glpzzz\cron\tests\Unit;

use glpzzz\cron\components\CronStatus;
use glpzzz\cron\models\CronEntry;
use glpzzz\cron\testing\FakeCronScanner;
use glpzzz\cron\testing\FakeCrontab;
use PHPUnit\Framework\TestCase;

final class CronStatusTest extends TestCase
{
    private const APP_PATH = '/srv/app';
    private const PREFIX = 'cron';
    private const UNRELATED = '0 2 * * * /some/other/app/script.sh';

    private FakeCronScanner $scanner;
    private FakeCrontab $crontab;

    protected function setUp(): void
    {
        $this->scanner = new FakeCronScanner();
        $this->crontab = new FakeCrontab();
    }

    public function testInSyncWhenEveryScheduledEntryHasItsExactLineInstalled(): void
    {
        $a = new CronEntry('audit', 'check-smtp', [], '*/5 * * * *', 'smtp probe');
        $this->scanner->setEntries([$a]);
        $this->crontab->writeLines([
            self::UNRELATED,
            $a->schedule . ' ' . $a->getCommand(self::APP_PATH, self::PREFIX),
        ]);

        $report = $this->makeStatus()->report();

        self::assertFalse($report->hasDiscrepancies());
        self::assertSame([$a], $report->inSync);
        self::assertSame([], $report->missing);
    }

    public function testMissingWhenAScheduledEntryHasNoInstalledLine(): void
    {
        $a = new CronEntry('audit', 'check-smtp', [], '*/5 * * * *', 'smtp probe');
        $this->scanner->setEntries([$a]);
        $this->crontab->writeLines([self::UNRELATED]);

        $report = $this->makeStatus()->report();

        self::assertTrue($report->hasDiscrepancies());
        self::assertSame([$a], $report->missing);
    }

    public function testDriftedWhenTheCommandIsInstalledWithADifferentSchedule(): void
    {
        $a = new CronEntry('audit', 'check-smtp', [], '*/5 * * * *', 'smtp probe');
        $this->scanner->setEntries([$a]);
        $this->crontab->writeLines(['0 9 * * * ' . $a->getCommand(self::APP_PATH, self::PREFIX)]);

        $report = $this->makeStatus()->report();

        self::assertCount(1, $report->drifted);
        self::assertSame($a, $report->drifted[0]['entry']);
        self::assertSame('0 9 * * *', $report->drifted[0]['installedSchedule']);
        self::assertSame([], $report->inSync);
    }

    public function testOrphanedWhenAnOwnedLineMatchesNoDeclaredEntry(): void
    {
        $this->scanner->setEntries([]);
        $orphan = "0 0 * * * cd '" . self::APP_PATH . "' && php yii cron/gone/away > /dev/null 2>&1";
        $this->crontab->writeLines([$orphan, self::UNRELATED]);

        $report = $this->makeStatus()->report();

        self::assertCount(1, $report->orphaned);
        self::assertSame($orphan, $report->orphaned[0]['schedule'] . ' ' . $report->orphaned[0]['command']);
    }

    public function testUnrelatedAndUnscheduledLinesAreLeftOutOfEveryDiscrepancyBucket(): void
    {
        $manual = new CronEntry('users', 'reset-passwords', [], null, 'documented only');
        $this->scanner->setEntries([$manual]);
        $this->crontab->writeLines([self::UNRELATED]);

        $report = $this->makeStatus()->report();

        self::assertSame([$manual], $report->unscheduled);
        self::assertSame([], $report->scheduled);
        self::assertFalse($report->hasDiscrepancies());
    }

    private function makeStatus(): CronStatus
    {
        return new CronStatus($this->scanner, $this->crontab, self::APP_PATH, self::PREFIX);
    }
}
