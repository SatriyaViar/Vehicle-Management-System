import test from 'node:test';
import assert from 'node:assert/strict';
import { confirmAction, currentAlert, finishAlert, notice } from '../../resources/js/alerts.js';

test('cancel resolves a confirmation as false and a single accept as true', async () => {
    const cancelled = confirmAction('Tolak?');
    finishAlert(false);
    assert.equal(await cancelled, false);

    const accepted = confirmAction('Setujui?');
    finishAlert(true);
    finishAlert(false);
    assert.equal(await accepted, true);
});

test('alerts queue in order and an old close event cannot dismiss the next alert', async () => {
    const first = notice('Satu');
    const firstId = currentAlert.value.id;
    const second = notice('Dua');
    assert.equal(currentAlert.value.message, 'Satu');

    finishAlert(true, firstId);
    await first;
    await new Promise((resolve) => setTimeout(resolve, 0));
    assert.equal(currentAlert.value.message, 'Dua');

    finishAlert(false, firstId);
    assert.equal(currentAlert.value.message, 'Dua');
    finishAlert();
    await second;
});
