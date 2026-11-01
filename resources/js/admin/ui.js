/**
 * UI helpers: translations, toasts (toastr), confirmations (SweetAlert2), loading state.
 * One notification strategy for the whole admin: toastr for results, SweetAlert2 for confirmations.
 */
const FLASH_KEY = 'geni.flash';

export function t(key, fallback = '') {
    return key.split('.').reduce((value, part) => (value && value[part] !== undefined ? value[part] : undefined), window.GENI?.config?.i18n ?? {}) ?? fallback;
}

export function isRtl() {
    return document.documentElement.getAttribute('dir') === 'rtl';
}

export function toast(type, message) {
    if (!message) {
        return;
    }

    if (typeof window.toastr === 'undefined') {
        window.alert(message);
        return;
    }

    window.toastr.options = {
        closeButton: true,
        progressBar: true,
        newestOnTop: true,
        rtl: isRtl(),
        positionClass: isRtl() ? 'toastr-top-left' : 'toastr-top-right',
        timeOut: type === 'error' ? 7000 : 4000,
    };

    (window.toastr[type] ?? window.toastr.info)(message);
}

/** Show a toast on the next page load (survives a redirect). */
export function flashNext(type, message) {
    try {
        sessionStorage.setItem(FLASH_KEY, JSON.stringify({ type, message }));
    } catch (error) {
        // Storage unavailable: the message is simply not carried over.
    }
}

export function showPendingFlash() {
    const flash = window.GENI?.config?.flash ?? {};
    Object.entries(flash).forEach(([type, message]) => toast(type, message));

    try {
        const stored = sessionStorage.getItem(FLASH_KEY);
        if (stored) {
            sessionStorage.removeItem(FLASH_KEY);
            const { type, message } = JSON.parse(stored);
            toast(type, message);
        }
    } catch (error) {
        // Ignore malformed or unavailable storage.
    }
}

/**
 * @returns {Promise<boolean>}
 */
export async function confirmDialog(message, { danger = false } = {}) {
    if (typeof window.Swal === 'undefined') {
        return window.confirm(message);
    }

    const result = await window.Swal.fire({
        title: t('confirm_title'),
        text: message,
        icon: danger ? 'warning' : 'question',
        showCancelButton: true,
        buttonsStyling: false,
        reverseButtons: isRtl(),
        confirmButtonText: t('confirm_button'),
        cancelButtonText: t('cancel_button'),
        customClass: {
            confirmButton: danger ? 'btn btn-danger' : 'btn btn-primary',
            cancelButton: 'btn btn-light',
        },
    });

    return result.isConfirmed === true;
}

export function setLoading(element, loading) {
    if (!element) {
        return;
    }

    if (loading) {
        element.setAttribute('data-kt-indicator', 'on');
        element.setAttribute('disabled', 'disabled');
    } else {
        element.removeAttribute('data-kt-indicator');
        element.removeAttribute('disabled');
    }
}

/** Translate an HTTP failure into a user-facing message. */
export function errorMessage(result) {
    if (result.status === 0) {
        return t('network_error');
    }
    if (result.status === 419) {
        return t('session_expired');
    }
    if (result.status === 403) {
        return result.data?.message || t('forbidden');
    }

    return result.data?.message || t('error_generic');
}
