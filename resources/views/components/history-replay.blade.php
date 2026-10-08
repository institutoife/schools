@props(['label' => 'Repetir animación'])
<button type="button" {{ $attributes->class(['eh-button', 'eh-icon-button']) }} aria-label="{{ $label }}" title="{{ $label }}">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
        <path d="M20 7v5h-5M4 17v-5h5" />
        <path d="M6.1 7a7 7 0 0 1 11.55-1L20 9M4 15l2.35 3A7 7 0 0 0 17.9 17" />
    </svg>
</button>
