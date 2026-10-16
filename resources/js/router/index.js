import { createRouter, createWebHistory } from 'vue-router';
import { auth, loadUser } from '../auth';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/login', name: 'login', component: () => import('../pages/Login.vue'), meta: { guest: true } },
        {
            path: '/',
            component: () => import('../layouts/AppLayout.vue'),
            children: [
                { path: '', redirect: '/dashboard' },
                { path: 'dashboard', name: 'dashboard', component: () => import('../pages/Dashboard.vue'), meta: { title: 'Dashboard', role: 'admin' } },
                { path: 'bookings', name: 'bookings', component: () => import('../pages/Bookings.vue'), meta: { title: 'Pemesanan' } },
                { path: 'bookings/create', name: 'booking-create', component: () => import('../pages/BookingForm.vue'), meta: { title: 'Pesan kendaraan', role: 'admin' } },
                { path: 'bookings/:id/edit', name: 'booking-edit', component: () => import('../pages/BookingForm.vue'), meta: { title: 'Ubah pemesanan', role: 'admin' } },
                { path: 'bookings/:id', name: 'booking-detail', redirect: (to) => ({ path: '/bookings', query: { ...to.query, detail: to.params.id } }) },
                { path: 'reports', name: 'reports', component: () => import('../pages/Reports.vue'), meta: { title: 'Laporan', role: 'admin' } },
            ],
        },
        { path: '/:pathMatch(.*)*', redirect: '/bookings' },
    ],
});

router.beforeEach(async (to) => {
    if (!auth.ready) {
        try {
            await loadUser();
        } catch {
            return { name: 'login' };
        }
    }

    if (to.meta.guest) return auth.user ? (auth.user.role === 'admin' ? '/dashboard' : '/bookings') : true;
    if (!auth.user) return { name: 'login' };
    if (to.meta.role && auth.user.role !== to.meta.role) return '/bookings';
    if (to.path === '/') return auth.user.role === 'admin' ? '/dashboard' : '/bookings';

    return true;
});

export default router;
