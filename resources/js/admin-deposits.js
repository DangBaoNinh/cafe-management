const deposits = document.querySelector('[data-deposit-management]');

if (deposits) {
    const dialog = deposits.querySelector('[data-deposit-dialog]');
    const form = dialog.querySelector('[data-deposit-form]');
    const summary = dialog.querySelector('[data-deposit-summary]');

    deposits.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-deposit-open]');
        if (!trigger) return;

        form.reset();
        form.action = trigger.dataset.depositUrl;
        summary.textContent = `${trigger.dataset.depositNumber} · ${trigger.dataset.depositCustomer} · Bàn ${trigger.dataset.depositSeat}`;
        dialog.showModal();
        form.elements.reason.focus();
    });

    dialog.querySelectorAll('[data-deposit-close]').forEach((button) => {
        button.addEventListener('click', () => dialog.close());
    });

    dialog.addEventListener('click', (event) => {
        if (event.target !== dialog) return;
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right
            || event.clientY < bounds.top || event.clientY > bounds.bottom) {
            dialog.close();
        }
    });
}
