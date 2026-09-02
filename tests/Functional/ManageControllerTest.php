<?php

declare(strict_types=1);

namespace glpzzz\cron\tests\Functional;

use glpzzz\cron\controllers\ManageController;
use glpzzz\cron\models\CronEntry;
use glpzzz\cron\testing\FakeCronScanner;
use glpzzz\cron\testing\FakeCrontab;
use glpzzz\cron\tests\TestCase;
use Yii;
use yii\console\ExitCode;

final class ManageControllerTest extends TestCase
{
    private const SCHEDULE_A = '0 0 * * *';
    private const SCHEDULE_B = '0 5 * * *';
    private const UNRELATED_LINE = '0 2 * * * /some/unrelated/script.sh';

    protected function setUp(): void
    {
        $this->mockConsoleApplication($this->fakeComponents());
        $this->scanner()->setEntries($this->fixtureEntries());
        $this->crontab()->reset();
    }

    public function testInstallAddsALineForASingleDiscoveredEntry(): void
    {
        self::assertSame(ExitCode::OK, $this->controller()->runAction('install', ['test-a/run']));
        self::assertCount(1, $this->crontab()->readLines());
    }

    public function testInstallIsANoOpWhenAnIdenticalLineAlreadyExists(): void
    {
        $this->controller()->runAction('install', ['test-a/run']);

        self::assertSame(ExitCode::OK, $this->controller()->runAction('install', ['test-a/run']));
        self::assertCount(1, $this->crontab()->readLines());
    }

    public function testInstallMatchesOnParamsSoDifferentParamsAreDifferentEntries(): void
    {
        self::assertSame(ExitCode::OK, $this->controller()->runAction('install', ['test-b/run', 'x']));
        self::assertCount(1, $this->crontab()->readLines());
    }

    public function testInstallReturnsDataerrForAnUnknownRoute(): void
    {
        self::assertSame(ExitCode::DATAERR, $this->controller()->runAction('install', ['no-such/route']));
        self::assertCount(0, $this->crontab()->readLines());
    }

    public function testInstallAllAddsEveryDiscoveredEntryAndLeavesUnrelatedLinesAlone(): void
    {
        $this->crontab()->writeLines([self::UNRELATED_LINE]);

        self::assertSame(ExitCode::OK, $this->controller()->runAction('install-all'));

        $lines = $this->crontab()->readLines();
        self::assertCount(3, $lines);
        self::assertContains(self::UNRELATED_LINE, $lines);
    }

    public function testInstallAllIsIdempotentWithoutForce(): void
    {
        $this->controller()->runAction('install-all');

        self::assertSame(ExitCode::OK, $this->controller()->runAction('install-all'));
        self::assertCount(2, $this->crontab()->readLines());
    }

    public function testInstallAllForceReplacesStaleLinesWithoutTouchingUnrelatedOnes(): void
    {
        $entryA = $this->fixtureEntries()[0];
        $staleLine = '0 9 * * * ' . $entryA->getCommand($this->appPath(), 'cron');
        $this->crontab()->writeLines([$staleLine, self::UNRELATED_LINE]);

        $controller = $this->controller();
        $controller->force = true;
        self::assertSame(ExitCode::OK, $controller->runAction('install-all'));

        $lines = $this->crontab()->readLines();
        self::assertCount(3, $lines);
        self::assertContains(self::UNRELATED_LINE, $lines);
        self::assertContains(self::SCHEDULE_A . ' ' . $entryA->getCommand($this->appPath(), 'cron'), $lines);
        self::assertNotContains($staleLine, $lines);
    }

    public function testRemoveDeletesOnlyTheMatchingLine(): void
    {
        $this->crontab()->writeLines([self::UNRELATED_LINE]);
        $this->controller()->runAction('install-all');

        self::assertSame(ExitCode::OK, $this->controller()->runAction('remove', ['test-a/run']));

        $lines = $this->crontab()->readLines();
        self::assertCount(2, $lines);
        self::assertContains(self::UNRELATED_LINE, $lines);
    }

    public function testRemoveAllClearsOnlyOurLines(): void
    {
        $this->crontab()->writeLines([self::UNRELATED_LINE]);
        $this->controller()->runAction('install-all');

        self::assertSame(ExitCode::OK, $this->controller()->runAction('remove-all'));
        self::assertSame([self::UNRELATED_LINE], $this->crontab()->readLines());
    }

    public function testListAllRendersEveryDiscoveredEntry(): void
    {
        ob_start();
        $exit = $this->controller()->runAction('list', ['all' => true]);
        $out = ob_get_clean();

        self::assertSame(ExitCode::OK, $exit);
        self::assertStringContainsString('cron/test-a/run', $out);
        self::assertStringContainsString('cron/test-b/run', $out);
    }

    /**
     * @return CronEntry[]
     */
    private function fixtureEntries(): array
    {
        return [
            new CronEntry('test-a', 'run', [], self::SCHEDULE_A, 'entry A'),
            new CronEntry('test-b', 'run', ['x'], self::SCHEDULE_B, 'entry B'),
        ];
    }

    private function appPath(): string
    {
        return dirname(Yii::getAlias('@app'));
    }

    private function controller(): ManageController
    {
        $controller = new ManageController('manage', Yii::$app->getModule('cron'));
        $controller->interactive = false;
        return $controller;
    }

    private function crontab(): FakeCrontab
    {
        $crontab = Yii::$app->getModule('cron')->get('crontab');
        assert($crontab instanceof FakeCrontab);
        return $crontab;
    }

    private function scanner(): FakeCronScanner
    {
        $scanner = Yii::$app->getModule('cron')->get('cronScanner');
        assert($scanner instanceof FakeCronScanner);
        return $scanner;
    }
}
