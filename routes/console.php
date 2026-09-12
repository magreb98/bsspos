<?php

declare(strict_types=1);

use App\Platform\Outbox\Actions\RelayMessages;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new RelayMessages())->everyMinute()->onOneServer();
Schedule::command('tokens:prune-expired')->daily()->onOneServer();
