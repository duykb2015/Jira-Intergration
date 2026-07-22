<?php

namespace App\Console\Commands;

use App\Jobs\ReconcileClockifyConnection;
use App\Models\ClockifyConnection;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ReconcileClockifyCommand extends Command
{
    protected $signature = 'clockify:reconcile {--connection=} {--from=} {--to=}';

    protected $description = 'Queue reconciliation for active Clockify connections';

    public function handle(): int
    {
        try {
            $from = $this->option('from') ? CarbonImmutable::parse($this->option('from')) : now()->toImmutable()->subDays(7);
            $to = $this->option('to') ? CarbonImmutable::parse($this->option('to')) : now()->toImmutable();
        } catch (\Throwable) {
            $this->error('The --from and --to values must be valid dates.');

            return self::FAILURE;
        }
        if ($from->greaterThan($to)) {
            $this->error('--from must be before --to.');

            return self::FAILURE;
        }

        $query = ClockifyConnection::query()->where('status', 'connected');

        if ($id = $this->option('connection')) {
            $query->whereKey($id);
        }

        $count = 0;
        $query->select('id')->eachById(function (ClockifyConnection $connection) use ($from, $to, &$count): void {
            ReconcileClockifyConnection::dispatch($connection->id, $from->toIso8601String(), $to->toIso8601String());
            $count++;
        });

        $this->info("Queued {$count} Clockify connection(s).");

        return self::SUCCESS;
    }
}
