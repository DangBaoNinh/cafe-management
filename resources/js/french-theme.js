// Presentation only: purchase, reservation and staff actions remain server-side.
let disposers = [];
let generation = 0;

function teardown() {
    generation++;
    disposers.splice(0).reverse().forEach(dispose => dispose());
}

async function initializeFrenchTheme() {
    teardown();
    const current = generation;
    if (!document.body.classList.contains('french-theme')) return;
    const base = new URL(document.querySelector('meta[name="french-theme-base"]').content, location.href);
    const listen = (target, event, callback) => {
        target?.addEventListener(event, callback);
        disposers.push(() => target?.removeEventListener(event, callback));
    };
    const retain = dispose => {
        if (current === generation) disposers.push(dispose);
        else dispose();
    };
    document.querySelectorAll('[data-print-invoice]').forEach(button => listen(button, 'click', () => window.print()));

    const menuButton = document.querySelector('[data-public-nav]');
    const publicNav = document.querySelector('#public-nav');
    const closePublic = () => {
        menuButton?.setAttribute('aria-expanded', 'false');
        publicNav?.classList.remove('is-open');
    };
    listen(menuButton, 'click', () => {
        const open = menuButton.getAttribute('aria-expanded') !== 'true';
        menuButton.setAttribute('aria-expanded', String(open));
        publicNav.classList.toggle('is-open', open);
    });
    listen(document, 'click', event => {
        if (publicNav && !publicNav.contains(event.target) && !menuButton.contains(event.target)) closePublic();
    });

    const shell = document.querySelector('.admin-shell');
    const sidebar = document.querySelector('#admin-navigation');
    const adminButton = document.querySelector('[data-admin-toggle]');
    const adminMain = document.querySelector('.admin-main');
    const narrow = matchMedia('(max-width: 980px)');
    const setAdminOpen = (open, returnFocus = true) => {
        shell?.classList.toggle('nav-open', open);
        adminButton?.setAttribute('aria-expanded', String(open));
        if (sidebar) sidebar.inert = narrow.matches && !open;
        if (adminMain) adminMain.inert = narrow.matches && open;
        if (open) sidebar.querySelector('a, button')?.focus();
        else if (returnFocus) adminButton?.focus();
    };
    if (sidebar) {
        setAdminOpen(false, false);
        listen(adminButton, 'click', () => setAdminOpen(true));
        document.querySelectorAll('[data-admin-close]').forEach(button => listen(button, 'click', () => setAdminOpen(false)));
        listen(narrow, 'change', () => setAdminOpen(false, false));
    }
    listen(document, 'keydown', event => {
        if (event.key === 'Escape') {
            if (menuButton?.getAttribute('aria-expanded') === 'true') {
                closePublic();
                menuButton.focus();
            }
            if (shell?.classList.contains('nav-open')) setAdminOpen(false);
        }
        if (event.key === 'Tab' && shell?.classList.contains('nav-open') && narrow.matches) {
            const items = [...sidebar.querySelectorAll('a[href], button, input, select, textarea')].filter(item => !item.disabled && item.getClientRects().length);
            const first = items[0], last = items.at(-1);
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });

    const categories = document.querySelectorAll('[data-home-category]');
    categories.forEach(button => listen(button, 'click', () => {
        const category = button.dataset.homeCategory;
        categories.forEach(item => item.setAttribute('aria-pressed', String(item === button)));
        document.querySelectorAll('[data-home-product]').forEach(product => {
            product.hidden = category !== 'all' && product.dataset.category !== category;
        });
    }));

    document.querySelectorAll('[data-theme-start-3d]').forEach(button => listen(button, 'click', async () => {
        const stage = button.closest('[data-scene-slot]');
        const host = stage.querySelector('[data-garden-host]');
        button.disabled = true;
        button.textContent = 'Đang mở khu vườn…';
        try {
            const { mountCafe } = await import(/* @vite-ignore */ new URL('cafe3d/scene.js', base).href);
            if (current !== generation) return;
            host.hidden = false;
            const dispose = mountCafe(host.querySelector('[data-cafe3d]'));
            retain(dispose);
            stage.querySelector('.french-garden-preview').hidden = true;
        } catch {
            host.hidden = true;
            button.disabled = false;
            button.textContent = 'Thử mở lại quán 3D';
        }
    }));

    if (document.querySelector('[data-hero-film]')) {
        try {
            const { mountHeroFilm } = await import(/* @vite-ignore */ new URL('assets/hero-film.js', base).href);
            if (current === generation) retain(mountHeroFilm());
        } catch { /* The poster remains available when the video module cannot load. */ }
    }
    if (document.querySelector('[data-foliage-toggle]')) {
        try {
            const { mountGardenWindow } = await import(/* @vite-ignore */ new URL('assets/garden-window.js', base).href);
            if (current === generation) retain(mountGardenWindow());
        } catch { document.querySelectorAll('[data-foliage-toggle]').forEach(button => button.hidden = true); }
    }
    for (const root of document.querySelectorAll('[data-public-seating]')) {
        try {
            const { mountPublicSeating } = await import(/* @vite-ignore */ new URL('assets/seating-ui.js', base).href);
            if (current !== generation) return;
            retain(await mountPublicSeating(root));
        } catch {
            const status = root.querySelector('[data-seat-status]');
            if (status) status.textContent = 'Chưa kết nối được tình trạng chỗ ngồi. Bạn có thể thử lại bằng cách tải lại trang.';
        }
    }
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeFrenchTheme, { once: true });
else initializeFrenchTheme();
document.addEventListener('livewire:navigating', teardown);
document.addEventListener('livewire:navigated', initializeFrenchTheme);
window.addEventListener('pagehide', event => { if (!event.persisted) teardown(); });
