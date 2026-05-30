<x-filament-widgets::widget>
    <section class="nh-admin-dashboard-card nh-admin-dashboard-card--activity">
        <div class="nh-admin-dashboard-card__header">
            <div>
                <p class="nh-admin-eyebrow">Последние действия</p>
                <h2 class="nh-admin-dashboard-card__title">Операционный журнал</h2>
            </div>
        </div>

        @if ($activities->isEmpty())
            <div class="nh-admin-empty-state">
                <p>Пока нет отправок поставщику, доставок или обновленных циклов.</p>
                <x-filament::button tag="a" :href="\App\Filament\Resources\OrderCycles\OrderCycleResource::getUrl('index')" color="gray" outlined>
                    Открыть недельные циклы
                </x-filament::button>
            </div>
        @else
            <ol class="nh-admin-activity-list">
                @foreach ($activities as $activity)
                    <li>
                        <div class="nh-admin-activity-list__marker" aria-hidden="true"></div>

                        <div class="nh-admin-activity-list__body">
                            <div class="nh-admin-activity-list__topline">
                                <span class="nh-admin-activity-list__kind nh-admin-activity-list__kind--{{ $activity['tone'] }}">
                                    {{ $activity['kind'] }}
                                </span>
                                <time datetime="{{ $activity['happened_at']->toIso8601String() }}">
                                    {{ $activity['happened_at']->format('d.m H:i') }}
                                </time>
                            </div>
                            <p class="nh-admin-activity-list__title">{{ $activity['title'] }}</p>
                            <p class="nh-admin-activity-list__description">{{ $activity['description'] }}</p>
                            <p class="nh-admin-activity-list__actor">Ответственный: {{ $activity['actor'] }}</p>
                        </div>

                        @if ($activity['url'])
                            <a class="nh-admin-activity-list__link" href="{{ $activity['url'] }}">
                                Открыть
                            </a>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
</x-filament-widgets::widget>
