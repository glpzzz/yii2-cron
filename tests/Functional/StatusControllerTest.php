<?php

declare(strict_types=1);

namespace glpzzz\cron\tests\Functional;

use glpzzz\cron\controllers\StatusController;
use glpzzz\cron\models\CronEntry;
use glpzzz\cron\testing\FakeCronScanner;
use glpzzz\cron\testing\FakeCrontab;
use glpzzz\cron\tests\support\ThrowingCrontab;
use glpzzz\cron\tests\TestCase;
use Yii;

final class StatusControllerTest extends TestCase
{
    public function testIndexListsDiscoveredRoutesAndReportsInSyncWhenInstalled(): void
    {
        $this->mockWebApplication($this->fakeComponents());
        $entry = new CronEntry('audit', 'check-smtp', [], '*/5 * * * *', 'smtp probe');
        $this->scanner()->setEntries([$entry]);

        $appPath = dirname(Yii::getAlias('@app'));   // same derivation the module uses
        $this->crontab()->writeLines(['*/5 * * * * ' . $entry->getCommand($appPath, 'cron')]);

        $html = $this->controller()->runAction('index');

        self::assertIsString($html);
        self::assertStringContainsString('cron/audit/check-smtp', $html);
        self::assertStringContainsString('In sync', $html);
    }

    public function testIndexFlagsAMissingScheduledCron(): void
    {
        $this->mockWebApplication($this->fakeComponents());
        $this->scanner()->setEntries([
            new CronEntry('audit', 'check-smtp', [], '*/5 * * * *', 'smtp probe'),
        ]);

        $html = $this->controller()->runAction('index');

        self::assertStringContainsString('cron/audit/check-smtp', $html);
        self::assertStringContainsString('discrepancy', $html);
    }

    public function testIndexStillRendersWhenTheCrontabCannotBeRead(): void
    {
        $this->mockWebApplication([
            'components' => [
                'crontab' => ['class' => ThrowingCrontab::class],
                'cronScanner' => ['class' => FakeCronScanner::class],
            ],
        ]);
        $this->scanner()->setEntries([
            new CronEntry('audit', 'check-smtp', [], '*/5 * * * *', 'smtp probe'),
        ]);

        $html = $this->controller()->runAction('index');

        self::assertStringContainsString('cron/audit/check-smtp', $html);
        self::assertStringContainsString('unavailable', $html);
    }

    private function controller(): StatusController
    {
        $controller = new StatusController('status', Yii::$app->getModule('cron'));
        $controller->layout = false;   // the host app supplies the layout in production
        return $controller;
    }

    private function scanner(): FakeCronScanner
    {
        $scanner = Yii::$app->getModule('cron')->get('cronScanner');
        assert($scanner instanceof FakeCronScanner);
        return $scanner;
    }

    private function crontab(): FakeCrontab
    {
        $crontab = Yii::$app->getModule('cron')->get('crontab');
        assert($crontab instanceof FakeCrontab);
        return $crontab;
    }
}
