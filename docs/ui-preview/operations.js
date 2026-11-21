const periods = [
    { day: 15, revenue: 1260000, orders: 18, discount: 95000, delivery: 108000, completed: 18, active: 5, closed: 2 },
    { day: 16, revenue: 1710000, orders: 24, discount: 135000, delivery: 138000, completed: 23, active: 4, closed: 1 },
    { day: 17, revenue: 1480000, orders: 22, discount: 110000, delivery: 126000, completed: 22, active: 3, closed: 2 },
    { day: 18, revenue: 2240000, orders: 31, discount: 165000, delivery: 164000, completed: 30, active: 6, closed: 3 },
    { day: 19, revenue: 1920000, orders: 27, discount: 140000, delivery: 151000, completed: 26, active: 4, closed: 2 },
    { day: 20, revenue: 2590000, orders: 37, discount: 205000, delivery: 205000, completed: 36, active: 6, closed: 2 },
    { day: 21, revenue: 3080000, orders: 43, discount: 245000, delivery: 242000, completed: 40, active: 7, closed: 3 },
];

const statuses = {
    pending: ['Chờ xác nhận', 'amber'],
    confirmed: ['Đã xác nhận', 'rose'],
    preparing: ['Đang pha chế', 'rose'],
    ready: ['Đã chuẩn bị xong', 'green'],
    delivery: ['Đang giao', 'blue'],
    completed: ['Đã hoàn tất', 'green'],
    cancelled: ['Đã hủy', 'neutral'],
};

const fulfillmentLabels = { pickup: 'Nhận tại quán', delivery: 'Giao tận nơi' };
const drinks = {
    coffee: ['Cà phê sữa đá', 39000, 'Vừa · Ít đá'],
    bacXiu: ['Bạc xỉu', 45000, 'Vừa · Ít ngọt'],
    matcha: ['Matcha latte', 55000, 'Vừa · Đá tiêu chuẩn'],
};

const orders = [
    { id: 'CS-1048', time: '09:12', status: 'pending', fulfillment: 'pickup', payment: 'Chờ đối chiếu', items: [['coffee', 2], ['matcha', 1]] },
    { id: 'CS-1047', time: '09:08', status: 'pending', fulfillment: 'delivery', payment: 'Thu khi giao', delivery: 18000, items: [['bacXiu', 2]] },
    { id: 'CS-1046', time: '09:03', status: 'pending', fulfillment: 'pickup', payment: 'Đã thanh toán', items: [['matcha', 2], ['bacXiu', 1]] },
    { id: 'CS-1045', time: '08:58', status: 'preparing', fulfillment: 'pickup', payment: 'Đã thanh toán', items: [['coffee', 2]] },
    { id: 'CS-1044', time: '08:53', status: 'confirmed', fulfillment: 'delivery', payment: 'Thu khi giao', delivery: 16000, items: [['coffee', 1], ['matcha', 2]] },
    { id: 'CS-1043', time: '08:38', status: 'ready', fulfillment: 'pickup', payment: 'Đã thanh toán', items: [['bacXiu', 1]] },
    { id: 'CS-1042', time: '08:32', status: 'delivery', fulfillment: 'delivery', payment: 'Thu khi giao', delivery: 16000, items: [['coffee', 1], ['bacXiu', 1]] },
    { id: 'CS-1041', time: '08:20', status: 'completed', fulfillment: 'pickup', payment: 'Đã thanh toán', items: [['matcha', 2]] },
    { id: 'CS-1040', time: '08:15', status: 'cancelled', fulfillment: 'pickup', payment: 'Chờ hoàn tiền', items: [['coffee', 1]] },
];

const adminPages = {
    overview: ['Tổng quan quản trị', 'Doanh thu và tình trạng đơn hàng của quán.'],
    orders: ['Quản lý đơn hàng', 'Tra cứu mã đơn, hình thức nhận và tình trạng thanh toán.'],
};
const staffPages = {
    intake: { title: 'Đơn chờ xác nhận', description: 'Kiểm tra món và thông tin thanh toán trước khi nhận đơn.', includes: (order) => order.status === 'pending' },
    preparation: { title: 'Pha chế', description: 'Xem số lượng và tùy chọn đồ uống của từng đơn.', includes: (order) => ['confirmed', 'preparing'].includes(order.status) },
    handoff: { title: 'Bàn giao', description: 'Kiểm tra đơn đã chuẩn bị xong và đơn đang giao.', includes: (order) => ['ready', 'delivery'].includes(order.status) },
    payments: { title: 'Đối chiếu thanh toán', description: 'Xem khoản chuyển khoản cần đối chiếu và khoản chờ hoàn tiền.', includes: (order) => ['Chờ đối chiếu', 'Chờ hoàn tiền'].includes(order.payment) },
};

