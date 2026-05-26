<?php

namespace Tests\Feature\Domain;

use App\Enums\OrderCycleStatus;
use App\Models\OrderCycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderCycleAutoCloseCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function command_closes_expired_open_cycles(): void
    {
        $expiredOpen = $this->createCycle(OrderCycleStatus::Open, now()->subMinute());
        $futureOpen = $this->createCycle(OrderCycleStatus::Open, now()->addHour());

        $this->artisan('order-cycles:close-expired')
            ->expectsOutput('Closed expired order cycles: 1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('order_cycles', [
            'id' => $expiredOpen->id,
            'status' => OrderCycleStatus::Closed->value,
        ]);
        $this->assertDatabaseHas('order_cycles', [
            'id' => $futureOpen->id,
            'status' => OrderCycleStatus::Open->value,
        ]);
    }

    #[Test]
    public function command_does_not_close_non_open_statuses(): void
    {
        $draft = $this->createCycle(OrderCycleStatus::Draft, now()->subMinute());
        $closed = $this->createCycle(OrderCycleStatus::Closed, now()->subMinute());
        $sent = $this->createCycle(OrderCycleStatus::SentToSupplier, now()->subMinute());
        $delivered = $this->createCycle(OrderCycleStatus::Delivered, now()->subMinute());
        $archived = $this->createCycle(OrderCycleStatus::Archived, now()->subMinute());

        $this->artisan('order-cycles:close-expired')
            ->expectsOutput('Closed expired order cycles: 0')
            ->assertExitCode(0);

        $this->assertDatabaseHas('order_cycles', [
            'id' => $draft->id,
            'status' => OrderCycleStatus::Draft->value,
        ]);
        $this->assertDatabaseHas('order_cycles', [
            'id' => $closed->id,
            'status' => OrderCycleStatus::Closed->value,
        ]);
        $this->assertDatabaseHas('order_cycles', [
            'id' => $sent->id,
            'status' => OrderCycleStatus::SentToSupplier->value,
        ]);
        $this->assertDatabaseHas('order_cycles', [
            'id' => $delivered->id,
            'status' => OrderCycleStatus::Delivered->value,
        ]);
        $this->assertDatabaseHas('order_cycles', [
            'id' => $archived->id,
            'status' => OrderCycleStatus::Archived->value,
        ]);
    }

    #[Test]
    public function command_is_idempotent(): void
    {
        $cycle = $this->createCycle(OrderCycleStatus::Open, now()->subMinute());

        $this->artisan('order-cycles:close-expired')
            ->expectsOutput('Closed expired order cycles: 1')
            ->assertExitCode(0);

        $this->artisan('order-cycles:close-expired')
            ->expectsOutput('Closed expired order cycles: 0')
            ->assertExitCode(0);

        $this->assertDatabaseHas('order_cycles', [
            'id' => $cycle->id,
            'status' => OrderCycleStatus::Closed->value,
        ]);
    }

    #[Test]
    public function scheduler_registers_close_expired_command(): void
    {
        $commands = collect(app(Schedule::class)->events())
            ->map(fn ($event): string => (string) $event->command);

        $this->assertTrue(
            $commands->contains(fn (string $command): bool => str_contains($command, 'order-cycles:close-expired')),
            'Expected order-cycles:close-expired to be registered in scheduler.',
        );
    }

    private function createCycle(OrderCycleStatus $status, mixed $closesAt): OrderCycle
    {
        return OrderCycle::query()->create([
            'title' => sprintf('Test cycle %s', $status->value),
            'starts_at' => now()->startOfWeek(),
            'closes_at' => $closesAt,
            'status' => $status,
        ]);
    }
}
