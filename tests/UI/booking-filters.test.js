import test from 'node:test';
import assert from 'node:assert/strict';
import {
    readBookingFilters,
    toBookingQuery,
    withDetail,
    withoutDetail,
} from '../../resources/js/booking-filters.js';

test('filter query contains only known scalar parameters', () => {
    assert.deepEqual(readBookingFilters({ search: 'BK', status: ['pending'], detail: '7', page: '2' }), { search: 'BK' });
});

test('filter defaults do not fill a partly specified date period', () => {
    assert.deepEqual(
        readBookingFilters({ from: '2026-10-05' }, { from: '2026-10-01', to: '2026-10-31', status: 'pending' }),
        { status: 'pending', from: '2026-10-05' },
    );
    assert.deepEqual(
        readBookingFilters({}, { from: '2026-10-01', to: '2026-10-31' }),
        { from: '2026-10-01', to: '2026-10-31' },
    );
});

test('detail query operations preserve applied filters without forwarding detail', () => {
    const query = withDetail({ status: 'pending', from: '2026-10-01', page: '3' }, 7);

    assert.deepEqual(query, { status: 'pending', from: '2026-10-01', detail: '7' });
    assert.deepEqual(withoutDetail(query), { status: 'pending', from: '2026-10-01' });
    assert.deepEqual(toBookingQuery({ search: '', status: 'pending', detail: '7', page: '2' }), { status: 'pending' });
});
