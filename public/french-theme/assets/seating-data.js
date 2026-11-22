export const statusLabels = {
  available: 'Còn chỗ',
  occupied: 'Đang sử dụng',
  reserved: 'Đã giữ chỗ',
  cleaning: 'Đang chuẩn bị',
  maintenance: 'Tạm ngừng',
  unknown: 'Chưa rõ',
  conflict: 'Chưa thể nhận khách',
};
export function validateSnapshot(raw) {
  if (!raw || !Array.isArray(raw.areas) || !Array.isArray(raw.tables))
    throw new Error('Invalid seating snapshot');
  const observed = Date.parse(raw.observed_at);
  if (
    !Number.isFinite(observed) ||
    observed > Date.now() + 60000 ||
    Date.now() - observed > 90000
  ) {
    throw new Error('Seating snapshot is stale');
  }
  if (raw.areas.length > 30 || raw.tables.length > 300)
    throw new Error('Snapshot exceeds supported limits');
  const areas = raw.areas.map((area) => {
    if (!area || area.id == null || !String(area.id).trim() || !area.name)
      throw new Error('Invalid area');
    return { id: String(area.id), name: String(area.name).slice(0, 80) };
  });
  const knownAreas = new Set(areas.map((area) => area.id));
  if (knownAreas.size !== areas.length) throw new Error('Duplicate area');
  const seen = new Set();
  const tables = raw.tables.slice(0, 300).map((table) => {
    if (!table || table.id == null || !table.code) throw new Error('Invalid table');
    const id = String(table.id);
    if (
      seen.has(id) ||
      !knownAreas.has(String(table.area_id)) ||
      !Number.isInteger(table.capacity) ||
      table.capacity < 1 ||
      table.capacity > 30
    )
      throw new Error('Invalid table record');
    seen.add(id);
    const status = Object.hasOwn(statusLabels, table.status) ? table.status : 'unknown';
    const appointment = typeof table.scheduled_at === 'string' ? Date.parse(table.scheduled_at) : NaN;
    return {
      id,
      code: String(table.code).slice(0, 30),
      area_id: String(table.area_id),
      capacity: table.capacity,
      status,
      scheduled_at: status === 'reserved' && appointment > observed ? table.scheduled_at : null,
      seated_count:
        status === 'occupied' && Number.isInteger(table.seated_count)
          ? Math.max(0, Math.min(table.capacity, table.seated_count))
          : 0,
    };
  });
  return { observed_at: raw.observed_at, areas, tables };
}
