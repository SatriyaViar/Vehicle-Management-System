<template>
    <dialog
        ref="dialog"
        class="fleet-booking-dialog"
        aria-modal="true"
        aria-labelledby="booking-detail-title"
        @cancel.prevent="dismiss"
        @close="handleClose"
    >
        <div class="fleet-booking-dialog__content">
            <header class="fleet-booking-dialog__header d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <p class="fleet-muted mb-1">Detail pemesanan</p>
                    <h2 id="booking-detail-title" class="h4 mb-0" tabindex="-1">{{ booking?.code ?? 'Pemesanan' }}</h2>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <router-link v-if="canEdit" class="btn btn-outline-secondary min-action" :to="`/bookings/${booking.id}/edit`">Edit pemesanan</router-link>
                    <button class="btn btn-outline-primary min-action" type="button" @click="dismiss">Tutup</button>
                </div>
            </header>

            <p v-if="loading" class="fleet-muted mt-4" role="status">Memuat detail pemesanan…</p>
            <div v-else-if="error" class="alert alert-danger mt-4" role="alert">
                {{ error }}
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button class="btn btn-outline-danger min-action" type="button" @click="load">Coba lagi</button>
                    <button class="btn btn-outline-secondary min-action" type="button" @click="dismiss">Tutup</button>
                </div>
            </div>
            <template v-else-if="booking">
                <p v-if="actionError" class="alert alert-danger mt-3" role="alert">{{ actionError }}</p>
                <div class="row g-3 mt-1">
                    <div class="col-12 col-lg-7">
                        <section class="card fleet-card mb-3">
                            <div class="card-body">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                                    <h3 class="h5 mb-0">Rincian perjalanan</h3>
                                    <span class="fleet-status" :class="`fleet-status--${booking.status}`">{{ statusLabel(booking.status) }}</span>
                                </div>
                                <dl class="row mb-0">
                                    <dt class="col-sm-4 fleet-muted">Pegawai pemakai</dt><dd class="col-sm-8">{{ booking.requester_name }}</dd>
                                    <dt class="col-sm-4 fleet-muted">Kendaraan</dt><dd class="col-sm-8">{{ booking.vehicle?.plate_number }} · {{ vehicleType(booking.vehicle?.type) }} · {{ booking.vehicle?.ownership === 'rental' ? 'Sewa' : 'Milik perusahaan' }}</dd>
                                    <dt class="col-sm-4 fleet-muted">Driver</dt><dd class="col-sm-8">{{ booking.driver?.name }}</dd>
                                    <dt class="col-sm-4 fleet-muted">Jadwal</dt><dd class="col-sm-8">{{ formatTime(booking.start_at) }} sampai {{ formatTime(booking.end_at) }} WIB</dd>
                                    <dt class="col-sm-4 fleet-muted">Tujuan</dt><dd class="col-sm-8">{{ booking.destination }}</dd>
                                    <dt class="col-sm-4 fleet-muted">Keperluan</dt><dd class="col-sm-8 mb-0">{{ booking.purpose }}</dd>
                                </dl>
                                <p v-if="booking.completed_at" class="fleet-muted mt-3 mb-0">Selesai dikonfirmasi {{ formatTime(booking.completed_at) }} WIB</p>
                            </div>
                        </section>

                        <section class="card fleet-card">
                            <div class="card-body">
                                <h3 class="h5 mb-3">Persetujuan</h3>
                                <ol class="approval-list mb-0">
                                    <li v-for="approval in booking.approvals" :key="approval.level" class="approval-row">
                                        <div>
                                            <p class="fw-semibold mb-1">Level {{ approval.level }} · {{ approval.approver }}</p>
                                            <p class="fleet-muted small mb-0">
                                                {{ approval.status === 'pending' && booking.status === 'rejected' ? 'Tidak dilanjutkan' : approvalLabel(approval) }}
                                                <span v-if="approval.decided_at"> · {{ formatTime(approval.decided_at) }} WIB</span>
                                            </p>
                                        </div>
                                        <span class="fleet-status" :class="`fleet-status--${approval.status === 'pending' && booking.status === 'rejected' ? 'rejected' : approval.status}`">
                                            {{ approval.status === 'pending' && booking.status === 'rejected' ? 'Tidak dilanjutkan' : statusLabel(approval.status) }}
                                        </span>
                                    </li>
                                </ol>
                                <p v-if="waitingForLevelTwo" class="fleet-muted mt-3 mb-0">Giliran penyetuju level 2 setelah level 1 menyetujui.</p>
                                <div v-if="canDecide" class="d-flex flex-wrap gap-2 mt-3">
                                    <button class="btn btn-primary min-action" type="button" :disabled="acting" @click="decide('approve')">{{ acting ? 'Menyimpan…' : 'Setujui' }}</button>
                                    <button class="btn btn-outline-danger min-action" type="button" :disabled="acting" @click="decide('reject')">Tolak</button>
                                </div>
                                <button v-if="canComplete" class="btn btn-primary min-action mt-3" type="button" :disabled="acting" @click="complete">{{ acting ? 'Menyimpan…' : 'Tandai perjalanan selesai' }}</button>
                            </div>
                        </section>
                    </div>

                    <div class="col-12 col-lg-5">
                        <section class="card fleet-card">
                            <div class="card-body">
                                <h3 class="h5 mb-3">Riwayat proses</h3>
                                <ol v-if="booking.logs.length" class="activity-list mb-0">
                                    <li v-for="(log, index) in booking.logs" :key="`${log.action}-${index}`" class="py-2">
                                        <p class="fw-semibold mb-1">{{ eventLabel(log.action) }}</p>
                                        <p class="fleet-muted small mb-0">{{ log.actor }} · {{ formatTime(log.created_at) }} WIB</p>
                                    </li>
                                </ol>
                                <p v-else class="fleet-muted mb-0">Belum ada aktivitas.</p>
                            </div>
                        </section>
                    </div>
                </div>
            </template>
        </div>
    </dialog>
