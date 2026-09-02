<?php

declare(strict_types=1);

namespace glpzzz\cron\controllers;

use glpzzz\cron\components\CronScanner;
use glpzzz\cron\components\CronStatus;
use glpzzz\cron\components\Crontab;
use glpzzz\cron\models\CronEntry;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\console\widgets\Table;
use yii\helpers\Console;

/**
 * Syncs #[Cron]-tagged console actions to the invoking user's crontab.
 *
 * Registered by {@see \glpzzz\cron\Module} as `manage` when the host is a console app,
 * so the routes are `<module-id>/manage/<action>` (e.g. `cron/manage/install-all`).
 */
class ManageController extends Controller
{
    public $defaultAction = 'list';

    public bool $force = false;
    public bool $all = false;

    public function options($actionID): array
    {
        return match ($actionID) {
            'install-all' => [...parent::options($actionID), 'force'],
            'list' => [...parent::options($actionID), 'all'],
            default => parent::options($actionID),
        };
    }

    public function optionAliases(): array
    {
        return [...parent::optionAliases(), 'a' => 'all'];
    }

    /**
     * Lists crontab lines currently installed for this app, matched back to their #[Cron]
     * entry for Route/Params/description. With --all/-a, lists every discovered #[Cron]
     * entry instead, whether currently installed or not.
     */
    public function actionList(): int
    {
        if ($this->all) {
            $declared = $this->status()->declared();   // no crontab read on this path
            if ($declared === []) {
                $this->stdout("No #[Cron]-tagged actions found.\n");
                return ExitCode::OK;
            }

            $this->renderTable(array_map(
                fn (CronEntry $entry): array => $this->entryRow($entry, $entry->schedule ?? '(unscheduled)'),
                $declared
            ));
            return ExitCode::OK;
        }

        $report = $this->status()->report();
        $rows = [];
        foreach ($report->inSync as $entry) {
            $rows[] = $this->entryRow($entry, (string) $entry->schedule);
        }
        foreach ($report->drifted as $drift) {
            $rows[] = $this->entryRow($drift['entry'], $drift['installedSchedule']);
        }
        foreach ($report->orphaned as $line) {
            $rows[] = ["(unrecognized)\n{$line['command']}", '', $line['schedule']];
        }

        if ($rows === []) {
            $this->stdout("No cron lines installed for this app.\n");
            return ExitCode::OK;
        }

        $this->renderTable($rows);
        return ExitCode::OK;
    }

