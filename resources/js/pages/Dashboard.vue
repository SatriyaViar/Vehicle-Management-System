<template>
    <div>
        <header class="fleet-page-heading mb-4">
            <div>
                <p class="fleet-muted mb-1">Ringkasan armada dan pemesanan</p>
                <h1>Aktivitas kendaraan</h1>
            </div>
        </header>

        <p v-if="loading" role="status">Memuat ringkasan…</p>
        <div v-else-if="error" class="alert alert-danger" role="alert">
            {{ error }} <button class="btn btn-sm btn-outline-danger ms-2" type="button" @click="load">Coba lagi</button>
        </div>
        <p v-else-if="!summary" class="alert alert-info" role="status">Ringkasan belum tersedia.</p>
        <template v-else>
            <div class="row g-3 mb-4">
                <div v-for="metric in metrics" :key="metric.label" class="col-12 col-sm-6 col-xl-3">
                    <section class="card fleet-card">
                        <div class="card-body">
                            <p class="fleet-muted mb-2">{{ metric.label }}</p>
                            <p class="h2 mb-0">{{ metric.value }}</p>
                        </div>
                    </section>
                </div>
            </div>

            <section class="card fleet-card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div>
                            <h2 class="h5 mb-1">Perjalanan selesai per bulan</h2>
                            <p class="fleet-muted mb-0">Pemakaian dihitung saat admin menandai perjalanan selesai, {{ summary.year }} (WIB).</p>
                        </div>
                    </div>
                    <div class="fleet-chart" role="img" :aria-label="chartDescription">
                        <div v-for="(month, index) in summary.monthly_usage" :key="month.month" class="fleet-chart__month">
                            <span class="small fw-semibold">{{ month.trips }}</span>
                            <span class="fleet-chart__bar" :style="{ height: `${barHeight(month.trips)}%` }"></span>
                            <span>{{ monthNames[index] }}</span>
                        </div>
                    </div>
                </div>
            </section>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../bootstrap';
import { notice } from '../alerts';

const summary = ref(null);
const loading = ref(true);
const error = ref('');
const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const maxTrips = computed(() => Math.max(1, ...(summary.value?.monthly_usage.map((month) => month.trips) ?? [0])));
const metrics = computed(() => [
    { label: 'Total pemesanan', value: summary.value.total_bookings },
    { label: 'Menunggu persetujuan', value: summary.value.pending },
    { label: 'Disetujui', value: summary.value.approved },
    { label: 'Perjalanan selesai', value: summary.value.completed_trips },
]);
const chartDescription = computed(() => summary.value.monthly_usage.map((month, index) => `${monthNames[index]}: ${month.trips}`).join(', '));

function barHeight(value) {
    return value ? Math.max(12, (value / maxTrips.value) * 100) : 2;
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        summary.value = (await api.get('/api/dashboard')).data;
    } catch {
        error.value = 'Ringkasan tidak dapat dimuat.';
        void notice(error.value, 'Ringkasan tidak tersedia');
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>
