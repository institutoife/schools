@props(['labels' => false])
<div {{ $attributes->merge(['class' => 'social-links']) }} aria-label="Redes sociales oficiales">
    @foreach(['tiktok' => 'TikTok', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube'] as $network => $label)
        <a href="{{ config("brand.social.$network") }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $label }} de {{ config('brand.name') }}">
            <i class="fa-brands fa-{{ $network }}" aria-hidden="true"></i>@if($labels)<span>{{ $label }}</span>@endif
        </a>
    @endforeach
</div>
