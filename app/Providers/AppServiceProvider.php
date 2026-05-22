<?php

namespace App\Providers;

use App\Models\FridgeItem;
use App\Models\Order;
use App\Models\OrderCycle;
use App\Models\OrderItem;
use App\Observers\OrderCycleObserver;
use App\Policies\FridgeItemPolicy;
use App\Policies\OrderItemPolicy;
use App\Policies\OrderPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(OrderItem::class, OrderItemPolicy::class);
        Gate::policy(FridgeItem::class, FridgeItemPolicy::class);

        OrderCycle::observe(OrderCycleObserver::class);
    }
}
