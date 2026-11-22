// Mount one background film and release media/listeners when Livewire leaves the page.
export function mountHeroFilm(hero = document.querySelector('[data-film-hero]')) {
    const video = hero?.querySelector('[data-hero-film]');
    const toggle = hero?.querySelector('[data-film-toggle]');
    if (!video || !toggle || !video.dataset.src) return () => {};
    const source = new URL(video.dataset.src, location.href);
    if (source.origin !== location.origin) return () => {};

    const events = new AbortController();
    const listen = (target, event, callback) => target?.addEventListener(event, callback, { signal: events.signal });
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    const meta = hero.querySelector('[data-film-meta]');
    const originalMeta = meta?.textContent;
    let userPaused = reducedMotion.matches || Boolean(navigator.connection?.saveData);
    let visible = hero.getBoundingClientRect().bottom > 0 && hero.getBoundingClientRect().top < innerHeight;
    let disposed = false;
    let failed = false;
    let playback = 0;

    function syncButton() {
        toggle.hidden = failed;
        toggle.textContent = video.paused ? 'Phát video' : 'Tạm dừng video';
        toggle.setAttribute('aria-pressed', String(!video.paused));
    }
    async function syncPlayback() {
        const attempt = ++playback;
        if (disposed || failed || !visible || document.hidden || userPaused) {
            video.pause();
            syncButton();
            return;
        }
        try { await video.play(); }
        catch {
            if (!disposed && !failed && attempt === playback) {
                userPaused = true;
                syncButton();
            }
        }
    }
    listen(video, 'playing', () => {
        if (disposed || userPaused || !visible || document.hidden) { video.pause(); return; }
        video.hidden = false;
        hero.classList.add('has-film');
        syncButton();
    });
    listen(video, 'pause', syncButton);
    listen(video, 'loadedmetadata', () => {
        if (meta && Number.isFinite(video.duration)) meta.textContent = 'LE FILM DE TOÀN · ' + Math.round(video.duration) + ' GIÂY';
    });
    listen(video, 'error', () => {
        failed = true;
        video.pause();
        video.hidden = true;
        hero.classList.remove('has-film');
        if (meta) meta.textContent = originalMeta;
        syncButton();
    });
    listen(toggle, 'click', () => {
        userPaused = !video.paused;
        syncPlayback();
    });
    listen(document, 'visibilitychange', syncPlayback);
    listen(reducedMotion, 'change', () => {
        userPaused = reducedMotion.matches;
        syncPlayback();
    });
    const observer = new IntersectionObserver(entries => {
        visible = entries[0].isIntersecting;
        syncPlayback();
    }, { threshold: 0.03 });
    observer.observe(hero);
    video.muted = true;
    video.loop = true;
    video.playsInline = true;
    video.preload = userPaused ? 'metadata' : 'auto';
    video.src = source.href;
    video.load();
    syncButton();
    syncPlayback();

    return () => {
        disposed = true;
        playback++;
        events.abort();
        observer.disconnect();
        video.pause();
        video.removeAttribute('src');
        video.load();
        video.hidden = true;
        hero.classList.remove('has-film');
        if (meta) meta.textContent = originalMeta;
        toggle.hidden = true;
    };
}
