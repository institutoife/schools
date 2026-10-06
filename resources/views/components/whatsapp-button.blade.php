@props(['label' => 'Escríbenos', 'message' => null])
<a {{ $attributes->merge(['class' => 'button button-whatsapp']) }} href="https://wa.me/{{ config('brand.whatsapp') }}?text={{ rawurlencode($message ?: config('brand.whatsapp_message')) }}" target="_blank" rel="noopener noreferrer" aria-label="Contactar a {{ config('brand.name') }} por WhatsApp">
    <i class="fa-brands fa-whatsapp" aria-hidden="true"></i> {{ $label }}
</a>
