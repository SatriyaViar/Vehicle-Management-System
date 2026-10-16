<template>
    <div>
        <div class="mb-4">
            <p class="fleet-muted mb-1">Pemesanan kendaraan</p>
            <h2 class="h4 mb-0">{{ editing ? 'Ubah pemesanan' : 'Pesan kendaraan' }}</h2>
        </div>

        <p v-if="loading" role="status">Memuat data form…</p>
        <div v-else-if="loadError" class="alert alert-danger" role="alert">
            {{ loadError }} <button class="btn btn-sm btn-outline-danger" type="button" @click="load">Coba lagi</button>
        </div>
        <form v-else class="card fleet-card" @submit.prevent="submit" novalidate>
            <div class="card-body p-3 p-md-4">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="requester">Nama pegawai pemakai</label>
                        <input id="requester" v-model.trim="form.requester_name" class="form-control" maxlength="120" required :aria-invalid="Boolean(errors.requester_name)">
                        <p v-if="errors.requester_name" class="fleet-error small mt-1 mb-0" role="alert">{{ errors.requester_name }}</p>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="destination">Tujuan / lokasi</label>
                        <input id="destination" v-model.trim="form.destination" class="form-control" maxlength="255" required :aria-invalid="Boolean(errors.destination)">
                        <p v-if="errors.destination" class="fleet-error small mt-1 mb-0" role="alert">{{ errors.destination }}</p>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="vehicle">Kendaraan</label>
                        <select id="vehicle" v-model="form.vehicle_id" class="form-select" required :aria-invalid="Boolean(errors.vehicle_id)">
                            <option value="" disabled>Pilih kendaraan</option>
                            <option v-for="vehicle in options.vehicles" :key="vehicle.id" :value="vehicle.id">{{ vehicle.plate_number }} · {{ vehicle.type === 'goods' ? 'Angkutan barang' : 'Angkutan orang' }} · {{ vehicle.ownership === 'rental' ? 'Sewa' : 'Milik perusahaan' }}</option>
                        </select>
                        <p v-if="errors.vehicle_id" class="fleet-error small mt-1 mb-0" role="alert">{{ errors.vehicle_id }}</p>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="driver">Driver</label>
                        <select id="driver" v-model="form.driver_id" class="form-select" required :aria-invalid="Boolean(errors.driver_id)">
                            <option value="" disabled>Pilih driver</option>
                            <option v-for="driver in options.drivers" :key="driver.id" :value="driver.id">{{ driver.name }}</option>
                        </select>
                        <p v-if="errors.driver_id" class="fleet-error small mt-1 mb-0" role="alert">{{ errors.driver_id }}</p>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="purpose">Keperluan perjalanan</label>
                        <textarea id="purpose" v-model.trim="form.purpose" class="form-control" rows="3" required :aria-invalid="Boolean(errors.purpose)"></textarea>
                        <p v-if="errors.purpose" class="fleet-error small mt-1 mb-0" role="alert">{{ errors.purpose }}</p>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="start_at">Mulai (WIB)</label>
                        <input id="start_at" v-model="form.start_at" class="form-control" type="datetime-local" required :aria-invalid="Boolean(errors.start_at)">
                        <p v-if="errors.start_at" class="fleet-error small mt-1 mb-0" role="alert">{{ errors.start_at }}</p>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="end_at">Selesai (WIB)</label>
                        <input id="end_at" v-model="form.end_at" class="form-control" type="datetime-local" required :aria-invalid="Boolean(errors.end_at)">
                        <p v-if="errors.end_at" class="fleet-error small mt-1 mb-0" role="alert">{{ errors.end_at }}</p>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="approver1">Penyetuju level 1</label>
                        <select id="approver1" v-model="form.approver_1_id" class="form-select" required :aria-invalid="Boolean(errors.approver_1_id)">
                            <option value="" disabled>Pilih penyetuju</option>
                            <option v-for="approver in options.approvers" :key="approver.id" :value="approver.id">{{ approver.name }}</option>
                        </select>
                        <p v-if="errors.approver_1_id" class="fleet-error small mt-1 mb-0" role="alert">{{ errors.approver_1_id }}</p>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="approver2">Penyetuju level 2</label>
                        <select id="approver2" v-model="form.approver_2_id" class="form-select" required :aria-invalid="Boolean(errors.approver_2_id)">
                            <option value="" disabled>Pilih penyetuju</option>
                            <option v-for="approver in options.approvers" :key="approver.id" :value="approver.id">{{ approver.name }}</option>
                        </select>
                        <p v-if="errors.approver_2_id" class="fleet-error small mt-1 mb-0" role="alert">{{ errors.approver_2_id }}</p>
                    </div>
                </div>

                <p v-if="error" class="alert alert-danger mt-4 mb-0" role="alert">{{ error }}</p>
                <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                    <router-link class="btn btn-outline-secondary min-action" to="/bookings">Batal</router-link>
                    <button class="btn btn-primary min-action" type="submit" :disabled="saving" :aria-busy="saving">{{ saving ? 'Menyimpan…' : editing ? 'Simpan perubahan' : 'Buat pemesanan' }}</button>
                </div>
            </div>
        </form>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../bootstrap';

const route = useRoute();
const router = useRouter();
const editing = computed(() => Boolean(route.params.id));
const loading = ref(true);
const saving = ref(false);
const loadError = ref('');
const error = ref('');
const errors = reactive({});
const options = reactive({ vehicles: [], drivers: [], approvers: [] });
const form = reactive({
    requester_name: '', destination: '', vehicle_id: '', driver_id: '', purpose: '',
    start_at: '', end_at: '', approver_1_id: '', approver_2_id: '',
});

function formTime(timestamp) {
    return timestamp?.slice(0, 16) ?? '';
}

async function load() {
    loading.value = true;
    loadError.value = '';
    try {
        Object.assign(options, (await api.get('/api/booking-options')).data);
        if (editing.value) {
            const { data } = await api.get(`/api/bookings/${route.params.id}`);
            const booking = data.data;
            if (booking.status !== 'pending' || booking.approvals[0]?.status !== 'pending') {
                await router.replace(`/bookings/${booking.id}`);
                return;
            }
            Object.assign(form, {
                requester_name: booking.requester_name,
                destination: booking.destination,
                vehicle_id: booking.vehicle_id,
                driver_id: booking.driver_id,
                purpose: booking.purpose,
                start_at: formTime(booking.start_at),
                end_at: formTime(booking.end_at),
                approver_1_id: booking.approvals[0]?.approver_id ?? '',
                approver_2_id: booking.approvals[1]?.approver_id ?? '',
            });
        }
    } catch {
        loadError.value = 'Data form tidak dapat dimuat.';
    } finally {
        loading.value = false;
    }
}

async function submit() {
    saving.value = true;
    error.value = '';
    Object.keys(errors).forEach((key) => delete errors[key]);
    try {
        // Keep datetime-local fields unchanged. Laravel interprets them as Asia/Jakarta.
        const response = editing.value
            ? await api.patch(`/api/bookings/${route.params.id}`, form)
            : await api.post('/api/bookings', form);
        await router.push(`/bookings/${response.data.data.id}`);
    } catch (requestError) {
        Object.assign(errors, Object.fromEntries(Object.entries(requestError.response?.data?.errors ?? {}).map(([key, messages]) => [key, messages[0]])));
        error.value = requestError.response?.status === 409
            ? requestError.response.data.message
            : requestError.response?.status === 422 ? 'Periksa kembali data pemesanan.' : 'Pemesanan tidak dapat disimpan.';
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>
