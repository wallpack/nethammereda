<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info(
            'No data seeded. To destructively rebuild local demo data, run: php artisan demo:reset',
        );
    }
}
