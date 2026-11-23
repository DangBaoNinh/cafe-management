import './storefront-chat';
import './french-theme';
import './table-order';
import './table-pos';
import './admin-order-confirmation';
import './admin-deposits';

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

const pickupCheckout = document.querySelector('[data-checkout-pickup]');

if (pickupCheckout && pickupCheckout.elements.pickup_mode) {
    const { fulfillment_type: fulfillment, pickup_mode: mode, pickup_date: date, pickup_clock: time } = pickupCheckout.elements;
    const options = pickupCheckout.querySelector('[data-pickup-mode]');
    const slot = pickupCheckout.querySelector('[data-pickup-slot]');

    const updatePickupFields = () => {
        const pickup = fulfillment.value === 'pickup';
        if (pickup) mode.value = 'scheduled';
        const scheduled = pickup;
        const cod = pickupCheckout.querySelector('select[name="method"] option[value="cod"]');
        if (cod) {
            cod.disabled = cod.hidden = pickup;
            if (pickup && pickupCheckout.elements.method.value === 'cod') pickupCheckout.elements.method.value = 'bank_transfer';
        }

        options.hidden = options.disabled = !pickup;
        slot.hidden = slot.disabled = !scheduled;
        date.required = time.required = scheduled;
    };

    fulfillment.addEventListener('change', updatePickupFields);
    mode.addEventListener('change', updatePickupFields);
    window.addEventListener('pageshow', updatePickupFields);
    updatePickupFields();
}

const expireCancellationButtons = () => {
    document.querySelectorAll('[data-cancel-until]').forEach(button => {
        if (Date.now() > Number(button.dataset.cancelUntil)) button.disabled = true;
    });
};
expireCancellationButtons();
setInterval(expireCancellationButtons, 1000);
