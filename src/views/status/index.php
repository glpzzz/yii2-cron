<?php

/**
 * @var yii\web\View $this
 * @var \glpzzz\cron\models\CronStatusReport|null $report
 * @var string|null $error
 * @var \glpzzz\cron\models\CronEntry[] $declared
 * @var string $routePrefix
 */

use glpzzz\cron\models\CronEntry;
use yii\helpers\Html;

$this->title = 'Cron Jobs';
$this->params['breadcrumbs'][] = $this->title;

/** @var array<string,string> $statusByRoute route => badge label */
$statusByRoute = [];
if ($report !== null) {
    foreach ($report->inSync as $e) {
        $statusByRoute[$e->getRoute($routePrefix)] = 'installed';
    }
    foreach ($report->drifted as $d) {
        $statusByRoute[$d['entry']->getRoute($routePrefix)] =
            'drift: installed "' . $d['installedSchedule'] . '"';
    }
    foreach ($report->missing as $e) {
        $statusByRoute[$e->getRoute($routePrefix)] = 'missing';
    }
}

$scheduled = $report !== null
    ? $report->scheduled
    : array_values(array_filter($declared, static fn (CronEntry $e): bool => $e->isScheduled()));
$unscheduled = $report !== null
    ? $report->unscheduled
    : array_values(array_filter($declared, static fn (CronEntry $e): bool => !$e->isScheduled()));
?>
<div class="cron-status">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php if ($error !== null): ?>
        <div class="alert alert-warning">
            The installed-state comparison is unavailable here (<?= Html::encode($error) ?>).
            Showing the declared <code>#[Cron]</code> actions only.
        </div>
    <?php elseif ($report !== null && !$report->hasDiscrepancies()): ?>
        <div class="alert alert-success">In sync — every scheduled cron is installed as declared.</div>
    <?php elseif ($report !== null): ?>
        <div class="alert alert-warning">
            <?= $report->discrepancyCount() ?> discrepancy(ies) between the declared crons and the installed crontab.
        </div>
    <?php endif; ?>

    <h2>Scheduled (<?= count($scheduled) ?>)</h2>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Route</th><th>Schedule</th><th>Params</th><th>Description</th>
                <?php if ($report !== null): ?>
                    <th>Status</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($scheduled as $entry): ?>
            <?php $route = $entry->getRoute($routePrefix); ?>
            <tr>
                <td><code><?= Html::encode($route) ?></code></td>
                <td><code><?= Html::encode((string) $entry->schedule) ?></code></td>
                <td><?= Html::encode(implode(' ', $entry->params)) ?></td>
                <td><?= Html::encode($entry->description) ?></td>
                <?php if ($report !== null): ?>
                    <td><?= Html::encode($statusByRoute[$route] ?? '—') ?></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($report !== null && $report->hasDiscrepancies()): ?>
        <h2>Discrepancies</h2>

        <?php if ($report->missing !== []): ?>
            <h3>Missing — declared &amp; scheduled, not in the crontab</h3>
            <ul>
                <?php foreach ($report->missing as $entry): ?>
                    <li><code><?= Html::encode($entry->getRoute($routePrefix)) ?></code>
                        (<?= Html::encode((string) $entry->schedule) ?>)</li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($report->drifted !== []): ?>
            <h3>Drifted — installed with a different schedule</h3>
            <ul>
                <?php foreach ($report->drifted as $drift): ?>
                    <li><code><?= Html::encode($drift['entry']->getRoute($routePrefix)) ?></code>:
                        installed <code><?= Html::encode($drift['installedSchedule']) ?></code>,
                        declared <code><?= Html::encode((string) $drift['entry']->schedule) ?></code></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($report->orphaned !== []): ?>
            <h3>Orphaned — installed for this app, no matching <code>#[Cron]</code></h3>
            <ul>
                <?php foreach ($report->orphaned as $line): ?>
                    <li><code><?= Html::encode($line['schedule']) ?></code> —
                        <code><?= Html::encode($line['command']) ?></code></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($unscheduled !== []): ?>
        <h2>Unscheduled <code>#[Cron]</code> actions (<?= count($unscheduled) ?>)</h2>
        <p>Documented as cron-manageable but declared with <code>schedule: null</code> — not installed.</p>
        <table class="table table-striped">
            <thead><tr><th>Route</th><th>Params</th><th>Description</th></tr></thead>
            <tbody>
            <?php foreach ($unscheduled as $entry): ?>
                <tr>
                    <td><code><?= Html::encode($entry->getRoute($routePrefix)) ?></code></td>
                    <td><?= Html::encode(implode(' ', $entry->params)) ?></td>
                    <td><?= Html::encode($entry->description) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
