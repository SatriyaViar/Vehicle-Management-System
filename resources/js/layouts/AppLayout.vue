<template>
    <div class="fleet-shell">
        <aside id="fleet-sidebar" class="sidebar sidebar-base sidebar-white sidebar-default navs-rounded-all fleet-sidebar" :class="{ 'is-open': menuOpen }" data-toggle="main-sidebar" data-sidebar="responsive" aria-label="Navigasi utama">
            <div class="sidebar-header d-flex align-items-center justify-content-between px-4">
                <router-link class="navbar-brand fleet-brand" :to="homePath">
                    <span class="fleet-brand__name">FleetOps</span>
                    <span class="fleet-brand__caption">Pool kendaraan</span>
                </router-link>
                <button class="btn btn-outline-secondary min-action d-lg-none fleet-sidebar__close" type="button" aria-label="Tutup menu" @click="closeMenu">Tutup</button>
            </div>
            <div class="sidebar-body pt-0 px-3">
                <nav class="sidebar-list" aria-label="Halaman">
                    <ul class="navbar-nav iq-main-menu">
                        <li v-if="auth.user?.role === 'admin'" class="nav-item">
                            <router-link class="nav-link" active-class="active" to="/dashboard" @click="closeMenu">
                                <span class="icon fleet-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M3 6.5C3 3.87 3.03 3 6.5 3S10 3.87 10 6.5 10.01 10 6.5 10 3 9.13 3 6.5Zm11 0c0-2.63.03-3.5 3.5-3.5S21 3.87 21 6.5 21.01 10 17.5 10 14 9.13 14 6.5ZM3 17.5c0-2.63.03-3.5 3.5-3.5s3.5.87 3.5 3.5.01 3.5-3.5 3.5S3 20.13 3 17.5Zm11 0c0-2.63.03-3.5 3.5-3.5s3.5.87 3.5 3.5.01 3.5-3.5 3.5-3.5-.87-3.5-3.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                                <span class="item-name">Dashboard</span>
                            </router-link>
                        </li>
                        <li class="nav-item">
                            <router-link class="nav-link" active-class="active" to="/bookings" @click="closeMenu">
                                <span class="icon fleet-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M3.1 9.4h17.8M16 2v3.3M8 2v3.3M7.8 3.6h8.4c2.9 0 4.8 1.6 4.8 4.6v9.1c0 3.1-1.8 4.7-4.8 4.7H7.8C4.8 22 3 20.3 3 17.3V8.2c0-3 1.8-4.6 4.8-4.6Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M7.6 13.3h.1m4.3 0h.1m4.3 0h.1m-8.8 3.9h.1m4.3 0h.1m4.3 0h.1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>
                                <span class="item-name">Pemesanan</span>
                            </router-link>
                        </li>
                        <li v-if="auth.user?.role === 'admin'" class="nav-item">
                            <router-link class="nav-link" active-class="active" to="/reports" @click="closeMenu">
                                <span class="icon fleet-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M7.4 10.2v6.9M12 6.9v10.2m4.6-3.3v3.3M7.3 2h9.4C20 2 22 4.3 22 7.6v8.8c0 3.3-2 5.6-5.3 5.6H7.3C4 22 2 19.7 2 16.4V7.6C2 4.3 4 2 7.3 2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                                <span class="item-name">Laporan</span>
                            </router-link>
                        </li>
                    </ul>
                </nav>
                <div class="fleet-sidebar__account">
                    <span class="fleet-account-avatar" aria-hidden="true">{{ auth.user?.name?.slice(0, 1) }}</span>
                    <div class="fleet-account-copy">
                        <span class="d-block text-truncate fw-semibold">{{ auth.user?.name }}</span>
                        <small class="fleet-muted">{{ auth.user?.role === 'admin' ? 'Admin pool' : 'Penyetuju' }}</small>
                    </div>
                    <button class="btn btn-link min-action fleet-signout" type="button" aria-label="Keluar dari akun" @click="signOut">Keluar</button>
                </div>
            </div>
        </aside>

        <button v-if="menuOpen" class="fleet-backdrop border-0" type="button" aria-label="Tutup menu navigasi" @click="closeMenu"></button>

        <main class="main-content">
            <nav class="nav navbar navbar-expand-xl navbar-light iq-navbar fleet-navbar" aria-label="Kontrol halaman">
                <div class="container-fluid navbar-inner">
                    <button id="fleet-menu-button" class="btn btn-outline-secondary min-action d-lg-none fleet-menu-trigger" type="button" aria-controls="fleet-sidebar" :aria-expanded="menuOpen" aria-label="Buka menu navigasi" @click="toggleMenu">Menu</button>
                    <span class="fleet-navbar__title d-none d-sm-block">{{ route.meta.title }}</span>
                    <div class="ms-auto d-flex align-items-center gap-2">
                        <span class="fleet-demo-label d-none d-sm-inline">Data demo</span>
                        <button class="btn btn-outline-primary min-action" type="button" :aria-pressed="dark" @click="toggleTheme">
                    {{ dark ? 'Mode terang' : 'Mode gelap' }}
                        </button>
                    </div>
                </div>
            </nav>
            <div class="content-inner pb-0 fleet-page">
                <router-view />
            </div>
        </main>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../bootstrap';
import { auth } from '../auth';
import { dark } from '../theme';
import { notice } from '../alerts';

const route = useRoute();
const router = useRouter();
const menuOpen = ref(false);
const homePath = computed(() => auth.user?.role === 'admin' ? '/dashboard' : '/bookings');

function toggleTheme() {
    dark.value = !dark.value;
}

function openMenu() {
    menuOpen.value = true;
    void nextTick(() => requestAnimationFrame(() => requestAnimationFrame(() => document.querySelector('#fleet-sidebar .nav-link')?.focus())));
}

function closeMenu() {
    const wasOpen = menuOpen.value;
    menuOpen.value = false;
    if (wasOpen) requestAnimationFrame(() => requestAnimationFrame(() => document.getElementById('fleet-menu-button')?.focus()));
}

function toggleMenu() {
    menuOpen.value ? closeMenu() : openMenu();
}

async function signOut() {
    let csrfToken;
    try {
        csrfToken = (await api.post('/logout')).data.csrf_token;
    } catch {
        await notice('Sesi tidak dapat diakhiri. Coba lagi.', 'Keluar gagal');
        return;
    }
    auth.user = null;
    auth.ready = true;
    api.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
    document.querySelector('meta[name="csrf-token"]').content = csrfToken;
    await router.push('/login');
}

function closeOnEscape(event) {
    if (event.key === 'Escape' && menuOpen.value) closeMenu();
}

onMounted(() => {
    window.addEventListener('keydown', closeOnEscape);
});
onUnmounted(() => window.removeEventListener('keydown', closeOnEscape));
</script>
