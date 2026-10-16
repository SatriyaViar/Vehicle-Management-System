<template>
    <dialog
        ref="dialog"
        class="fleet-alert"
        role="alertdialog"
        aria-modal="true"
        :aria-labelledby="`fleet-alert-title-${currentAlert?.id ?? 'closed'}`"
        :aria-describedby="`fleet-alert-message-${currentAlert?.id ?? 'closed'}`"
        @keydown="trapTab"
        @cancel.prevent="dismiss(false)"
        @close="handleClose"
    >
        <div class="fleet-alert__content">
            <h2 :id="`fleet-alert-title-${currentAlert?.id ?? 'closed'}`" class="h5 mb-2">{{ currentAlert?.title }}</h2>
            <p :id="`fleet-alert-message-${currentAlert?.id ?? 'closed'}`" class="mb-4">{{ currentAlert?.message }}</p>
            <div class="d-flex justify-content-end gap-2">
                <button v-if="currentAlert?.kind === 'confirm'" ref="cancelButton" class="btn btn-outline-secondary min-action" type="button" @click="dismiss(false)">Batal</button>
                <button ref="acceptButton" class="btn btn-primary min-action" type="button" @click="dismiss(true)">
                    {{ currentAlert?.kind === 'confirm' ? 'Lanjutkan' : 'Tutup' }}
                </button>
            </div>
        </div>
    </dialog>
</template>

<script setup>
import { nextTick, onUnmounted, ref, watch } from 'vue';
import { currentAlert, finishAlert } from '../alerts';

const dialog = ref(null);
const cancelButton = ref(null);
const acceptButton = ref(null);
const closingIds = [];

function focusInitial() {
    (currentAlert.value?.kind === 'confirm' ? cancelButton.value : acceptButton.value)?.focus();
}

function dismiss(accepted) {
    const alert = currentAlert.value;
    if (!alert) return;
    closingIds.push(alert.id);
    if (dialog.value?.open) dialog.value.close();
    finishAlert(accepted, alert.id);
}

function handleClose() {
    const id = closingIds.shift();
    if (id !== undefined) finishAlert(false, id);
    else if (currentAlert.value) finishAlert(false, currentAlert.value.id);
    if (dialog.value?.open) focusInitial();
}

function trapTab(event) {
    if (event.key !== 'Tab') return;
    const buttons = [...dialog.value.querySelectorAll('button:not([disabled])')];
    const first = buttons[0];
    const last = buttons.at(-1);
    if (!first) return;
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

watch(currentAlert, async (alert) => {
    if (!alert) {
        if (dialog.value?.open) dialog.value.close();
        return;
    }
    await nextTick();
    if (!dialog.value?.open) dialog.value?.showModal();
    focusInitial();
});

onUnmounted(() => {
    if (dialog.value?.open) dialog.value.close();
});
</script>
