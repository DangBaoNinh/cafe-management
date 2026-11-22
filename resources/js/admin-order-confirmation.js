// Existing online order actions keep their backend transition checks; admin confirms before submitting.
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    const root = form.closest('[data-admin-order-actions]');
    if (!root) return;
    if (form.dataset.statusConfirmed === 'true') {
        delete form.dataset.statusConfirmed;
        return;
    }
    const dialog = root.querySelector('[data-admin-status-dialog]');
    if (!dialog) return;
    event.preventDefault();
    const target = form.elements.target_status?.value ?? form.elements.decision?.value ?? 'cancelled';
    const labels = { confirmed: 'Đã xác nhận', preparing: 'Đang pha chế', ready: 'Đã chuẩn bị xong', out_for_delivery: 'Đang giao', completed: 'Hoàn tất', cancelled: 'Đã hủy' };
    const reason = form.elements.reason?.value?.trim();
    root.querySelector('[data-admin-status-summary]').textContent = `Đơn ${root.dataset.orderNumber}: ${root.dataset.currentStatus} → ${labels[target] ?? target}.${reason ? ` Lý do: ${reason}` : ''}`;
    const approve = root.querySelector('[data-admin-status-approve]');
    const cancel = root.querySelector('[data-admin-status-cancel]');
    const submitter = event.submitter;
    approve.onclick = () => {
        dialog.close();
        form.dataset.statusConfirmed = 'true';
        if (submitter) form.requestSubmit(submitter);
        else form.requestSubmit();
        delete form.dataset.statusConfirmed;
    };
    cancel.onclick = () => dialog.close();
    dialog.showModal();
});