    /**
     * Installs the crontab line for a single discovered #[Cron] action. No-op if an identical
     * line (same command and schedule) is already installed. Doesn't touch anything else.
     *
     * @param string[] $params comma-separated if more than one, e.g. `yii cron/manage/install some/route p1,p2`
     */
    public function actionInstall(string $route, array $params = []): int
    {
        $entry = $this->findEntry($route, $params);
        if ($entry === null) {
            $this->stderr("No #[Cron]-tagged action matches '{$this->describeRoute($route, $params)}'.\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }
        if (!$entry->isScheduled()) {
            $this->stderr("{$entry->getRoute($this->routePrefix())} has no schedule -- nothing to install.\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $command = $entry->getCommand($this->appPath(), $this->routePrefix());
        $crontab = $this->crontab();
        $lines = $crontab->readLines();

        foreach ($lines as $line) {
            $parsed = Crontab::parseLine($line);
            if ($parsed !== null && $parsed['command'] === $command && $parsed['schedule'] === $entry->schedule) {
                $this->stdout("Already installed: {$entry->getRoute($this->routePrefix())}\n");
                return ExitCode::OK;
            }
        }

        $lines[] = "{$entry->schedule} {$command}";
        $crontab->writeLines($lines);
        $this->stdout("Installed: {$entry->getRoute($this->routePrefix())}\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Installs every discovered #[Cron] action not already installed as an exact match.
     * With --force, first removes every line installed for this app, then installs all fresh
     * (the reconciliation path for schedule drift, renamed params, etc.).
     */
    public function actionInstallAll(): int
    {
        $entries = $this->discover();
        $appPath = $this->appPath();
        $prefix = $this->routePrefix();
        $crontab = $this->crontab();
        $lines = $crontab->readLines();

        if ($this->force) {
            if (!$this->confirm("This will remove all cron lines installed for {$appPath} and reinstall them fresh. Continue?")) {
                $this->stdout("Aborted.\n");
                return ExitCode::OK;
            }
            $lines = $this->withoutOurs($lines, $appPath);
        }

        $added = 0;
        foreach ($entries as $entry) {
            if (!$entry->isScheduled()) {
                continue;
            }

            $command = $entry->getCommand($appPath, $prefix);

            $alreadyInstalled = false;
            foreach ($lines as $line) {
                $parsed = Crontab::parseLine($line);
                if ($parsed !== null && $parsed['command'] === $command && $parsed['schedule'] === $entry->schedule) {
                    $alreadyInstalled = true;
                    break;
                }
            }
            if ($alreadyInstalled) {
                continue;
            }

            $lines[] = "{$entry->schedule} {$command}";
            $added++;
            $this->stdout("Installed: {$entry->getRoute($prefix)}\n", Console::FG_GREEN);
        }

        $crontab->writeLines($lines);
        $this->stdout("{$added} cron line(s) installed.\n");
        return ExitCode::OK;
    }

    /**
     * Removes any installed line matching a single discovered #[Cron] action's command,
     * regardless of its currently installed schedule.
     *
     * @param string[] $params comma-separated if more than one, e.g. `yii cron/manage/remove some/route p1,p2`
     */
    public function actionRemove(string $route, array $params = []): int
    {
        $entry = $this->findEntry($route, $params);
        if ($entry === null) {
            $this->stderr("No #[Cron]-tagged action matches '{$this->describeRoute($route, $params)}'.\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        if (!$this->confirm("Remove installed cron line(s) for {$entry->getRoute($this->routePrefix())}?")) {
            $this->stdout("Aborted.\n");
            return ExitCode::OK;
        }

        $command = $entry->getCommand($this->appPath(), $this->routePrefix());
        $crontab = $this->crontab();
        $lines = $crontab->readLines();

        $remaining = [];
        $removed = 0;
        foreach ($lines as $line) {
            $parsed = Crontab::parseLine($line);
            if ($parsed !== null && $parsed['command'] === $command) {
                $removed++;
                continue;
            }
            $remaining[] = $line;
        }

        $crontab->writeLines($remaining);
        $this->stdout("{$removed} cron line(s) removed for {$entry->getRoute($this->routePrefix())}.\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Removes every installed line referencing this app's path, regardless of what's
     * currently declared in code (cleans up orphans from deleted #[Cron] actions too).
     */
    public function actionRemoveAll(): int
    {
        $appPath = $this->appPath();

        if (!$this->confirm("Remove all cron lines installed for {$appPath}?")) {
            $this->stdout("Aborted.\n");
            return ExitCode::OK;
        }

        $crontab = $this->crontab();
        $lines = $crontab->readLines();
        $remaining = $this->withoutOurs($lines, $appPath);
        $crontab->writeLines($remaining);

        $this->stdout((count($lines) - count($remaining)) . " cron line(s) removed.\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * @return CronEntry[]
     */
    private function discover(): array
    {
        return $this->scanner()->scan();
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function entryRow(CronEntry $entry, string $schedule): array
    {
        return [
            "{$entry->getRoute($this->routePrefix())}\n{$entry->description}",
            implode(' ', $entry->params),
            $schedule,
        ];
    }

    /**
     * @param array<int, array{0: string, 1: string, 2: string}> $rows
     */
    private function renderTable(array $rows): void
    {
        echo Table::widget([
            'headers' => ['Route', 'Params', 'Schedule'],
            'rows' => $rows,
        ]);
    }

    private function routePrefix(): string
    {
        return $this->module->uniqueId;
    }

    private function appPath(): string
    {
        return dirname(Yii::getAlias('@app'));
    }

    private function crontab(): Crontab
    {
        $crontab = $this->module->get('crontab');
        assert($crontab instanceof Crontab);
        return $crontab;
    }

    private function scanner(): CronScanner
    {
        $scanner = $this->module->get('cronScanner');
        assert($scanner instanceof CronScanner);
        return $scanner;
    }

    private function status(): CronStatus
    {
        $status = $this->module->get('cronStatus');
        assert($status instanceof CronStatus);
        return $status;
    }

    /**
     * @param string[] $params
     */
    private function findEntry(string $route, array $params): ?CronEntry
    {
        $prefix = $this->routePrefix();
        foreach ($this->discover() as $entry) {
            if ($entry->getRoute($prefix) === "{$prefix}/{$route}" && $entry->params === $params) {
                return $entry;
            }
        }
        return null;
    }

    /**
     * @param string[] $params
     */
    private function describeRoute(string $route, array $params): string
    {
        return $params === [] ? $route : "{$route} " . implode(' ', $params);
    }

    /**
     * @param string[] $lines
     * @return string[]
     */
    private function withoutOurs(array $lines, string $appPath): array
    {
        return array_values(array_filter($lines, static function (string $line) use ($appPath): bool {
            $parsed = Crontab::parseLine($line);
            return $parsed === null || !str_contains($parsed['command'], $appPath);
        }));
    }
}