const number = new Intl.NumberFormat('vi-VN');
const money = (value) => `${number.format(value)} ₫`;
const get = (id) => document.getElementById(id);
const roleLinks = [...document.querySelectorAll('[data-role]')];
const orderSearch = get('order-search');
const fulfillment = get('fulfillment');
const orderList = get('order-list');
const detail = get('order-detail');

let currentRole;
let currentPage;

function itemTotal(order) {
    return order.items.reduce((sum, [drink, count]) => sum + drinks[drink][1] * count, 0);
}

function orderTotal(order) {
    return itemTotal(order) + (order.delivery ?? 0);
}

function renderOverview() {
    const days = periods.slice(-Number(get('period').value));
    const total = (key) => days.reduce((sum, day) => sum + day[key], 0);
    const metrics = [
        ['Tổng thu', money(total('revenue'))],
        ['Đơn hoàn tất đã thanh toán', number.format(total('orders'))],
        ['Giảm giá', money(total('discount'))],
        ['Phí giao', money(total('delivery'))],
    ];

    get('metrics').innerHTML = metrics.map(([label, value], index) => `
        <div class="metric ${index === 0 ? 'featured' : ''}">
            <dt>${label}</dt><dd>${value}</dd>
        </div>`).join('');

    const scale = [4, 3, 2, 1, 0].map((mark) => `<span>${mark ? `${mark} tr` : '0'}</span>`).join('');
    const bars = days.map(({ day, revenue }) => `
        <li title="${day}/11 · ${money(revenue)}">
            <span class="bar" aria-hidden="true" style="--height: ${revenue / 40000}%"></span>
            <span class="chart-day">${day}/11</span>
            <span class="visually-hidden">${money(revenue)}</span>
        </li>`).join('');
    get('chart').innerHTML = `<div class="chart-scale" aria-hidden="true">${scale}</div><ul class="chart-bars" aria-label="Doanh thu từng ngày">${bars}</ul>`;
    get('chart-period').textContent = `${days[0].day}/11 – ${days.at(-1).day}/11/2026 · Đơn đã thanh toán theo ngày hoàn tất`;

    // Đây là số đơn đặt trong kỳ, tách khỏi số đơn hoàn tất trong kỳ.
    const placed = [
        ['Đã hoàn tất', total('completed'), 'green'],
        ['Đang xử lý', total('active'), 'rose'],
        ['Đã hủy hoặc từ chối', total('closed'), 'neutral'],
    ];
    const orderCount = placed.reduce((sum, [, count]) => sum + count, 0);
    get('status-report').innerHTML = placed.map(([label, count, tone]) => `
        <div class="status-row" data-tone="${tone}">
            <dt>${label}</dt><dd>${number.format(count)}</dd>
            <div class="status-track" aria-hidden="true"><span style="--share: ${count / orderCount * 100}%"></span></div>
        </div>`).join('');
}

function renderNavigation(isStaff, page) {
    const links = isStaff
        ? Object.entries(staffPages).map(([key, view]) => [key, view.title, orders.filter(view.includes).length])
        : Object.entries(adminPages).map(([key, [title]]) => [key, title]);

    get('workspace-nav').innerHTML = links.map(([key, label, count]) => `
        <a href="#${currentRole}/${key}" ${page === key ? 'aria-current="page"' : ''}>
            ${label}
            ${count === undefined ? '' : `<span class="nav-count" aria-label="${count} đơn">${count}</span>`}
        </a>`).join('');
}

function renderPage() {
    const [role, page] = window.location.hash.slice(1).split('/');
    const isStaff = role === 'staff';
    const nextRole = isStaff ? 'staff' : 'admin';
    const nextPage = isStaff
        ? (Object.hasOwn(staffPages, page) ? page : 'intake')
        : (Object.hasOwn(adminPages, page) ? page : 'overview');
    const hasNavigated = currentRole && (nextRole !== currentRole || nextPage !== currentPage);
    currentRole = nextRole;
    currentPage = nextPage;
    const isOverview = !isStaff && currentPage === 'overview';

    for (const link of roleLinks) {
        if (link.dataset.role === currentRole) link.setAttribute('aria-current', 'true');
        else link.removeAttribute('aria-current');
    }
    get('brand-link').href = `#${currentRole}`;
    get('brand-role').textContent = isStaff ? 'Vận hành' : 'Quản trị';
    renderNavigation(isStaff, currentPage);

    const staffView = staffPages[currentPage];
    const [title, description] = isStaff ? [staffView.title, staffView.description] : adminPages[currentPage];
    get('page-title').textContent = title;
    get('page-description').textContent = description;
    document.title = `Coffee Shop | ${title}`;
    get('overview').hidden = !isOverview;
    get('period-control').hidden = !isOverview;
    get('orders').hidden = isOverview;
    if (hasNavigated) {
        if (detail.open) detail.close();
        orderSearch.value = '';
        fulfillment.value = 'all';
        get('page-title').focus({ preventScroll: true });
        window.scrollTo(0, 0);
    }
    if (isOverview) renderOverview();
    else renderOrders();
}

