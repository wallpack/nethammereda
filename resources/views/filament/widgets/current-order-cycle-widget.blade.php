<x-filament-widgets::widget>
    <section class="nh-admin-dashboard-card nh-admin-dashboard-card--cycle">
        <div class="nh-admin-dashboard-card__header">
            <div>
                <p class="nh-admin-eyebrow">Текущий цикл заказа</p>
                <h2 class="nh-admin-dashboard-card__title">{{ $cycle?->title ?? 'Недельный цикл не создан' }}</h2>
            </div>

            <x-filament::badge :color="$statusColor">
                {{ $statusLabel }}
            </x-filament::badge>
        </div>

        <dl class="nh-admin-cycle-grid">
            <div>
                <dt>Неделя</dt>
                <dd>{{ $period }}</dd>
            </div>

            <div>
                <dt>Дедлайн</dt>
                <dd>{{ $deadline }}</dd>
            </div>

            <div>
                <dt>Осталось</dt>
                <dd>{{ $timeLeft }}</dd>
            </div>

            <div>
                <dt>Следующий шаг</dt>
                <dd>{{ $nextStep }}</dd>
            </div>
        </dl>

        @if ($deliveryPending)
            <div class="nh-admin-alert nh-admin-alert--warning" role="status">
                <div>
                    <strong>Доставка ожидает отметки</strong>
                    <p>После фактической доставки нажмите «Отметить доставку», чтобы блюда попали в холодильники пользователей.</p>
                </div>
            </div>
        @endif

        <div class="nh-admin-dashboard-card__footer nh-admin-action-strip" aria-label="Основные действия по текущему циклу">
            <x-filament::button tag="a" :href="$primaryActionUrl" :color="$primaryActionColor" :icon="$primaryActionIcon">
                {{ $primaryActionLabel }}
            </x-filament::button>

            @if ($cycle)
                <x-filament::button tag="a" :href="$cycleUrl" color="gray" outlined icon="heroicon-m-arrow-top-right-on-square">
                    Открыть цикл
                </x-filament::button>

                <x-filament::button tag="a" :href="$ordersUrl" color="gray" outlined icon="heroicon-m-shopping-bag">
                    Открыть заказы
                </x-filament::button>
            @endif

            <x-filament::button tag="a" :href="$cyclesUrl" color="gray" outlined icon="heroicon-m-calendar-days">
                История циклов
            </x-filament::button>

            @if ($cycle)
                <x-filament::button tag="a" :href="$supplierExportsUrl" color="gray" outlined icon="heroicon-m-document-arrow-down">
                    История отправок
                </x-filament::button>
            @endif
        </div>
    </section>
</x-filament-widgets::widget>
