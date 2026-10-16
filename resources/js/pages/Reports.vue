<template>
    <div>
        <div class="mb-4">
            <p class="fleet-muted mb-1">Pemesanan kendaraan</p>
            <h2 class="h4 mb-1">Laporan pemesanan</h2>
            <p class="fleet-muted mb-0">Periode berdasarkan tanggal pembuatan booking (WIB).</p>
        </div>

        <form class="card fleet-card mb-3" @submit.prevent="applyFilters" novalidate>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="report-from">Dari tanggal</label>
                        <input id="report-from" v-model="filters.from" class="form-control" type="date" :max="filters.to || today" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="report-to">Sampai tanggal</label>
                        <input id="report-to" v-model="filters.to" class="form-control" type="date" :min="filters.from" :max="today" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="report-status">Status</label>
                        <select id="report-status" v-model="filters.status" class="form-select">
                            <option value="">Semua status</option>
                            <option value="pending">Menunggu</option>
                            <option value="approved">Disetujui</option>
                            <option value="rejected">Ditolak</option>
                            <option value="completed">Selesai</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex flex-wrap justify-content-end gap-2">
                        <button class="btn btn-primary min-action" type="submit" :disabled="loading">{{ loading ? 'Memuat…' : 'Terapkan filter' }}</button>
                        <a v-if="applied" class="btn btn-outline-primary min-action" :href="exportUrl">Ekspor Excel</a>
                    </div>
                </div>
            </div>
        </form>

        <p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p>
        <p v-else-if="loading" role="status">Memuat laporan…</p>
        <section v-else-if="!bookings.length" class="card fleet-card">
            <div class="card-body py-5 text-center">
                <h3 class="h5">Tidak ada pemesanan pada periode ini</h3>
                <p class="fleet-muted mb-3">Ubah rentang tanggal atau status untuk melihat hasil lain.</p>
                <router-link class="btn btn-outline-primary min-action" to="/bookings/create">Buat pemesanan</router-link>
            </div>
        </section>
        <section v-else class="card fleet-card">
            <div class="table-responsive d-none d-lg-block">
                <table class="table align-middle mb-0">
                    <thead><tr><th scope="col">Kode</th><th scope="col">Pemakai</th><th scope="col">Kendaraan</th><th scope="col">Driver</th><th scope="col">Tujuan</th><th scope="col">Jadwal (WIB)</th><th scope="col">Dibuat (WIB)</th><th scope="col">Approval</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                        <tr v-for="booking in bookings" :key="booking.id">
                            <td><router-link :to="`/bookings/${booking.id}`">{{ booking.code }}</router-link></td>
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
                        <router-link class="fw-semibold" :to="`/bookings/${booking.id}`">{{ booking.code }}</router-link>
                        <span class="fleet-status" :class="`fleet-status--${booking.status}`">{{ statusLabel(booking.status) }}</span>
                    </div>
                    <p class="mb-1">{{ booking.requester_name }} · {{ booking.vehicle?.plate_number }} · {{ booking.driver?.name }}</p>
                    <p class="fleet-muted small mb-1">{{ booking.destination }}</p>
                    <p class="fleet-muted small mb-1">{{ formatSchedule(booking) }} WIB</p>
                    <p class="fleet-muted small mb-0">Dibuat {{ formatTime(booking.created_at) }} WIB · {{ approvalSummary(booking) }}</p>
                </article>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import api from '../bootstrap';

function wibDate(value) {
    const parts = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta', year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(value);
    const date = Object.fromEntries(parts.map((part) => [part.type, part.value]));
    return `${date.year}-${date.month}-${date.day}`;
}

const today = wibDate(new Date());
const filters = reactive({ from: `${today.slice(0, 7)}-01`, to: today, status: '' });
const applied = ref(null);
const bookings = ref([]);
const loading = ref(false);
const error = ref('');
const exportUrl = computed(() => `/api/reports/bookings/export?${new URLSearchParams(applied.value ?? {}).toString()}`);

async function applyFilters() {
    error.value = '';
    if (!filters.from || !filters.to || filters.from > filters.to) {
        error.value = 'Pilih rentang tanggal yang valid.';
        return;
    }

    loading.value = true;
    applied.value = { ...filters };
    try {
        bookings.value = (await api.get('/api/bookings', { params: applied.value })).data.data;
    } catch {
        error.value = 'Laporan tidak dapat dimuat.';
        bookings.value = [];
    } finally {
        loading.value = false;
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

onMounted(applyFilters);
</script>

<style scoped>
.report-mobile-row + .report-mobile-row { border-top: 1px solid var(--fleet-line); }
</style>
