<template>
    <form class="card fleet-card mb-3" aria-label="Filter pemesanan" @submit.prevent="apply">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-lg-4">
                    <label class="form-label" for="booking-search">Kata kunci</label>
                    <input id="booking-search" v-model="draft.search" class="form-control" maxlength="120" placeholder="Kode, pemakai, atau tujuan">
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <label class="form-label" for="booking-status">Status</label>
                    <select id="booking-status" v-model="draft.status" class="form-select">
                        <option value="">Semua status</option>
                        <option value="pending">Menunggu</option>
                        <option value="approved">Disetujui</option>
                        <option value="rejected">Ditolak</option>
                        <option value="completed">Selesai</option>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <label class="form-label" for="booking-from">Dibuat dari (WIB)</label>
                    <input id="booking-from" v-model="draft.from" class="form-control" type="date" :max="draft.to || today" :aria-invalid="Boolean(validationError)" :aria-describedby="validationError ? 'booking-filter-error' : undefined">
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <label class="form-label" for="booking-to">Dibuat sampai (WIB)</label>
                    <input id="booking-to" v-model="draft.to" class="form-control" type="date" :min="draft.from" :max="today" :aria-invalid="Boolean(validationError)" :aria-describedby="validationError ? 'booking-filter-error' : undefined">
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <label class="form-label" for="booking-vehicle">Kendaraan</label>
                    <select id="booking-vehicle" v-model="draft.vehicle_id" class="form-select">
                        <option value="">Semua kendaraan</option>
                        <option v-for="vehicle in options.vehicles" :key="vehicle.id" :value="String(vehicle.id)">{{ vehicle.plate_number }}</option>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <label class="form-label" for="booking-driver">Driver</label>
                    <select id="booking-driver" v-model="draft.driver_id" class="form-select">
                        <option value="">Semua driver</option>
                        <option v-for="driver in options.drivers" :key="driver.id" :value="String(driver.id)">{{ driver.name }}</option>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <label class="form-label" for="booking-approver">Penyetuju</label>
                    <select id="booking-approver" v-model="draft.approver_id" class="form-select">
                        <option value="">Semua penyetuju</option>
                        <option v-for="approver in options.approvers" :key="approver.id" :value="String(approver.id)">{{ approver.name }}</option>
                    </select>
                </div>
            </div>

            <p v-if="optionsLoading" class="fleet-muted small mt-3 mb-0" role="status">Memuat pilihan kendaraan, driver, dan penyetuju…</p>
            <div v-else-if="optionsError" class="alert alert-danger mt-3 mb-0" role="alert">
                {{ optionsError }}
                <button class="btn btn-sm btn-outline-danger min-action ms-2" type="button" @click="emit('retry-options')">Coba lagi</button>
            </div>
            <p v-if="validationError" id="booking-filter-error" class="fleet-error mt-3 mb-0" role="alert">{{ validationError }}</p>

            <div class="d-flex flex-wrap justify-content-end gap-2 mt-3">
                <button class="btn btn-outline-secondary min-action" type="button" :disabled="loading" @click="emit('reset')">Reset</button>
                <button class="btn btn-primary min-action" type="submit" :disabled="loading" :aria-busy="loading">
                    {{ loading ? 'Memuat…' : 'Terapkan filter' }}
                </button>
            </div>
        </div>
    </form>
</template>

<script setup>
import { ref, watch } from 'vue';
import { toBookingQuery } from '../booking-filters';

const props = defineProps({
    value: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({ vehicles: [], drivers: [], approvers: [] }) },
    optionsLoading: { type: Boolean, default: false },
    optionsError: { type: String, default: '' },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['apply', 'reset', 'retry-options']);

const emptyFilters = { search: '', status: '', from: '', to: '', vehicle_id: '', driver_id: '', approver_id: '' };
const draft = ref({ ...emptyFilters });
const validationError = ref('');
const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(new Date());

watch(() => props.value, (value) => {
    draft.value = { ...emptyFilters, ...value };
    validationError.value = '';
}, { immediate: true, deep: true });

function apply() {
    validationError.value = '';
    if (Boolean(draft.value.from) !== Boolean(draft.value.to)) {
        validationError.value = 'Isi kedua tanggal atau kosongkan keduanya.';
        document.querySelector(draft.value.from ? '#booking-to' : '#booking-from')?.focus();
        return;
    }
    if (draft.value.from && draft.value.from > draft.value.to) {
        validationError.value = 'Tanggal awal tidak boleh melewati tanggal akhir.';
        document.querySelector('#booking-from')?.focus();
        return;
    }
    emit('apply', toBookingQuery(draft.value));
}

</script>