function pill(order) {
    const [label, tone] = statuses[order.status];
    return `<span class="pill" data-tone="${tone}">${label}</span>`;
}

function detailButton(order) {
    return `<button type="button" class="detail-button" data-order="${order.id}" aria-haspopup="dialog" aria-label="Chi tiết đơn ${order.id}">Chi tiết <span aria-hidden="true">↗</span></button>`;
}

function orderFacts(order) {
    return `<dl class="order-meta">
        <div><dt>Cách nhận</dt><dd>${fulfillmentLabels[order.fulfillment]}</dd></div>
        <div><dt>Thanh toán</dt><dd>${order.payment}</dd></div>
    </dl>`;
}

function orderCards(rows) {
    return `<ul class="order-card-grid">${rows.map((order) => `
        <li class="order-card panel" data-tone="${statuses[order.status][1]}">
            <div class="order-top">
                <div><h3>${order.id}</h3><time datetime="2026-11-21T${order.time}:00+07:00">Đặt lúc ${order.time}</time></div>
                ${pill(order)}
            </div>
            <p class="order-summary">${order.items.map(([drink, count]) => `${count} × ${drinks[drink][0]}`).join(' · ')}</p>
            ${orderFacts(order)}
            <div class="order-bottom"><strong class="amount">${money(orderTotal(order))}</strong>${detailButton(order)}</div>
        </li>`).join('')}</ul>`;
}

function orderTable(rows) {
    return `<div class="order-table panel" role="region" aria-label="Bảng đơn hàng" tabindex="0">
        <table>
            <thead><tr><th scope="col">Mã đơn</th><th scope="col">Giờ đặt</th><th scope="col">Cách nhận</th><th scope="col">Trạng thái</th><th scope="col">Thanh toán</th><th scope="col">Tổng tiền</th><th scope="col"><span class="visually-hidden">Chi tiết</span></th></tr></thead>
            <tbody>${rows.map((order) => `<tr>
                <th scope="row">${order.id}</th><td>${order.time}</td><td>${fulfillmentLabels[order.fulfillment]}</td>
                <td>${pill(order)}</td><td>${order.payment}</td><td class="amount">${money(orderTotal(order))}</td>
                <td>${detailButton(order)}</td>
            </tr>`).join('')}</tbody>
        </table>
    </div>`;
}

function renderOrders() {
    const isStaff = currentRole === 'staff';
    const query = orderSearch.value.trim().toUpperCase();
    const rows = orders.filter((order) => (
        (!isStaff || staffPages[currentPage].includes(order))
        && order.id.includes(query)
        && (fulfillment.value === 'all' || fulfillment.value === order.fulfillment)
    ));

    get('orders-heading').textContent = `${rows.length} đơn hàng`;
    get('order-count').textContent = `${rows.length} kết quả`;
    get('empty-results').hidden = rows.length > 0;
    orderList.innerHTML = rows.length ? (isStaff ? orderCards(rows.toReversed()) : orderTable(rows)) : '';
}

orderList.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-order]');
    if (!trigger) return;
    const order = orders.find(({ id }) => id === trigger.dataset.order);
    get('detail-title').textContent = order.id;
    const items = order.items.map(([drink, count]) => {
        const [name, price, options] = drinks[drink];
        return `<li>${count} × ${name} · ${money(price * count)}<small>${options}</small></li>`;
    });
    get('detail-body').innerHTML = `
        ${pill(order)} ${orderFacts(order)}
        <h3>Món đã đặt</h3><ul class="drink-list">${items.join('')}</ul>
        ${order.delivery ? `<p>Phí giao ${money(order.delivery)}</p>` : ''}
        <p><strong>Tổng tiền ${money(orderTotal(order))}</strong></p>`;
    detail.addEventListener('close', () => trigger.focus(), { once: true });
    detail.showModal();
});

get('period').addEventListener('change', renderOverview);
orderSearch.addEventListener('input', renderOrders);
fulfillment.addEventListener('change', renderOrders);
window.addEventListener('hashchange', renderPage);
renderPage();
