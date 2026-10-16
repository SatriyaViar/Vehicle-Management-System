import { ref } from 'vue';

export const currentAlert = ref(null);

const queue = [];
let activeAlert = null;
let nextId = 0;

function presentNext() {
    if (activeAlert || !queue.length) return;
    activeAlert = queue.shift();
    currentAlert.value = {
        id: activeAlert.id,
        kind: activeAlert.kind,
        title: activeAlert.title,
        message: activeAlert.message,
    };
}

function enqueue(kind, message, title) {
    return new Promise((resolve) => {
        queue.push({ id: ++nextId, kind, message: String(message), title: String(title), resolve });
        presentNext();
    });
}

export function notice(message, title = 'Pemberitahuan') {
    return enqueue('notice', message, title);
}

export function confirmAction(message, title = 'Konfirmasi') {
    return enqueue('confirm', message, title);
}

export function finishAlert(accepted = false, id = currentAlert.value?.id) {
    if (!activeAlert || id !== activeAlert.id) return;

    const finished = activeAlert;
    activeAlert = null;
    currentAlert.value = null;
    finished.resolve(finished.kind === 'confirm' ? Boolean(accepted) : undefined);
    queueMicrotask(presentNext);
}
