<template>
    <div>
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
            <div>
                <p class="fleet-muted mb-1">Pemesanan kendaraan</p>
                <h2 class="h4 mb-0">Daftar pemesanan</h2>
            </div>
            <router-link v-if="auth.user?.role === 'admin'" class="btn btn-primary min-action" to="/bookings/create">Pesan kendaraan</router-link>
        </div>

        <p v-if="loading" role="status">Memuat pemesanan…</p>
        <div v-else-if="error" class="alert alert-danger" role="alert">
            {{ error }} <button class="btn btn-sm btn-outline-danger ms-2" type="button" @click="load">Coba lagi</button>
        </div>
        <section v-else-if="!bookings.length" class="card fleet-card">
            <div class="card-body py-5 text-center">
                <h3 class="h5">Belum ada pemesanan</h3>
                <p class="fleet-muted mb-0">Pemesanan baru akan muncul di sini.</p>
            </div>
        </section>
        <section v-else class="card fleet-card">
            <div class="table-responsive d-none d-md-block">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr><th scope="col">Kode</th><th scope="col">Pemakai</th><th scope="col">Kendaraan / driver</th><th scope="col">Jadwal (WIB)</th><th scope="col">Persetujuan</th><th scope="col">Status</th><th scope="col">Aksi</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="booking in bookings" :key="booking.id">
                            <td class="fw-semibold">{{ booking.code }}</td>
                            <td>{{ booking.requester_name }}</td>
                            <td>{{ booking.vehicle?.plate_number }}<small class="d-block fleet-muted">{{ booking.driver?.name }}</small></td>
                            <td>{{ formatSchedule(booking) }}</td>
                            <td>{{ approvalSummary(booking) }}</td>
                            <td><span class="fleet-status" :class="`fleet-status--${booking.status}`">{{ statusLabel(booking.status) }}</span></td>
                            <td class="text-nowrap">
                                <router-link class="btn btn-sm btn-outline-primary min-action me-1" :to="`/bookings/${booking.id}`">Detail</router-link>
                                <router-link v-if="canEdit(booking)" class="btn btn-sm btn-outline-secondary min-action" :to="`/bookings/${booking.id}/edit`">Edit</router-link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="d-md-none p-3">
                <article v-for="booking in bookings" :key="booking.id" class="booking-mobile-row py-3">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div><h3 class="h6 mb-1">{{ booking.code }}</h3><p class="mb-1">{{ booking.requester_name }} · {{ booking.vehicle?.plate_number }} · {{ booking.driver?.name }}</p></div>
                        <span class="fleet-status" :class="`fleet-status--${booking.status}`">{{ statusLabel(booking.status) }}</span>
                    </div>
                    <p class="fleet-muted small mb-2">{{ formatSchedule(booking) }} WIB · {{ approvalSummary(booking) }}</p>
                    <div class="d-flex gap-2">
                        <router-link class="btn btn-sm btn-outline-primary min-action" :to="`/bookings/${booking.id}`">Detail</router-link>
                        <router-link v-if="canEdit(booking)" class="btn btn-sm btn-outline-secondary min-action" :to="`/bookings/${booking.id}/edit`">Edit</router-link>
                    </div>
                </article>
            </div>
        </section>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../bootstrap';
import { auth } from '../auth';

const bookings = ref([]);
const loading = ref(true);
const error = ref('');

async function load() {
    loading.value = true;
    error.value = '';
    try {
        bookings.value = (await api.get('/api/bookings')).data.data;
    } catch {
        error.value = 'Daftar pemesanan tidak dapat dimuat.';
    } finally {
        loading.value = false;
    }
}

function formatSchedule(booking) {
    const start = new Date(booking.start_at);
    const end = new Date(booking.end_at);
    const parts = new Intl.DateTimeFormat('id-ID', { timeZone: 'Asia/Jakarta', day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit', hour12: false });
    return `${parts.format(start)} sampai ${parts.format(end)}`;
}

function approvalSummary(booking) {
    return booking.approvals.map((approval) => `L${approval.level} ${approval.status === 'pending' && approval.level === 2 && booking.approvals[0].status !== 'approved' ? 'menunggu' : statusLabel(approval.status)}`).join(' · ');
}

function canEdit(booking) {
    return auth.user?.role === 'admin' && booking.status === 'pending' && booking.approvals[0]?.status === 'pending';
}

function statusLabel(status) {
    return ({ pending: 'Menunggu', approved: 'Disetujui', rejected: 'Ditolak', completed: 'Selesai' })[status] ?? status;
}

onMounted(load);
</script>

<style scoped>
.booking-mobile-row + .booking-mobile-row { border-top: 1px solid var(--fleet-line); }
</style>
