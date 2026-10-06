<footer class="site-footer">
    <div class="shell footer-grid">
        <div><x-brand-logo /><p>Información educativa para explorar y comprender los colegios de Bolivia.</p><x-social-links /></div>
        <div><h2>Enlaces rápidos</h2><a href="{{ route('home') }}#buscar">Buscar colegios</a><a href="{{ route('home') }}#explorar">Explorar Bolivia</a><a href="{{ url('/admin') }}">Panel de acceso</a></div>
        <div><h2>Contacto</h2><a href="mailto:{{ config('brand.contact_email') }}">{{ config('brand.contact_email') }}</a><a href="https://{{ config('brand.domain') }}" target="_blank" rel="noopener noreferrer">{{ config('brand.domain') }}</a><x-whatsapp-button label="WhatsApp" /></div>
    </div>
    <div class="shell footer-bottom"><span>© {{ date('Y') }} {{ config('brand.legal_name') }}.</span><span>Datos educativos de Bolivia</span></div>
</footer>
