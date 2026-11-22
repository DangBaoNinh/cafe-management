const money = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' });
const maxLines = 12;

for (const form of document.querySelectorAll('[data-table-order]')) {
    const container = form.querySelector('[data-order-lines]');
    const template = form.querySelector('[data-order-line-template]');
    const addButton = form.querySelector('[data-add-order-line]');
    const options = new Map([...form.querySelectorAll('[data-options-for]')].map((option) => [option.dataset.optionsFor, option]));
    const field = (row, name) => row.querySelector('[data-field="' + name + '"]');

    function renumber() {
        const rows = [...container.querySelectorAll('[data-order-line]')];
        rows.forEach((row, index) => {
            row.querySelector('[data-order-line-label]').textContent = 'Món ' + (index + 1);
            row.querySelector('[data-remove-order-line]').hidden = rows.length <= 1;
            for (const input of row.querySelectorAll('[data-field]')) {
                const name = input.dataset.field;
                const suffix = name === 'topping_ids' ? '[]' : '';
                input.name = 'lines[' + index + '][' + name + ']' + suffix;
            }
        });
        addButton.disabled = rows.length >= maxLines;
    }

    function showPrice(row) {
        const size = field(row, 'size_id')?.selectedOptions[0];
        const caption = row.querySelector('[data-line-price]');
        if (!size) {
            caption.textContent = '';
            return;
        }
        const toppings = [...row.querySelectorAll('[data-field="topping_ids"]:checked')];
        const unit = Number(size.dataset.price) + toppings.reduce((sum, topping) => sum + Number(topping.dataset.price), 0);
        const quantity = Number(field(row, 'quantity').value);
        caption.textContent = 'Tạm tính ' + money.format(unit * quantity);
    }

    function showOptions(row, selection = {}) {
        const product = field(row, 'product_id');
        const panel = row.querySelector('[data-product-options]');
        const configuration = options.get(product.value);
        panel.replaceChildren(...(configuration ? [configuration.content.cloneNode(true)] : []));

        for (const key of ['size_id', 'sugar_level', 'ice_level']) {
            const select = field(row, key);
            if (select && [...select.options].some((option) => option.value === String(selection[key]))) {
                select.value = String(selection[key]);
            }
        }
        if (Array.isArray(selection.topping_ids)) {
            const selected = new Set(selection.topping_ids.map(String));
            for (const topping of row.querySelectorAll('[data-field="topping_ids"]')) {
                topping.checked = selected.has(topping.value);
            }
        }
        renumber();
        showPrice(row);
    }

    function addLine(selection = {}, focus = false) {
        const row = template.content.firstElementChild.cloneNode(true);
        const product = field(row, 'product_id');
        product.value = String(selection.product_id ?? '');
        field(row, 'quantity').value = selection.quantity ?? 1;
        field(row, 'notes').value = selection.notes ?? '';
        container.append(row);
        showOptions(row, selection);
        if (focus) product.focus();
    }

    container.addEventListener('change', (event) => {
        const row = event.target.closest('[data-order-line]');
        if (event.target.dataset.field === 'product_id') {
            showOptions(row);
        } else {
            showPrice(row);
        }
    });
    container.addEventListener('input', (event) => {
        if (event.target.dataset.field === 'quantity') showPrice(event.target.closest('[data-order-line]'));
    });
    container.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-order-line]');
        if (!removeButton || container.childElementCount <= 1) return;
        removeButton.closest('[data-order-line]').remove();
        renumber();
        addButton.focus();
    });
    addButton.addEventListener('click', () => addLine({}, true));

    const previous = JSON.parse(container.dataset.previousLines || '[]');
    for (const line of Array.isArray(previous) && previous.length ? previous.slice(0, maxLines) : [{}]) {
        addLine(line && typeof line === 'object' ? line : {});
    }
}
