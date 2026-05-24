<?php

namespace App\Console\Commands;

use Database\Seeders\DemoDatabaseSeeder;
use Illuminate\Console\Command;

class DemoResetCommand extends Command
{
    protected $signature = 'demo:reset
        {--force : Run without interactive confirmation}';

    protected $description = 'Destructively rebuild demo data with an open current order cycle';

    public function handle(): int
    {
        if (! $this->resetIsAllowed()) {
            $this->error(
                'Demo reset is blocked in this environment. Set DEMO_RESET_ALLOWED=true to explicitly allow destructive reset.',
            );

            return self::FAILURE;
        }

        $this->warn('This command deletes existing demo menu, orders, cycles, and fridge data.');

        if (! $this->option('force') && ! $this->confirm('Continue with destructive demo reset?')) {
            $this->info('Demo reset cancelled. No data changed.');

            return self::SUCCESS;
        }

        config(['lunch.demo_reset_execution_authorized' => true]);

        try {
            $seeder = $this->laravel->make(DemoDatabaseSeeder::class);
            $seeder->setContainer($this->laravel)->setCommand($this)->__invoke();
        } finally {
            config(['lunch.demo_reset_execution_authorized' => false]);
        }

        $this->info('Demo data rebuilt. Current order cycle is open for ordering.');

        return self::SUCCESS;
    }

    private function resetIsAllowed(): bool
    {
        return $this->laravel->environment(['local', 'testing'])
            || (bool) config('lunch.demo_reset_allowed', false);
    }
}
