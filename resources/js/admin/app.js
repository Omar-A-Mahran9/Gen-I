/**
 * GEN-I admin entry point (Vite). Runs after the Metronic global bundles,
 * so jQuery, Bootstrap, toastr, SweetAlert2, Select2 and KT* components are available.
 */
import { initActions } from './actions';
import { initDataTables } from './datatable';
import { clearErrors, initForms, showErrors } from './forms';
import { request } from './http';
import { initInventory } from './inventory';
import { initSelect2Ajax } from './select2';
import { confirmDialog, showPendingFlash, toast } from './ui';

window.GENI = Object.assign(window.GENI || {}, {
    request,
    toast,
    confirm: confirmDialog,
    forms: { clearErrors, showErrors },
    tables: (window.GENI && window.GENI.tables) || {},
});

if (window.jQuery) {
    window.jQuery.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': window.GENI.config?.csrfToken ?? '' },
    });
}

function boot() {
    initSelect2Ajax();
    initForms();
    initDataTables();
    showPendingFlash();
}

initActions();
initInventory();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
