<template>
    <div>
        <header class="fleet-page-heading mb-4">
            <div>
                <p class="fleet-muted mb-1">Pemesanan kendaraan</p>
                <h1 class="mb-1">Laporan pemesanan</h1>
                <p class="fleet-muted mb-0">Periode berdasarkan tanggal pembuatan booking (WIB).</p>
            </div>
        </header>

        <BookingFilters
            :value="appliedFilters"
            :options="filterOptions"
            :options-loading="optionsLoading"
            :options-error="optionsError"
            :loading="loading"
            @apply="applyFilters"
            @reset="resetFilters"
            @retry-options="loadFilterOptions"
        />

        <div class="d-flex flex-wrap justify-content-end gap-2 mb-3">
            <button v-if="applied && !error" class="btn btn-outline-primary min-action" type="button" :disabled="loading || exporting" @click="exportReport">
                {{ exporting ? 'Menyiapkan Excel…' : 'Ekspor Excel' }}
            </button>
        </div>

        <p v-if="exportError" class="alert alert-danger" role="alert">{{ exportError }}</p>
        <div v-if="error" class="alert alert-danger" role="alert">
            {{ error }} <button class="btn btn-sm btn-outline-danger min-action ms-2" type="button" @click="load">Coba lagi</button>
        </div>
        <p v-else-if="loading" role="status">Memuat laporan…</p>
        <section v-else-if="!bookings.length" class="card fleet-card">
            <div class="card-body py-5 text-center">
                <h3 class="h5">{{ hasCustomFilters ? 'Tidak ada hasil sesuai filter' : 'Tidak ada pemesanan pada periode ini' }}</h3>
                <p class="fleet-muted mb-3">{{ hasCustomFilters ? 'Ubah filter atau kembali ke rentang bulan ini.' : 'Pilih rentang tanggal lain atau buat pemesanan.' }}</p>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <button v-if="hasCustomFilters" class="btn btn-outline-primary min-action" type="button" @click="resetFilters">Reset filter</button>
                    <router-link class="btn btn-outline-primary min-action" to="/bookings/create">Buat pemesanan</router-link>
                </div>
            </div>
        </section>
        <section v-else class="card fleet-card">
            <div class="table-responsive d-none d-lg-block">
                <table class="table align-middle mb-0">
                    <thead><tr><th scope="col">Kode</th><th scope="col">Pemakai</th><th scope="col">Kendaraan</th><th scope="col">Driver</th><th scope="col">Tujuan</th><th scope="col">Jadwal (WIB)</th><th scope="col">Dibuat (WIB)</th><th scope="col">Approval</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                        <tr v-for="booking in bookings" :key="booking.id">
                            <td><router-link :to="{ path: '/reports', query: withDetail(route.query, booking.id) }" @click="rememberDetailTrigger">{{ booking.code }}</router-link></td>
                            <td>{{ booking.requester_name }}</td>
                            <td>{{ booking.vehicle?.plate_number }}</td>
                            <td>{{ booking.driver?.name }}</td>
                            <td>{{ booking.destination }}</td>
                            <td>{{ formatSchedule(booking) }}</td>
                            <td>{{ formatTime(booking.created_at) }}</td>
                            <td>{{ approvalSummary(booking) }}</td>
                            <td><span class="fleet-status" :class="`fleet-status--${booking.status}`">{{ statusLabel(booking.status) }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="d-lg-none p-3">
                <article v-for="booking in bookings" :key="booking.id" class="report-mobile-row py-3">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <router-link class="fw-semibold" :to="{ path: '/reports', query: withDetail(route.query, booking.id) }" @click="rememberDetailTrigger">{{ booking.code }}</router-link>
                        <span class="fleet-status" :class="`fleet-status--${booking.status}`">{{ statusLabel(booking.status) }}</span>
                    </div>
                    <p class="mb-1">{{ booking.requester_name }} · {{ booking.vehicle?.plate_number }} · {{ booking.driver?.name }}</p>
                    <p class="fleet-muted small mb-1">{{ booking.destination }}</p>
                    <p class="fleet-muted small mb-1">{{ formatSchedule(booking) }} WIB</p>
                    <p class="fleet-muted small mb-0">Dibuat {{ formatTime(booking.created_at) }} WIB · {{ approvalSummary(booking) }}</p>
                </article>
            </div>
        </section>

        <BookingDetail v-if="detailId" :id="detailId" :return-focus="detailTrigger" @dismiss="closeDetail" @changed="load" />
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../bootstrap';
import BookingFilters from '../components/BookingFilters.vue';
import BookingDetail from './BookingDetail.vue';
import { readBookingFilters, toBookingQuery, withDetail, withoutDetail } from '../booking-filters';
import { notice } from '../alerts';

function wibDate(value) {
    const parts = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta', year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(value);
    const date = Object.fromEntries(parts.map((part) => [part.type, part.value]));
    return `${date.year}-${date.month}-${date.day}`;
}

const today = wibDate(new Date());
const defaults = { from: `${today.slice(0, 7)}-01`, to: today };
const route = useRoute();
const router = useRouter();
const applied = ref(null);
const bookings = ref([]);
const loading = ref(false);
const error = ref('');
const exporting = ref(false);
const exportError = ref('');
const filterOptions = ref({ vehicles: [], drivers: [], approvers: [] });
const optionsLoading = ref(true);
const optionsError = ref('');
const appliedFilters = computed(() => readBookingFilters(route.query, defaults));
const detailId = computed(() => typeof route.query.detail === 'string' ? route.query.detail : '');
const detailTrigger = ref(null);
const hasCustomFilters = computed(() => ['search', 'status', 'vehicle_id', 'driver_id', 'approver_id']
    .some((key) => Boolean(appliedFilters.value[key]))
    || appliedFilters.value.from !== defaults.from
    || appliedFilters.value.to !== defaults.to);
let loadRequest = 0;

async function load() {
    error.value = '';
    loading.value = true;
    applied.value = null;
    bookings.value = [];
    const request = ++loadRequest;
    const filters = appliedFilters.value;
    try {
        const response = await api.get('/api/bookings', { params: filters });
        if (request === loadRequest) {
            bookings.value = response.data.data;
            applied.value = filters;
        }
    } catch (requestError) {
        if (request === loadRequest) {
            error.value = requestError.response?.status === 422
                ? 'Filter tanggal tidak valid. Isi rentang tanggal yang lengkap dan berurutan.'
                : 'Laporan tidak dapat dimuat.';
            void notice(error.value, 'Laporan tidak tersedia');
        }
    } finally {
        if (request === loadRequest) loading.value = false;
    }
}

async function loadFilterOptions() {
    optionsLoading.value = true;
    optionsError.value = '';
    try {
        filterOptions.value = await api.get('/api/booking-filter-options').then(({ data }) => data);
    } catch {
        optionsError.value = 'Pilihan kendaraan, driver, dan penyetuju tidak dapat dimuat.';
        void notice(optionsError.value, 'Pilihan filter tidak tersedia');
    } finally {
        optionsLoading.value = false;
    }
}

async function applyFilters(filters) {
    const query = toBookingQuery(filters);
    if (!route.query.detail && JSON.stringify(route.query) === JSON.stringify(query)) {
        await load();
        return;
    }
    await router.push({ query });
}

async function resetFilters() {
    const query = { ...defaults };
    if (!route.query.detail && JSON.stringify(route.query) === JSON.stringify(query)) {
        await load();
        return;
    }
    await router.push({ query });
}

async function closeDetail() {
    await router.replace({ path: route.path, query: withoutDetail(route.query) });
}

function rememberDetailTrigger(event) {
    detailTrigger.value = event.currentTarget;
}

async function exportReport() {
    if (!applied.value || loading.value || exporting.value) return;
    exporting.value = true;
    exportError.value = '';
    try {
        const { data } = await api.get('/api/reports/bookings/export', {
            params: applied.value,
            responseType: 'blob',
        });
        const url = URL.createObjectURL(data);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'booking-report.xlsx';
        document.body.append(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 0);
    } catch {
        exportError.value = 'File Excel tidak dapat diunduh. Coba lagi.';
        void notice(exportError.value, 'Ekspor gagal');
    } finally {
        exporting.value = false;
    }
}

function formatTime(value) {
    return new Intl.DateTimeFormat('id-ID', { timeZone: 'Asia/Jakarta', dateStyle: 'medium', timeStyle: 'short', hour12: false }).format(new Date(value));
}

function formatSchedule(booking) {
    return `${formatTime(booking.start_at)} sampai ${formatTime(booking.end_at)}`;
}

function statusLabel(status) {
    return ({ pending: 'Menunggu', approved: 'Disetujui', rejected: 'Ditolak', completed: 'Selesai' })[status] ?? status;
}

function approvalSummary(booking) {
    return booking.approvals.map((approval) => `L${approval.level} ${statusLabel(approval.status)}`).join(' · ');
}

watch(() => JSON.stringify(appliedFilters.value), load, { immediate: true });
watch(() => route.query.detail, (id) => { if (!id) detailTrigger.value = null; });
onMounted(() => {
    loadFilterOptions();
    if (!Object.hasOwn(route.query, 'from') && !Object.hasOwn(route.query, 'to')) {
        router.replace({ query: { ...route.query, ...defaults } });
    }
});
</script>

<style scoped>
.report-mobile-row + .report-mobile-row { border-top: 1px solid var(--fleet-line); }
</style>
