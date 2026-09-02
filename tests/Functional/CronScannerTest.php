<?php

declare(strict_types=1);

namespace glpzzz\cron\tests\Functional;

use glpzzz\cron\components\CronScanner;
use glpzzz\cron\models\CronEntry;
use glpzzz\cron\tests\TestCase;
use Yii;

final class CronScannerTest extends TestCase
{
    public function testScanReflectsCronAttributesFromTheConfiguredNamespace(): void
    {
        $this->mockConsoleApplication();

        $scanner = Yii::$app->getModule('cron')->get('cronScanner');
        assert($scanner instanceof CronScanner);

        $routes = array_map(
            static fn (CronEntry $e): string => $e->getRoute('cron') . ' [' . implode(',', $e->params) . ']',
            $scanner->scan()
        );
        sort($routes);

        self::assertSame([
            'cron/fixture/digest [1]',
            'cron/fixture/digest [7]',
            'cron/fixture/manual []',
            'cron/fixture/ping []',
        ], $routes);
    }

    public function testUnscheduledEntriesAreDiscoveredButFlaggedNotScheduled(): void
    {
        $this->mockConsoleApplication();

        $scanner = Yii::$app->getModule('cron')->get('cronScanner');
        assert($scanner instanceof CronScanner);

        $byRoute = [];
        foreach ($scanner->scan() as $entry) {
            $byRoute[$entry->actionId] = $entry;
        }

        self::assertTrue($byRoute['ping']->isScheduled());
        self::assertFalse($byRoute['manual']->isScheduled());
    }
}
