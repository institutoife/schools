@props(['eyebrow' => null, 'title', 'description' => null, 'align' => 'center'])
<div class="section-heading section-heading--{{ $align }}">
    @if($eyebrow)<span class="eyebrow">{{ $eyebrow }}</span>@endif
    <h2>{{ $title }}</h2>
    @if($description)<p>{{ $description }}</p>@endif
</div>
