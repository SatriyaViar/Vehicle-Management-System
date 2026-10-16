const filterKeys = ['search', 'status', 'from', 'to', 'vehicle_id', 'driver_id', 'approver_id'];

function scalar(value) {
    return typeof value === 'string' || typeof value === 'number' ? String(value) : null;
}

export function readBookingFilters(query = {}, defaults = {}) {
    const filters = {};
    const hasDateQuery = Object.hasOwn(query, 'from') || Object.hasOwn(query, 'to');

    for (const key of filterKeys) {
        const value = Object.hasOwn(query, key) ? scalar(query[key]) : hasDateQuery && (key === 'from' || key === 'to') ? null : scalar(defaults[key]);
        if (value !== null && value !== '') filters[key] = value;
    }

    return filters;
}

export function toBookingQuery(filters = {}) {
    return Object.fromEntries(filterKeys
        .map((key) => [key, scalar(filters[key])])
        .filter(([, value]) => value !== null && value !== ''));
}

export function withDetail(query, id) {
    return { ...toBookingQuery(readBookingFilters(query)), detail: String(id) };
}

export function withoutDetail(query) {
    return toBookingQuery(readBookingFilters(query));
}
