import './bootstrap';

document.querySelectorAll('[data-site-header]').forEach((header) => {
    const toggle = header.querySelector('.nav-toggle');
    const navigation = header.querySelector('.main-nav');
    toggle?.addEventListener('click', () => {
        const open = navigation.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
    });
});
