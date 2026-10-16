<template>
    <main class="fleet-login">
        <button class="btn btn-outline-primary min-action fleet-login-theme" type="button" :aria-pressed="dark" @click="toggleTheme">
            {{ dark ? 'Mode terang' : 'Mode gelap' }}
        </button>
        <section class="card fleet-card fleet-login__card">
            <div class="card-body p-4 p-md-5">
                <p class="text-primary fw-semibold mb-1">FleetOps</p>
                <h1 class="h3 mb-2">Masuk ke aplikasi</h1>
                <p class="fleet-muted mb-4">Gunakan akun demo admin atau penyetuju.</p>

                <form @submit.prevent="submit" novalidate>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input id="email" v-model.trim="form.email" class="form-control" type="email" name="email" autocomplete="username" required :aria-invalid="Boolean(errors.email)" :aria-describedby="errors.email ? 'email-error' : undefined">
                        <p v-if="errors.email" id="email-error" class="fleet-error small mt-1 mb-0" role="alert">{{ errors.email }}</p>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="password">Kata sandi</label>
                        <input id="password" v-model="form.password" class="form-control" type="password" name="password" autocomplete="current-password" required :aria-invalid="Boolean(errors.password)" :aria-describedby="errors.password ? 'password-error' : undefined">
                        <p v-if="errors.password" id="password-error" class="fleet-error small mt-1 mb-0" role="alert">{{ errors.password }}</p>
                    </div>
                    <p v-if="error" id="login-error" ref="errorSummary" class="fleet-error" role="alert" tabindex="-1">{{ error }}</p>
                    <button class="btn btn-primary w-100 min-action" type="submit" :disabled="loading" :aria-busy="loading">
                        {{ loading ? 'Memeriksa akun…' : 'Masuk' }}
                    </button>
                </form>
            </div>
        </section>
    </main>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../bootstrap';
import { auth } from '../auth';
import { dark } from '../theme';
import { notice } from '../alerts';

const router = useRouter();
const form = reactive({ email: '', password: '' });
const errors = reactive({ email: '', password: '' });
const error = ref('');
const errorSummary = ref(null);
const loading = ref(false);

function toggleTheme() {
    dark.value = !dark.value;
}

async function submit() {
    loading.value = true;
    error.value = '';
    errors.email = '';
    errors.password = '';
    try {
        const { data } = await api.post('/login', form);
        auth.user = data.user;
        auth.ready = true;
        api.defaults.headers.common['X-CSRF-TOKEN'] = data.csrf_token;
        document.querySelector('meta[name="csrf-token"]').content = data.csrf_token;
        await router.replace(data.redirect);
    } catch (requestError) {
        const fields = requestError.response?.data?.errors ?? {};
        errors.email = fields.email?.[0] ?? '';
        errors.password = fields.password?.[0] ?? '';
        error.value = requestError.response?.status === 422 ? 'Periksa kembali email dan kata sandi.' : 'Login gagal. Periksa koneksi lalu coba lagi.';
        await notice(error.value, 'Login gagal');
        errorSummary.value?.focus();
    } finally {
        loading.value = false;
    }
}
</script>
