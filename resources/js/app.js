const appearanceKey = 'flux.appearance';
const systemAppearance = window.matchMedia('(prefers-color-scheme: dark)');

function syncAppearance() {
    let storedAppearance;

    try {
        storedAppearance = window.localStorage.getItem(appearanceKey);
    } catch {
        // Fall back to the system appearance when storage is unavailable.
    }

    const appearance = ['light', 'dark'].includes(storedAppearance) ? storedAppearance : 'system';

    if (window.Flux && 'appearance' in window.Flux) {
        window.Flux.appearance = appearance;
    }

    document.documentElement.classList.toggle(
        'dark',
        appearance === 'dark' || (appearance === 'system' && systemAppearance.matches),
    );
}

// Flux handles system changes on pages with Alpine. The shared layout also serves pages without it.
systemAppearance.addEventListener('change', () => {
    if (!window.Flux || !('appearance' in window.Flux)) {
        syncAppearance();
    }
});

window.addEventListener('storage', (event) => {
    if (event.key === appearanceKey || event.key === null) {
        syncAppearance();
    }
});

window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        syncAppearance();
    }
});
