/**
 * Progressive AJAX forms: <form data-geni-ajax-form>.
 * Without JavaScript the same forms post normally and the server redirects with flash messages.
 *
 * Options (data attributes on the form):
 *   data-geni-close-modal="#id"     hide this modal after success
 *   data-geni-reset-on-success      clear the form after success
 *
 * Response: {message, redirect?, level?} — level "error" shows the carried-over message as an error.
 */
import { request } from './http';
import { errorMessage, flashNext, setLoading, t, toast } from './ui';

export function clearErrors(form) {
    form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
    form.querySelectorAll('[aria-invalid="true"]').forEach((el) => el.removeAttribute('aria-invalid'));
    form.querySelectorAll('[data-geni-error-for]').forEach((el) => {
        el.textContent = '';
    });
}

/**
 * Show Laravel validation errors next to their fields.
 * "roles.0" maps to the "roles" feedback element when no exact match exists.
 */
export function showErrors(form, errors) {
    let first = null;

    Object.entries(errors ?? {}).forEach(([key, messages]) => {
        const message = Array.isArray(messages) ? messages[0] : String(messages);
        const base = key.split('.')[0];
        const feedback = form.querySelector(`[data-geni-error-for="${CSS.escape(key)}"]`)
            ?? form.querySelector(`[data-geni-error-for="${CSS.escape(base)}"]`);

        if (feedback && !feedback.textContent) {
            feedback.textContent = message;
        }

        const inputName = key.includes('.') ? `${base}[]` : key;
        form.querySelectorAll(`[name="${CSS.escape(key)}"], [name="${CSS.escape(inputName)}"]`).forEach((input) => {
            if (input.type === 'hidden') {
                return;
            }
            input.classList.add('is-invalid');
            input.setAttribute('aria-invalid', 'true');
            first ??= input;
        });

        first ??= feedback;
    });

    if (first) {
        first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (typeof first.focus === 'function') {
            first.focus({ preventScroll: true });
        }
    }
}

async function submit(form, event) {
    event.preventDefault();

    const button = form.querySelector('[type="submit"]');
    clearErrors(form);
    setLoading(button, true);

    const result = await request(form.getAttribute('action'), {
        method: 'POST',
        data: new FormData(form),
    });

    setLoading(button, false);

    if (result.ok) {
        const message = result.data?.message ?? t('success');

        if (form.dataset.geniCloseModal) {
            const modalElement = document.querySelector(form.dataset.geniCloseModal);
            if (modalElement && window.bootstrap) {
                const modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
                // Bootstrap ignores hide() while the opening animation runs (fast responses): hide once it is shown.
                if (modal._isTransitioning) {
                    modalElement.addEventListener('shown.bs.modal', () => modal.hide(), { once: true });
                } else {
                    modal.hide();
                }
            }
        }

        if (form.hasAttribute('data-geni-reset-on-success') || form.dataset.geniCloseModal) {
            form.reset();
        }

        // Refresh a DataTable instead of navigating (e.g. stock adjustments).
        const table = form.dataset.geniTable ? document.querySelector(form.dataset.geniTable) : null;
        if (table && window.jQuery?.fn?.dataTable?.isDataTable(table)) {
            window.jQuery(table).DataTable().ajax.reload(null, false);
            toast('success', message);
            return;
        }

        // The server may ask for a different toast level (e.g. a rejected import that still redirects).
        const level = result.data?.level === 'error' ? 'error' : 'success';

        if (result.data?.redirect) {
            flashNext(level, message);
            if (result.data.redirect === window.location.href) {
                // Same page (e.g. settings, profile): reload so changes such as branding apply.
                window.location.reload();
            } else {
                window.location.assign(result.data.redirect);
            }
            return;
        }

        toast('success', message);
        return;
    }

    if (result.status === 422) {
        showErrors(form, result.data?.errors);
        toast('error', result.data?.errors ? t('validation_failed') : (result.data?.message ?? t('validation_failed')));
        return;
    }

    toast('error', errorMessage(result));
}

export function initForms(root = document) {
    root.querySelectorAll('form[data-geni-ajax-form]').forEach((form) => {
        if (form.dataset.geniBound) {
            return;
        }
        form.dataset.geniBound = '1';
        form.addEventListener('submit', (event) => submit(form, event));
    });

    // Classic forms (e.g. login): show the button progress indicator while posting.
    root.querySelectorAll('form[data-geni-submit-indicator]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('[type="submit"]');
            if (button) {
                button.setAttribute('data-kt-indicator', 'on');
                // Disable after the submit has been dispatched so the form still posts.
                setTimeout(() => button.setAttribute('disabled', 'disabled'), 0);
            }
        });
    });

    // "Select all" checkbox per permission group.
    root.querySelectorAll('[data-geni-permission-group]').forEach((group) => {
        const master = group.querySelector('[data-geni-check-group]');
        const boxes = () => [...group.querySelectorAll('input[type="checkbox"]:not([data-geni-check-group]):not(:disabled)')];
        const sync = () => {
            const list = boxes();
            master.checked = list.length > 0 && list.every((box) => box.checked);
            master.indeterminate = !master.checked && list.some((box) => box.checked);
        };

        master?.addEventListener('change', () => {
            boxes().forEach((box) => {
                box.checked = master.checked;
            });
            sync();
        });
        boxes().forEach((box) => box.addEventListener('change', sync));
        if (master) {
            sync();
        }
    });
}
