<x-filament-widgets::widget>
    <section class="nh-admin-dashboard-card nh-admin-dashboard-card--supplier">
        <div class="nh-admin-dashboard-card__header">
            <div>
                <p class="nh-admin-eyebrow">Поставщик</p>
                <h2 class="nh-admin-dashboard-card__title">{{ $status }}</h2>
            </div>
        </div>

        @if ($deliveryPending)
            <div class="nh-admin-alert nh-admin-alert--warning" role="status">
                <div>
                    <strong>Доставка ожидает отметки</strong>
                    <p>После фактической доставки нажмите «Отметить доставку», чтобы блюда попали в холодильники пользователей.</p>
                </div>
            </div>
        @endif

        <dl class="nh-admin-fact-list">
            <div>
                <dt>Дата отправки</dt>
                <dd>{{ $sentAt }}</dd>
            </div>
            <div>
                <dt>Кто отправил</dt>
                <dd>{{ $sentBy }}</dd>
            </div>
            <div>
                <dt>Строк в snapshot</dt>
                <dd>{{ $rowsCount }}</dd>
            </div>
            <div>
                <dt>Порций</dt>
                <dd>{{ $totalQuantity }}</dd>
            </div>
            <div>
                <dt>Сумма</dt>
                <dd>{{ $totalPrice }}</dd>
            </div>
        </dl>

        <div class="nh-admin-dashboard-card__footer">
            <x-filament::button tag="a" :href="$cycleUrl" color="gray" outlined icon="heroicon-m-arrow-top-right-on-square">
                Перейти к циклам
            </x-filament::button>
        </div>
    </section>
</x-filament-widgets::widget>
