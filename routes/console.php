<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
| Scheduled jobs (PLAN §4). Each phase adds the entries it owns. All times are UTC.
*/

// Ledger (Phase 2)
Schedule::command('ledger:snapshot')->dailyAt('00:01')->withoutOverlapping()->onOneServer();
Schedule::command('ledger:reconcile')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('ledger:invariant-check')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
