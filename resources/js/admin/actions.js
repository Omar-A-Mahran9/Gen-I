/**
 * One-click AJAX actions with optional confirmation:
 *   <a href="#" data-geni-action="/admin/users/5/status" data-geni-method="PATCH"
 *      data-geni-confirm="Deactivate?" data-geni-confirm-danger="true"
 *      data-geni-table="#geni_users_table" | data-geni-reload="true">
 *
 * After success: reload the DataTable, reload the page, or follow the server redirect.
 */
import { request } from './http';
import { confirmDialog, errorMessage, flashNext, setLoading, t, toast } from './ui';

async function run(trigger) {
    const { geniAction: url, geniMethod: method = 'POST', geniConfirm: confirmText } = trigger.dataset;

    if (confirmText && !(await confirmDialog(confirmText, { danger: trigger.dataset.geniConfirmDanger === 'true' }))) {
        return;
    }

    setLoading(trigger, true);
    const result = await request(url, { method, data: {} });
    setLoading(trigger, false);

    if (!result.ok) {
        toast('error', errorMessage(result));
        return;
    }

    const message = result.data?.message ?? t('success');
    const table = trigger.dataset.geniTable ? document.querySelector(trigger.dataset.geniTable) : null;

    if (table && window.jQuery?.fn?.dataTable?.isDataTable(table)) {
        window.jQuery(table).DataTable().ajax.reload(null, false);
        toast('success', message);
        return;
    }

    if (trigger.dataset.geniReload === 'true') {
        flashNext('success', message);
        window.location.reload();
        return;
    }

    if (result.data?.redirect) {
        flashNext('success', message);
        window.location.assign(result.data.redirect);
        return;
    }

    toast('success', message);
}

export function initActions() {
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-geni-action]');
        if (!trigger) {
            return;
        }

        event.preventDefault();
        run(trigger);
    });
}
