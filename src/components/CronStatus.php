<?php

declare(strict_types=1);

namespace glpzzz\cron\components;

use glpzzz\cron\models\CronEntry;
use glpzzz\cron\models\CronStatusReport;

/**
 * Computes how the declared #[Cron] entries compare with what is actually installed in the
 * crontab. Used by both `cron/manage/list` (console) and the web status page.
 *
 * "Belonging to the current system" = crontab lines whose command contains {@see $appPath}
 * (the repo root that the generated `cd` targets).
 */
final class CronStatus
{
    public function __construct(
        private CronScanner $scanner,
        private Crontab $crontab,
        private string $appPath,
        private string $routePrefix,
    ) {
    }

    /**
     * @return CronEntry[] always available, even when `crontab -l` cannot be read
     */
    public function declared(): array
    {
        return $this->scanner->scan();
    }

    public function report(): CronStatusReport
    {
        $declared = $this->declared();

        $scheduled = [];
        $unscheduled = [];
        $commandToEntry = [];
        foreach ($declared as $entry) {
            if ($entry->isScheduled()) {
                $scheduled[] = $entry;
                $commandToEntry[$entry->getCommand($this->appPath, $this->routePrefix)] = $entry;
            } else {
                $unscheduled[] = $entry;
            }
        }

        $ownLines = $this->ownLines();

        $inSync = [];
        $drifted = [];
        $orphaned = [];
        $installedCommands = [];
        foreach ($ownLines as $line) {
            $installedCommands[$line['command']] = true;
            $entry = $commandToEntry[$line['command']] ?? null;
            if ($entry === null) {
                $orphaned[] = $line;
            } elseif ($line['schedule'] === $entry->schedule) {
                $inSync[] = $entry;
            } else {
                $drifted[] = ['entry' => $entry, 'installedSchedule' => $line['schedule']];
            }
        }

        $missing = [];
        foreach ($scheduled as $entry) {
            if (!isset($installedCommands[$entry->getCommand($this->appPath, $this->routePrefix)])) {
                $missing[] = $entry;
            }
        }

        return new CronStatusReport(
            declared: $declared,
            scheduled: $scheduled,
            unscheduled: $unscheduled,
            inSync: $inSync,
            missing: $missing,
            drifted: $drifted,
            orphaned: $orphaned,
        );
    }

    /**
     * @return array<int, array{schedule: string, command: string}>
     */
    private function ownLines(): array
    {
        $out = [];
        foreach ($this->crontab->readLines() as $line) {
            $parsed = Crontab::parseLine($line);
            if ($parsed !== null && str_contains($parsed['command'], $this->appPath)) {
                $out[] = $parsed;
            }
        }
        return $out;
    }
}