</template>

<script setup>
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import api from '../bootstrap';
import { auth } from '../auth';
import { confirmAction, notice } from '../alerts';

const props = defineProps({
    id: { type: [String, Number], required: true },
    returnFocus: { type: Object, default: null },
});
const emit = defineEmits(['dismiss', 'changed']);
const dialog = ref(null);
const booking = ref(null);
const loading = ref(true);
const acting = ref(false);
const error = ref('');
const actionError = ref('');
let loadRequest = 0;
let dismissing = false;
let returnFocus = null;

const ownApproval = computed(() => booking.value?.approvals.find((approval) => approval.approver_id === auth.user?.id));
const levelOne = computed(() => booking.value?.approvals.find((approval) => approval.level === 1));
const canDecide = computed(() => booking.value?.status === 'pending'
    && ownApproval.value?.status === 'pending'
    && (ownApproval.value.level === 1 || levelOne.value?.status === 'approved'));
const waitingForLevelTwo = computed(() => auth.user?.role === 'approver'
    && ownApproval.value?.level === 2
    && booking.value?.status === 'pending'
    && levelOne.value?.status === 'pending');
const canEdit = computed(() => auth.user?.role === 'admin'
    && booking.value?.status === 'pending'
    && levelOne.value?.status === 'pending');
const canComplete = computed(() => auth.user?.role === 'admin'
    && booking.value?.status === 'approved'
    && Date.parse(booking.value.start_at) <= Date.now());

async function load() {
    const request = ++loadRequest;
    loading.value = true;
    error.value = '';
    actionError.value = '';
    booking.value = null;
    if (!/^\d+$/.test(String(props.id))) {
        error.value = 'ID pemesanan tidak valid.';
        loading.value = false;
        void notice(error.value, 'Detail pemesanan tidak tersedia');
        return;
    }
    try {
        const response = await api.get(`/api/bookings/${encodeURIComponent(props.id)}`);
        if (request === loadRequest) booking.value = response.data.data;
    } catch {
        if (request === loadRequest) {
            error.value = 'Detail pemesanan tidak dapat dimuat atau Anda tidak memiliki akses.';
            void notice(error.value, 'Detail pemesanan tidak tersedia');
        }
    } finally {
        if (request === loadRequest) loading.value = false;
    }
}

