@props([
    'iconClass' => '',
    'wordClass' => '',
])

<div {{ $attributes->class('nh-brand-logo')->merge(['aria-label' => 'NethammerEda', 'role' => 'img']) }}>
    <img
        class="{{ trim('nh-brand-logo__icon ' . $iconClass) }}"
        src="{{ asset('images/brand/nethammer-icon.svg') }}"
        alt=""
        aria-hidden="true"
    >
    <span class="{{ trim('nh-brand-logo__word ' . $wordClass) }}" aria-hidden="true">
        nethammer<span>eda.</span>
    </span>
</div>
