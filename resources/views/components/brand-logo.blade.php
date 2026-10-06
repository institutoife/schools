@props(['compact' => false])
<span {{ $attributes->merge(['class' => 'brand-lockup']) }}>
    <img src="{{ asset(config('brand.logo')) }}" alt="Logo de {{ config('brand.name') }}" width="168" height="54">
    @unless($compact)
        <span><strong>{{ config('brand.platform_name') }}</strong><small>{{ config('brand.name') }}</small></span>
    @endunless
</span>
