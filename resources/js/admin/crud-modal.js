/**
 * Add/edit modals for simple records (LMS crud_modal pattern) — see components/admin/crud-modal.blade.php.
 *
 *   [data-geni-crud-create="#modal"]                         reset the form, POST to the modal's store URL
 *   [data-geni-crud-edit="#modal" data-url data-record]      fill the form from the JSON record, PUT to data-url
 *
 * Inside the modal:
 *   [data-geni-create-only]                 shown (and its inputs enabled) only when creating
 *   select[data-geni-filter-organization]   options carry data-organization; only those of the selected
 *                                           (create) or record (edit) organization stay selectable
 *   select[data-geni-exclude-self]          the option equal to the edited record's id is disabled
 */
import { clearErrors } from './forms';
import { isRtl } from './ui';

const FIELD_SELECTOR = 'input[name]:not([type="hidden"]):not([type="file"]), select[name], textarea[name]';

function jq(element) {
    return window.jQuery && window.jQuery.fn?.select2 && window.jQuery(element).hasClass('select2-hidden-accessible') ? window.jQuery(element) : null;
}

function refreshSelect(select) {
    jq(select)?.trigger('change.select2');
}

function filterByOrganization(form, organizationId) {
    form.querySelectorAll('select[data-geni-filter-organization]').forEach((select) => {
        [...select.options].forEach((option) => {
            if (!option.value || !option.dataset.organization) {
                return;
            }
            const allowed = !organizationId || option.dataset.organization === String(organizationId);
            option.disabled = !allowed;
            option.hidden = !allowed;
            if (!allowed && option.selected) {
                select.value = '';
            }
        });
        refreshSelect(select);
    });
}

function excludeSelf(form, recordId) {
    form.querySelectorAll('select[data-geni-exclude-self] option').forEach((option) => {
        if (recordId && option.value === String(recordId)) {
            option.disabled = true;
            option.hidden = true;
        }
    });
}

function setValue(field, value) {
    if (field.type === 'checkbox' || field.type === 'radio') {
        field.checked = Boolean(value) && String(field.value) === (value === true ? field.value : String(value));
        return;
    }
    field.value = value === null || value === undefined ? '' : String(value);
    refreshSelect(field);
}

function open(trigger, isEdit) {
    const modal = document.querySelector(isEdit ? trigger.dataset.geniCrudEdit : trigger.dataset.geniCrudCreate);
    if (!modal || !window.bootstrap) {
        return;
    }

    const form = modal.querySelector('form');
    const record = isEdit ? JSON.parse(trigger.dataset.record || '{}') : {};

    form.reset();
    clearErrors(form);
    form.querySelectorAll('select option').forEach((option) => {
        option.disabled = false;
        option.hidden = false;
    });

    form.setAttribute('action', isEdit ? trigger.dataset.url : modal.dataset.storeUrl);
    form.querySelector('[data-geni-method]').value = isEdit ? 'PUT' : 'POST';
    modal.querySelector('[data-geni-modal-title]').textContent = isEdit ? modal.dataset.editTitle : modal.dataset.createTitle;
    modal.querySelector('[data-geni-submit-label]').textContent = isEdit ? modal.dataset.editLabel : modal.dataset.createLabel;

    modal.querySelectorAll('[data-geni-create-only]').forEach((section) => {
        section.hidden = isEdit;
        section.querySelectorAll('input, select, textarea').forEach((input) => {
            input.disabled = isEdit;
        });
    });

    if (isEdit) {
        form.querySelectorAll(FIELD_SELECTOR).forEach((field) => {
            if (Object.prototype.hasOwnProperty.call(record, field.name)) {
                setValue(field, record[field.name]);
            }
        });
    } else {
        form.querySelectorAll('select').forEach(refreshSelect);
    }

    const organizationSelect = form.querySelector('select[name="organization_id"]:not(:disabled)');
    filterByOrganization(form, isEdit ? record.organization_id : organizationSelect?.value);
    excludeSelf(form, isEdit ? record.id : null);

    const instance = window.bootstrap.Modal.getOrCreateInstance(modal);
    // Bootstrap ignores show() while the closing animation runs: reopen once it has finished.
    if (instance._isTransitioning && !modal.classList.contains('show')) {
        modal.addEventListener('hidden.bs.modal', () => instance.show(), { once: true });
    } else {
        instance.show();
    }
}

/**
 * Select2 inside a modal must render its dropdown in the modal, or it opens behind it.
 */
function bindSelect2(modal) {
    const $ = window.jQuery;
    if (!$?.fn?.select2) {
        return;
    }

    modal.querySelectorAll('select[data-control="select2"]').forEach((select) => {
        if ($(select).hasClass('select2-hidden-accessible')) {
            $(select).select2('destroy');
        }
        $(select).select2({
            width: '100%',
            dir: isRtl() ? 'rtl' : 'ltr',
            dropdownParent: $(modal),
            placeholder: select.dataset.placeholder || '',
            allowClear: select.dataset.allowClear === 'true',
        });
        select.setAttribute('data-kt-initialized', '1');
    });
}

export function initCrudModals() {
    document.querySelectorAll('[data-geni-crud-modal]').forEach(bindSelect2);

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-geni-crud-create], [data-geni-crud-edit]');
        if (!trigger) {
            return;
        }
        event.preventDefault();
        open(trigger, trigger.hasAttribute('data-geni-crud-edit'));
    });

    // Delegated through jQuery: it receives native changes and the change events Select2 triggers.
    window.jQuery?.(document).on('change', '[data-geni-crud-modal] select[name="organization_id"]', function onOrganizationChange() {
        filterByOrganization(this.form, this.value);
    });
}
