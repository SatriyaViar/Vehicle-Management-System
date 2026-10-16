import { ref, watch } from 'vue';

export const dark = ref(localStorage.getItem('fleetops-theme') === 'dark');

watch(dark, (value) => {
    document.body.classList.toggle('dark', value);
    localStorage.setItem('fleetops-theme', value ? 'dark' : 'light');
}, { immediate: true });
