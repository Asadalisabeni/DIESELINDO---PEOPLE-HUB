<?php

namespace App\Console\Commands;

use App\Services\Leave\LeaveManager;
use Illuminate\Console\Command;

class ProcessLeaveLifecycle extends Command
{
    protected $signature = 'leave:process-lifecycle {--date= : Business date in YYYY-MM-DD}';

    protected $description = 'Expire due leave entitlements and queue configurable balance reminders';

    public function handle(LeaveManager $manager): int
    {
        $date = $this->option('date') ?: now()->toDateString();
        $expired = $manager->expireDue((string) $date);
        $reminders = $manager->notifyExpiring(30);
        $this->info("Processed {$expired} expiries and {$reminders} 30-day reminders.");

        return self::SUCCESS;
    }
}
