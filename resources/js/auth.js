import { reactive } from 'vue';
import api from './bootstrap';

export const auth = reactive({ user: null, ready: false });

export async function loadUser() {
    try {
        const { data } = await api.get('/api/me');
        auth.user = data;
    } catch (error) {
        if (error.response?.status !== 401) throw error;
        auth.user = null;
    } finally {
        auth.ready = true;
    }
}
