@props([
    'wordClass' => '',
])

<div {{ $attributes->class('nh-brand-logo')->merge(['aria-label' => 'Nethammereda', 'role' => 'img']) }}>
    <span class="{{ trim('nh-brand-logo__word ' . $wordClass) }}" aria-hidden="true">
        Nethammer<span class="nh-brand-logo__accent">eda</span>
    </span>
</div>
