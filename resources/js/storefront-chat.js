function initializeChat() {
    const widget = document.querySelector('[data-storefront-chat]');
    if (!widget || widget.dataset.ready) return;
    widget.dataset.ready = 'true';

    const panel = widget.querySelector('section');
    const toggle = widget.querySelector('[data-chat-toggle]');
    const log = widget.querySelector('[role=log]');
    const status = widget.querySelector('[data-chat-status]');
    const form = widget.querySelector('form');
    const input = form.elements.message;
    const submit = form.querySelector('button');
    const template = widget.querySelector('[data-chat-product]');
    const history = [];
    const money = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' });

    function setOpen(open) {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Đóng trò chuyện' : 'Mở trò chuyện');
        (open ? input : toggle).focus();
    }

    function showStatus(message) {
        status.hidden = !message;
        status.textContent = message;
    }

    function appendMessage(text, role) {
        const item = document.createElement('p');
        item.className = 'terrasse-chat-message';
        item.dataset.role = role;
        item.textContent = text;
        log.append(item);
        log.scrollTop = log.scrollHeight;
    }

    async function post(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': form.elements._token.value,
            },
            body: JSON.stringify(body),
        });

        if (!response.ok) {
            const messages = {
                401: 'Bạn cần đăng nhập lại để tiếp tục.',
                404: 'Món này hiện không còn bán.',
                419: 'Phiên đã hết hạn. Hãy tải lại trang.',
                422: 'Thông tin vừa gửi chưa hợp lệ hoặc lựa chọn món đã thay đổi.',
                429: 'Bạn gửi hơi nhanh. Vui lòng thử lại sau một phút.',
            };
            throw new Error(messages[response.status] ?? 'Có lỗi khi xử lý yêu cầu. Vui lòng thử lại.');
        }

        return response.json();
    }

    function appendProduct(product) {
        const card = template.content.cloneNode(true);
        const link = card.querySelector('[data-product-link]');
        link.textContent = product.name;
        link.href = product.url;
        const select = card.querySelector('select');
        if (select) {
            for (const size of product.sizes) {
                select.add(new Option(`${size.label} · ${money.format(size.price_vnd)}`, size.id));
            }
            const add = card.querySelector('button');
            add.addEventListener('click', async () => {
                add.disabled = true;
                try {
                    const result = await post(widget.dataset.cartUrl, { product_id: product.id, size_id: select.value });
                    showStatus(result.message);
                } catch (error) {
                    showStatus(error.message);
                } finally {
                    add.disabled = false;
                }
            });
        } else {
            const lowestPrice = Math.min(...product.sizes.map(size => size.price_vnd));
            link.textContent = `${product.name} · từ ${money.format(lowestPrice)}`;
        }
        log.append(card);
        log.scrollTop = log.scrollHeight;
    }

    toggle.addEventListener('click', () => setOpen(panel.hidden));
    widget.querySelector('[data-chat-close]').addEventListener('click', () => setOpen(false));
    widget.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !panel.hidden) {
            event.preventDefault();
            setOpen(false);
        }
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const message = input.value.trim();
        if (!message || submit.disabled) return;

        const userTurn = { role: 'user', text: message };
        appendMessage(message, userTurn.role);
        input.value = '';
        submit.disabled = true;
        showStatus('Đang trả lời…');

        try {
            const result = await post(form.action, { message, history: history.slice(-8) });
            appendMessage(result.reply, 'model');
            result.products.forEach(appendProduct);
            if (result.available) {
                history.push(userTurn, { role: 'model', text: result.reply });
            }
            showStatus('');
        } catch (error) {
            showStatus(error.message);
            if (!input.value) input.value = message;
        } finally {
            submit.disabled = false;
        }
    });
}

initializeChat();
document.addEventListener('livewire:navigated', initializeChat);
