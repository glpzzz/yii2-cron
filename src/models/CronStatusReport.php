<?php

declare(strict_types=1);

namespace glpzzz\cron\models;

/**
 * Snapshot of how the declared #[Cron] entries line up with the installed crontab.
 *
 * @see \glpzzz\cron\components\CronStatus::report()
 */
final class CronStatusReport
{
    /**
     * @param CronEntry[] $declared every discovered entry
     * @param CronEntry[] $scheduled declared entries with a schedule
     * @param CronEntry[] $unscheduled declared entries with schedule === null (documented only)
     * @param CronEntry[] $inSync scheduled entries whose exact "{schedule} {command}" line is installed
     * @param CronEntry[] $missing scheduled entries with no installed line for their command
     * @param array<int, array{entry: CronEntry, installedSchedule: string}> $drifted command installed with a different schedule
     * @param array<int, array{schedule: string, command: string}> $orphaned installed own-lines matching no declared command
     */
    public function __construct(
        public readonly array $declared,
        public readonly array $scheduled,
        public readonly array $unscheduled,
        public readonly array $inSync,
        public readonly array $missing,
        public readonly array $drifted,
        public readonly array $orphaned,
    ) {
    }

    public function hasDiscrepancies(): bool
    {
        return $this->missing !== [] || $this->drifted !== [] || $this->orphaned !== [];
    }

    public function discrepancyCount(): int
    {
        return count($this->missing) + count($this->drifted) + count($this->orphaned);
    }
}
