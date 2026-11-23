import { validateSnapshot, statusLabels } from './seating-data.js';

const vietnamTime = new Intl.DateTimeFormat('vi-VN', {
  timeZone: 'Asia/Ho_Chi_Minh', hour: '2-digit', minute: '2-digit',
});
const vietnamDate = new Intl.DateTimeFormat('vi-VN', {
  timeZone: 'Asia/Ho_Chi_Minh', day: '2-digit', month: '2-digit',
});
function formatAppointment(date) {
  const { day, month } = Object.fromEntries(
    vietnamDate.formatToParts(date).map(({ type, value }) => [type, value]),
  );
  return `${vietnamTime.format(date)} ngày ${day}/${month}`;
}

const escape = (value) =>
  String(value ?? '').replace(
    /[&<>"']/g,
    (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c],
  );
export async function mountPublicSeating(root) {
  if (root.dataset.mounted) return () => {};
  root.dataset.mounted = 'true';
  let options = {};
  try {
    options = JSON.parse(root.dataset.seatingConfig || '{}');
  } catch {
    /* Missing config is handled as a connection error. */
  }
  if (!options || typeof options !== 'object' || Array.isArray(options)) options = {};
  let reservationUrl = '';
  try {
    const url = new URL(options.reservationUrl || './reservation.html', location.href);
    if (url.origin === location.origin && ['http:', 'https:'].includes(url.protocol))
      reservationUrl = url.href;
  } catch {
    /* Omit invalid booking links. */
  }
  const demo = options.mode === 'demo';
  const viewport = root.querySelector('[data-seat-viewport]');
  const list = root.querySelector('[data-seat-list]');
  const detail = root.querySelector('[data-seat-detail]');
  const areas = root.querySelector('[data-seat-areas]');
  const status = root.querySelector('[data-seat-status]');
  const capacity = root.querySelector('[data-seat-capacity]');
  const refresh = root.querySelector('[data-seat-refresh]');
  let snapshot = null,
    area = '',
    selected = '',
    stale = false,
    disposed = false,
    busy = false;
  let renderer = null,
    request = null,
    scenePage = 0;
  const removers = [];
  function listen(target, event, handler) {
    target.addEventListener(event, handler);
    removers.push(() => target.removeEventListener(event, handler));
  }
  function currentTables() {
    if (!snapshot) return [];
    return snapshot.tables.filter((table) => table.area_id === area);
  }
  function showDetail(id) {
    selected = id;
    const table = snapshot?.tables.find((t) => t.id === id);
    if (!table) return;
    const available = !stale && table.status === 'available';
    const appointment = !stale && table.scheduled_at ? new Date(table.scheduled_at) : null;
    const guidance = {
      available: 'Chọn giờ đến và xác nhận đặt chỗ. Hệ thống sẽ giữ chỗ và cấp mã ngay nếu chỗ còn trống.',
      reserved: 'Chỗ này đã được giữ. Bạn có thể chọn chỗ trống khác hoặc cập nhật lại khi lượt giữ kết thúc.',
      occupied: 'Bạn có thể chọn chỗ trống khác.',
    };
    let bookingLink = '';
    if (reservationUrl) {
      const url = new URL(reservationUrl);
      if (available && !demo) {
        url.searchParams.set('seat_id', table.id);
        url.searchParams.set('booking_date', root.querySelector('[data-seat-date]').value);
        const partySize = Number(capacity.value);
        if (partySize > 1 && partySize <= table.capacity) url.searchParams.set('party_size', partySize);
      }
      bookingLink = `<a class="btn ${available ? '' : 'secondary'}" href="${escape(url.href)}">${available ? 'Đặt chỗ này' : 'Xem trang đặt chỗ'}</a>`;
    }
    detail.innerHTML = `
      <div class="seat-detail-title"><span class="eyebrow">Chỗ bạn chọn</span><h3>${escape(table.code)}</h3></div>
      <div class="detail-row"><span>Khu vực</span><strong>${escape(snapshot.areas.find((a) => a.id === table.area_id)?.name)}</strong></div>
      <div class="detail-row"><span>Sức chứa</span><strong>${table.capacity} người</strong></div>
      <div class="detail-row"><span>Hiện tại</span><span class="badge ${stale ? 'unknown' : table.status}">${stale ? 'Chưa xác minh' : statusLabels[table.status]}</span></div>
      ${appointment ? `<p class="form-help">Có lịch đặt lúc <time datetime="${appointment.toISOString()}">${formatAppointment(appointment)}</time></p>` : ''}
      <p class="form-help">${stale ? 'Thông tin đang gián đoạn. Vui lòng cập nhật lại hoặc liên hệ quán.' : (guidance[table.status] ?? 'Bạn có thể chọn chỗ trống khác.')}</p>
      ${bookingLink}`;
    root
      .querySelectorAll('[data-public-table]')
      .forEach((button) =>
        button.setAttribute('aria-pressed', String(button.dataset.publicTable === id)),
      );
  }
  function draw() {
    if (!snapshot) return;
    const count = snapshot.tables.filter((t) => t.status === 'available').length;
    root.querySelector('[data-seat-available]').textContent = stale ? '—' : count;
    root.querySelector('[data-seat-total]').textContent = snapshot.tables.length;
    root.querySelector('[data-seat-mode]').textContent = demo
      ? 'ĐANG XEM DỮ LIỆU MINH HỌA'
      : 'THÔNG TIN TỪ QUÁN';
    areas.innerHTML = snapshot.areas
      .map(
        (a) =>
          `<button class="pill ${a.id === area ? 'active' : ''}" data-public-area="${escape(a.id)}" aria-pressed="${a.id === area}">${escape(a.name)}</button>`,
      )
      .join('');
    const minCapacity = Number(capacity.value || 1);
    const shown = currentTables();
    list.innerHTML =
      shown
        .filter((t) => t.capacity >= minCapacity)
        .map(
          (table) =>
            `<button class="seat-row ${selected === table.id ? 'selected' : ''}" data-public-table="${escape(table.id)}" aria-pressed="${selected === table.id}"><span class="seat-row-code">${escape(table.code)}${table.scheduled_at ? `<small style="display:block;font-size:11px">${escape(formatAppointment(new Date(table.scheduled_at)))}</small>` : ''}</span><span>${table.capacity} chỗ</span><span class="badge ${stale ? 'unknown' : table.status}">${stale ? 'Chưa xác minh' : statusLabels[table.status]}</span></button>`,
        )
        .join('') || '<p class="empty">Khu vực này chưa có bàn phù hợp số người bạn chọn.</p>';
    const pageCount = Math.max(1, Math.ceil(shown.length / 6));
    scenePage = Math.min(scenePage, pageCount - 1);
    const pages = root.querySelector('[data-seat-pages]');
    if (pages) {
      pages.hidden = pageCount <= 1;
      pages.innerHTML = Array.from(
        { length: pageCount },
        (_, i) =>
          `<button class="pill ${scenePage === i ? 'active' : ''}" data-scene-page="${i}" aria-pressed="${scenePage === i}">Nhóm bàn ${i + 1}</button>`,
      ).join('');
    }
    renderer?.update(
      shown
        .slice(scenePage * 6, scenePage * 6 + 6)
        .map((t) => (stale ? { ...t, status: 'unknown', seated_count: 0 } : t)),
      snapshot.areas.find((a) => a.id === area)?.name || '',
    );
    if (selected && shown.some((t) => t.id === selected)) showDetail(selected);
    else {
      selected = '';
      detail.innerHTML =
        '<span class="seat-detail-flower" aria-hidden="true">✳</span><h3>Chọn một góc<br>cho cuộc hẹn.</h3><p>Chạm mã bàn trong mô hình hoặc danh sách để xem thông tin.</p>';
    }
  }
  function markStale(message) {
    stale = true;
    status.textContent = message;
    root.classList.add('seating-stale');
    draw();
  }
  listen(root.querySelector('[data-seat-date]'), 'change', () => { selected = ''; document.querySelectorAll('input[name=pickup_date]').forEach(input => input.value = root.querySelector('[data-seat-date]').value); markStale('Đang tải lịch theo ngày…'); load(); });
  async function load() {
    if (busy || disposed) return;
    const requestedDay = root.querySelector('[data-seat-date]')?.value;
    busy = true;
    refresh.disabled = true;
    try {
      let data;
      if (demo) {
        const { getDemoSeating } = await import('./seating-demo.js');
        data = getDemoSeating();
      } else {
        const url = new URL(options.endpoint, location.href);
        const bookingDay = root.querySelector('[data-seat-date]')?.value;
        if (bookingDay) url.searchParams.set('date', bookingDay);
        if (!options.endpoint || url.origin !== location.origin)
          throw new Error('Configure a same-origin endpoint');
        request = new AbortController();
        const timeout = setTimeout(() => request.abort(), 12000);
        try {
          const response = await fetch(url, {
            signal: request.signal,
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
          });
          if (!response.ok) throw new Error('Unavailable');
          data = await response.json();
        } finally {
          clearTimeout(timeout);
        }
      }
      const validated = validateSnapshot(data);
      if (disposed) return;
      snapshot = validated;
      stale = false;
      root.classList.remove('seating-stale');
      if (!snapshot.areas.some((a) => a.id === area)) area = snapshot.areas[0]?.id || '';
      const time = new Date(snapshot.observed_at).toLocaleTimeString('vi-VN', {
        hour: '2-digit',
        minute: '2-digit',
      });
      status.textContent = `${demo ? 'Bản minh họa' : 'Đã cập nhật'} lúc ${time}. Trạng thái có thể thay đổi trước khi bạn đến.`;
      draw();
    } catch {
      if (!disposed)
        markStale('Chưa lấy được tình trạng bàn. Vui lòng thử cập nhật lại hoặc liên hệ quán.');
    } finally {
      busy = false;
      request = null;
      if (!disposed) {
        refresh.disabled = false;
        if (requestedDay !== root.querySelector('[data-seat-date]')?.value) load();
      }
    }
  }
  listen(root, 'click', (event) => {
    const areaButton = event.target.closest('[data-public-area]');
    if (areaButton) {
      area = areaButton.dataset.publicArea;
      selected = '';
      scenePage = 0;
      draw();
    }
    const tableButton = event.target.closest('[data-public-table]');
    if (tableButton) {
      const index = currentTables().findIndex((t) => t.id === tableButton.dataset.publicTable);
      if (index >= 0 && Math.floor(index / 6) !== scenePage) {
        scenePage = Math.floor(index / 6);
        draw();
      }
      showDetail(tableButton.dataset.publicTable);
    }
    const pageButton = event.target.closest('[data-scene-page]');
    if (pageButton) {
      scenePage = Number(pageButton.dataset.scenePage);
      draw();
    }
  });
  listen(capacity, 'change', draw);
  listen(refresh, 'click', load);
  listen(root.querySelector('[data-seat-reset]'), 'click', () => renderer?.reset());
  listen(document, 'visibilitychange', () => {
    if (!document.hidden && !demo) load();
  });
  if (demo) {
    listen(window, 'storage', load);
    listen(window, 'toan-demo-seating-change', load);
  }
  try {
    const { mountSeatingScene } = await import('../cafe3d/seating-scene.js');
    if (!disposed) renderer = mountSeatingScene(viewport, showDetail);
  } catch {
    viewport.innerHTML =
      '<p class="empty">Thiết bị chưa mở được 3D. Bạn vẫn xem được tình trạng qua danh sách bàn.</p>';
  }
  await load();
  const poll = setInterval(() => {
    if (!document.hidden && !demo) load();
  }, 30000);
  const staleCheck = setInterval(() => {
    if (snapshot && !demo && !stale && Date.now() - Date.parse(snapshot.observed_at) > 90000)
      markStale('Thông tin đã quá 90 giây. Đang chờ cập nhật lại từ quán.');
  }, 1000);
  return () => {
    disposed = true;
    request?.abort();
    clearInterval(poll);
    clearInterval(staleCheck);
    removers.forEach((remove) => remove());
    renderer?.dispose();
    delete root.dataset.mounted;
  };
}
