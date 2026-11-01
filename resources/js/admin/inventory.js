/**
 * Stock adjustment modal: [data-geni-adjust] opens #geni_adjust_modal, optionally
 * pre-selecting data-branch / data-variant, and issues a fresh idempotency key so
 * a double submit can never record the same movement twice.
 */
function newKey() {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;
}

export function initInventory() {
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-geni-adjust]');
        const modalElement = document.getElementById('geni_adjust_modal');

        if (!trigger || !modalElement || !window.bootstrap) {
            return;
        }

        event.preventDefault();
        const form = modalElement.querySelector('form');
        form.reset();
        form.querySelector('[data-geni-idempotency]').value = newKey();
        form.querySelector('[data-geni-adjust-label]').textContent = trigger.dataset.label || '';

        if (trigger.dataset.branch) {
            form.querySelector('[name="branch_id"]').value = trigger.dataset.branch;
        }
        if (trigger.dataset.variant) {
            form.querySelector('[name="variant_id"]').value = trigger.dataset.variant;
        }

        window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
    });
}
