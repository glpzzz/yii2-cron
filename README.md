# glpzzz/yii2-cron

Attribute-driven cron discovery and crontab synchronisation for Yii2 console applications.

Tag console actions with `#[Cron]`, then let the module install/remove/list the matching
lines in the invoking user's crontab. A read-only web page shows what is declared and flags
any drift from what is actually installed.

## Install

```bash
composer require glpzzz/yii2-cron
```

## Wire it up

Mount the module (conventionally as `cron`) and point `scan` at the namespace/path that holds
your `#[Cron]` controllers. Put the shared parts in `common/config/main.php`:

```php
'modules' => [
    'cron' => [
        'class' => \glpzzz\cron\Module::class,
        'scan'  => ['console\\controllers\\cron' => '@console/controllers/cron'],
    ],
],
```

and the console-only controller namespace in `console/config/main.php`:

```php
'modules' => [
    'cron' => ['controllerNamespace' => 'console\\controllers\\cron'],
],
```

The module registers `manage` (a `yii\console\Controller`) in console apps and `status`
(a `yii\web\Controller`) in web apps -- each only where it can run.

## Tag actions

```php
use glpzzz\cron\attributes\Cron;

class UsersController extends \yii\console\Controller
{
    #[Cron('send insurance-expiration alerts', '0 11 * * *')]
    public function actionNotifyForInsurance(): int { /* ... */ }

    // Repeatable -- schedule the same action twice with different params:
    #[Cron('daily new-job matches', '0 8 * * *', ['1'])]
    #[Cron('weekly new-job matches', '0 14 * * 5', ['7'])]
    public function actionNotifyUsersAboutNewJobs(int $daysSinceCreated): void { /* ... */ }
}
```

`schedule: null` marks an action as cron-manageable and documents it, but installs nothing.

## Console commands

```
yii cron/manage/list [--all|-a]        # installed lines (or every declared entry with --all)
yii cron/manage/install <route> [p1,p2]
yii cron/manage/install-all [--force]  # --force wipes this app's lines then reinstalls fresh
yii cron/manage/remove <route> [p1,p2]
yii cron/manage/remove-all
```

`<route>` is given without the module prefix, e.g. `users/notify-for-insurance`.
Run `install-all --force --interactive=0` on deploy, after migrations.

### Disabling installation per environment

Set the module's `install` option to `false` where the crons must not run -- typically a
dev/staging server that shares the production deploy script:

```php
'cron' => [
    'class' => \glpzzz\cron\Module::class,
    'install' => YII_ENV_PROD, // or a param, e.g. $params['install-crons']
    // ...
],
```

With `install => false`, `install` refuses (exit 78, `ExitCode::CONFIG`) and `install-all`
(with or without `--force`) **removes** every line installed for this app instead, so the
same deploy step converges the crontab to "no crons". `list`, `remove`, `remove-all` and the
status page behave as usual. Defaults to `true`.

## Web status page

`<module-mount>/status` (e.g. `/admin/cron/status`) lists the discovered crons and, when the
web user can read its own crontab, flags `missing` / `drifted` / `orphaned` lines. It is
read-only -- the one place a web request runs `crontab -l`, and it never writes.

**Access control is the host application's responsibility.** The module knows nothing about
your roles; protect the route with your app's access filter.

## Testing

`glpzzz\cron\testing\FakeCrontab` and `FakeCronScanner` let you drive the controllers without
touching the real crontab. Bind them as the module's components in your test config:

```php
'modules' => ['cron' => ['components' => [
    'crontab'     => \glpzzz\cron\testing\FakeCrontab::class,
    'cronScanner' => \glpzzz\cron\testing\FakeCronScanner::class,
]]],
```

## Requirements

PHP >= 8.1, Yii 2.0.14+, a POSIX `crontab` binary on the host that runs the sync command.
