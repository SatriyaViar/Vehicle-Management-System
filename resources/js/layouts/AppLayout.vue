<template>
    <div class="fleet-shell">
        <aside class="sidebar sidebar-base sidebar-white sidebar-default navs-rounded-all fleet-sidebar" :class="{ 'is-open': menuOpen }" aria-label="Navigasi utama">
            <div class="sidebar-header d-flex align-items-center justify-content-between px-4">
                <router-link class="navbar-brand fleet-brand" :to="homePath">
                    FleetOps
                    <small>Pool kendaraan</small>
                </router-link>
                <button class="btn btn-light min-action d-lg-none" type="button" aria-label="Tutup menu" @click="menuOpen = false">Tutup</button>
            </div>
            <div class="sidebar-body pt-0 px-3">
                <nav class="sidebar-list" aria-label="Halaman">
                    <router-link v-if="auth.user?.role === 'admin'" class="nav-link" active-class="active" to="/dashboard" @click="menuOpen = false">Dashboard</router-link>
                    <router-link class="nav-link" active-class="active" to="/bookings" @click="menuOpen = false">Pemesanan</router-link>
                    <router-link v-if="auth.user?.role === 'admin'" class="nav-link" active-class="active" to="/reports" @click="menuOpen = false">Laporan</router-link>
                </nav>
                <div class="mt-4 pt-3 border-top">
                    <span class="d-block text-truncate">{{ auth.user?.name }}</span>
                    <small class="fleet-muted">{{ auth.user?.role === 'admin' ? 'Admin pool' : 'Penyetuju' }}</small>
                    <button class="btn btn-link min-action px-0 d-block" type="button" @click="signOut">Keluar</button>
                </div>
            </div>
        </aside>

        <button v-if="menuOpen" class="fleet-backdrop border-0" type="button" aria-label="Tutup menu navigasi" @click="menuOpen = false"></button>

        <main class="fleet-main">
            <header class="fleet-topbar">
                <button class="btn btn-light min-action d-lg-none" type="button" :aria-expanded="menuOpen" aria-label="Buka menu" @click="menuOpen = !menuOpen">Menu</button>
                <h1 class="flex-grow-1">{{ route.meta.title }}</h1>
                <button class="btn btn-outline-primary min-action" type="button" :aria-pressed="dark" @click="toggleTheme">
                    {{ dark ? 'Mode terang' : 'Mode gelap' }}
                </button>
            </header>
            <section class="content-inner fleet-page">
                <router-view />
            </section>
        </main>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../bootstrap';
import { auth } from '../auth';
import { dark } from '../theme';

const route = useRoute();
const router = useRouter();
const menuOpen = ref(false);
const homePath = computed(() => auth.user?.role === 'admin' ? '/dashboard' : '/bookings');

function toggleTheme() {
    dark.value = !dark.value;
}

async function signOut() {
    let csrfToken = '';
    try {
        csrfToken = (await api.post('/logout')).data.csrf_token;
    } finally {
        auth.user = null;
        auth.ready = true;
        if (csrfToken) {
            api.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
            document.querySelector('meta[name="csrf-token"]').content = csrfToken;
        }
        await router.push('/login');
    }
}

function closeOnEscape(event) {
    if (event.key === 'Escape') menuOpen.value = false;
}

onMounted(() => {
    window.addEventListener('keydown', closeOnEscape);
});
onUnmounted(() => window.removeEventListener('keydown', closeOnEscape));
</script>
