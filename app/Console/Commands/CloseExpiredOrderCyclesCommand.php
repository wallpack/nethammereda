<?php

namespace App\Console\Commands;

use App\Services\OrderCycleAutoCloser;
use Illuminate\Console\Command;

class CloseExpiredOrderCyclesCommand extends Command
{
    protected $signature = 'order-cycles:close-expired';

    protected $description = 'Close open order cycles whose deadline has passed';

    public function handle(OrderCycleAutoCloser $autoCloser): int
    {
        $closed = $autoCloser->closeExpiredOpenCycles();

        $this->info("Closed expired order cycles: {$closed}");

        return self::SUCCESS;
    }
}