function dismiss() {
    if (dismissing) return;
    dismissing = true;
    if (dialog.value?.open) dialog.value.close();
    emit('dismiss');
}

function handleClose() {
    if (!dismissing) dismiss();
}

async function decide(decision) {
    const action = decision === 'approve' ? 'Setujui' : 'Tolak';
    if (!await confirmAction(`${action} pemesanan ${booking.value.code}?`, `Konfirmasi ${action.toLowerCase()}`)) return;
    acting.value = true;
    actionError.value = '';
    try {
        await api.post(`/api/bookings/${booking.value.id}/decision`, { decision });
        await load();
        document.getElementById('booking-detail-title')?.focus();
        emit('changed');
        await notice(decision === 'approve' ? 'Persetujuan berhasil disimpan.' : 'Pemesanan berhasil ditolak.', 'Pemesanan diperbarui');
    } catch (requestError) {
        actionError.value = requestError.response?.data?.message ?? 'Keputusan tidak dapat disimpan.';
        await notice(actionError.value, 'Keputusan tidak tersimpan');
    } finally {
        acting.value = false;
    }
}

async function complete() {
    if (!await confirmAction(`Tandai perjalanan ${booking.value.code} selesai?`, 'Konfirmasi penyelesaian')) return;
    acting.value = true;
    actionError.value = '';
    try {
        await api.post(`/api/bookings/${booking.value.id}/complete`);
        await load();
        document.getElementById('booking-detail-title')?.focus();
        emit('changed');
        await notice('Perjalanan berhasil ditandai selesai.', 'Pemesanan diperbarui');
    } catch (requestError) {
        actionError.value = requestError.response?.data?.message ?? 'Perjalanan belum dapat diselesaikan.';
        await notice(actionError.value, 'Penyelesaian tidak tersimpan');
    } finally {
        acting.value = false;
    }
}

function formatTime(value) {
    return new Intl.DateTimeFormat('id-ID', {
        timeZone: 'Asia/Jakarta', dateStyle: 'medium', timeStyle: 'short', hour12: false,
    }).format(new Date(value));
}

function statusLabel(status) {
    return ({ pending: 'Menunggu', approved: 'Disetujui', rejected: 'Ditolak', completed: 'Selesai' })[status] ?? status;
}

function approvalLabel(approval) {
    return approval.status === 'pending' && approval.level === 2 && levelOne.value?.status !== 'approved'
        ? 'Menunggu level 1' : statusLabel(approval.status);
}

function vehicleType(type) {
    return type === 'goods' ? 'Angkutan barang' : 'Angkutan orang';
}

function eventLabel(action) {
    return ({
        BOOKING_CREATED: 'Pemesanan dibuat',
        BOOKING_UPDATED: 'Pemesanan diubah',
        APPROVAL_LEVEL_1_APPROVED: 'Disetujui level 1',
        APPROVAL_LEVEL_2_APPROVED: 'Disetujui level 2',
        BOOKING_REJECTED: 'Pemesanan ditolak',
        BOOKING_COMPLETED: 'Perjalanan selesai',
    })[action] ?? action;
}

watch(() => props.id, async () => {
    dismissing = false;
    await nextTick();
    if (!dialog.value?.open) {
        returnFocus = props.returnFocus ?? document.activeElement;
        dialog.value?.showModal();
    }
    document.getElementById('booking-detail-title')?.focus();
    await load();
}, { immediate: true });

onUnmounted(() => {
    loadRequest++;
    if (dialog.value?.open) dialog.value.close();
    const target = returnFocus;
    if (target?.isConnected) requestAnimationFrame(() => target.focus());
    returnFocus = null;
});
</script>

<style scoped>
.approval-list,
.activity-list { padding-left: 1.25rem; }
.approval-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; padding: .75rem 0; border-bottom: 1px solid var(--fleet-line); }
.approval-row:last-child { border-bottom: 0; }
</style>
