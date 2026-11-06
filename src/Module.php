<?php

declare(strict_types=1);

namespace glpzzz\cron;

use glpzzz\cron\components\CronScanner;
use glpzzz\cron\components\CronStatus;
use glpzzz\cron\components\Crontab;
use glpzzz\cron\controllers\ManageController;
use glpzzz\cron\controllers\StatusController;
use Yii;
use yii\base\Module as BaseModule;
use yii\console\Application as ConsoleApplication;

/**
 * Mount this in the app's `modules` config (conventionally as `cron`), pointing `scan` at the
 * namespace/path that holds your #[Cron]-tagged controllers:
 *
 * ```php
 * 'modules' => [
 *     'cron' => [
 *         'class' => \glpzzz\cron\Module::class,
 *         'controllerNamespace' => 'console\\controllers\\cron',
 *         'scan' => ['console\\controllers\\cron' => '@console/controllers/cron'],
 *     ],
 * ],
 * ```
 *
 * It exposes `manage` (console: install/remove/list crontab lines) and `status`
 * (web: read-only listing + drift report), each only in the matching application kind.
 */
class Module extends BaseModule
{
    /**
     * Where the host app's #[Cron] controllers live. Override in config. The package's own
     * `manage`/`status` controllers are wired via {@see $controllerMap}, not this namespace.
     */
    public $controllerNamespace = 'glpzzz\\cron\\controllers';

    /**
     * @var array<string, string> namespace prefix => path (alias ok), passed to CronScanner::$scan.
     *     Defaults to the module's own controller namespace/path.
     */
    public array $scan = [];

    public bool $recursiveScan = false;

    /**
     * Whether `manage/install` / `manage/install-all` may write crontab lines for this app.
     * Set it false where the crons must not run (e.g. a dev/staging deploy that shares the
     * production deploy script): `install` then refuses (exit {@see \yii\console\ExitCode::CONFIG}),
     * and `install-all` removes every line installed for this app instead -- so a deploy that
     * runs `install-all --force --interactive=0` converges the crontab to "no crons".
     * `list`, `remove`, `remove-all` and the web `status` page are unaffected.
     */
    public bool $install = true;

    public $defaultRoute = 'status';

    /**
     * The crontab command's `cd` target and the marker used to tell "our" lines apart:
     * the repo root, one level above `@app` (which the advanced template points at the app dir).
     */
    public function appPath(): string
    {
        return dirname(Yii::getAlias('@app'));
    }

    /**
     * Route prefix baked into generated commands -- the id this module is mounted under.
     */
    public function routePrefix(): string
    {
        return $this->uniqueId;
    }

    public function init(): void
    {
        parent::init();

        if ($this->scan === []) {
            $this->scan = [$this->controllerNamespace => '@' . str_replace('\\', '/', $this->controllerNamespace)];
        }

        // `manage` is a console controller, `status` a web one -- register each only where it runs.
        $this->controllerMap += (Yii::$app instanceof ConsoleApplication)
            ? ['manage' => ManageController::class]
            : ['status' => StatusController::class];

        if (!$this->has('crontab')) {
            $this->set('crontab', Crontab::class);
        }
        if (!$this->has('cronScanner')) {
            $this->set('cronScanner', [
                'class' => CronScanner::class,
                'scan' => $this->scan,
                'recursive' => $this->recursiveScan,
            ]);
        }
        if (!$this->has('cronStatus')) {
            $this->set('cronStatus', fn (): CronStatus => new CronStatus(
                $this->get('cronScanner'),
                $this->get('crontab'),
                $this->appPath(),
                $this->routePrefix(),
            ));
        }
    }
}
