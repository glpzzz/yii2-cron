<?php

declare(strict_types=1);

namespace glpzzz\cron\tests\support;

use glpzzz\cron\attributes\Cron;
use yii\console\Controller;

/**
 * Exercised by CronScannerTest / CronStatusTest to cover the real reflection path.
 */
class FixtureController extends Controller
{
    #[Cron('every five minutes, no params', '*/5 * * * *')]
    public function actionPing(): int
    {
        return 0;
    }

    #[Cron('daily variant', '0 8 * * *', ['1'])]
    #[Cron('weekly variant', '0 14 * * 5', ['7'])]
    public function actionDigest(int $days): int
    {
        return $days;
    }

    #[Cron('documented but not scheduled')]
    public function actionManual(): int
    {
        return 0;
    }

    public function actionUntagged(): int
    {
        return 0;
    }
}
