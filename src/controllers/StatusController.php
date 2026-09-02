<?php

declare(strict_types=1);

namespace glpzzz\cron\controllers;

use glpzzz\cron\components\CronStatus;
use RuntimeException;
use yii\filters\VerbFilter;
use yii\web\Controller;

/**
 * Read-only web page: the discovered #[Cron] entries and how they compare with the installed
 * crontab. Registered by {@see \glpzzz\cron\Module} as `status` when the host is a web app.
 *
 * This is the one place a web request runs `crontab -l`. It never writes. Access control is
 * the host application's responsibility -- the module has no knowledge of its roles.
 */
class StatusController extends Controller
{
    public $defaultAction = 'index';

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => ['index' => ['get']],
            ],
        ];
    }

    public function actionIndex(): string
    {
        $status = $this->module->get('cronStatus');
        assert($status instanceof CronStatus);

        $error = null;
        $report = null;
        try {
            $report = $status->report();
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        return $this->render('index', [
            'report' => $report,
            'error' => $error,
            'declared' => $status->declared(),
            'routePrefix' => $this->module->uniqueId,
        ]);
    }
}
