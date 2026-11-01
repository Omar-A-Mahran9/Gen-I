/**
 * Server-side DataTables for <table data-geni-datatable> (see <x-admin.datatable>).
 * Talks to App\Support\DataTable on the backend.
 */
import { errorMessage, t, toast } from './ui';

function language() {
    const dt = window.GENI?.config?.i18n?.datatable ?? {};

    return {
        processing: dt.processing,
        search: dt.search,
        lengthMenu: dt.lengthMenu,
        info: dt.info,
        infoEmpty: dt.infoEmpty,
        infoFiltered: dt.infoFiltered,
        zeroRecords: dt.zeroRecords,
        emptyTable: dt.emptyTable,
        paginate: { first: dt.first, last: dt.last, next: dt.next, previous: dt.previous },
    };
}

function debounce(callback, wait = 350) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => callback(...args), wait);
    };
}

function init(element) {
    const $ = window.jQuery;
    if (!$?.fn?.DataTable || $.fn.dataTable.isDataTable(element)) {
        return;
    }

    const columns = JSON.parse(element.dataset.columns || '[]').map((column) => ({
        data: column.data,
        name: column.data,
        orderable: column.orderable !== false,
        searchable: false,
        className: column.class || '',
    }));
    const filtersForm = element.dataset.filters ? document.querySelector(element.dataset.filters) : null;

    const table = $(element).DataTable({
        processing: true,
        serverSide: true,
        searchDelay: 400,
        pageLength: Number(element.dataset.pageLength || 10),
        lengthMenu: [10, 25, 50, 100],
        order: JSON.parse(element.dataset.order || '[]'),
        columns,
        language: language(),
        dom: "<'table-responsive'tr>"
            + "<'row'<'col-sm-12 col-md-5 d-flex align-items-center justify-content-center justify-content-md-start'li>"
            + "<'col-sm-12 col-md-7 d-flex align-items-center justify-content-center justify-content-md-end'p>>",
        ajax: {
            url: element.dataset.url,
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            data: (payload) => {
                if (filtersForm) {
                    payload.filters = {};
                    new FormData(filtersForm).forEach((value, key) => {
                        payload.filters[key] = value;
                    });
                }
            },
            error: (xhr) => {
                toast('error', errorMessage({ status: xhr.status, data: xhr.responseJSON ?? {} }));
                element.querySelector('tbody').innerHTML = `<tr><td colspan="${columns.length}" class="text-center text-muted py-10">${t('error_generic')}</td></tr>`;
            },
        },
        drawCallback: () => {
            // Re-bind Metronic dropdown menus rendered inside rows.
            if (window.KTMenu) {
                window.KTMenu.createInstances();
            }
        },
    });

    if (element.dataset.search) {
        const search = document.querySelector(element.dataset.search);
        search?.addEventListener('input', debounce(() => table.search(search.value).draw()));
    }

    if (filtersForm) {
        // jQuery delegation also catches change events triggered by Select2.
        $(filtersForm).on('change', 'select, input:not([type="search"])', () => table.ajax.reload());
    }

    window.GENI.tables = window.GENI.tables || {};
    window.GENI.tables[element.id] = table;
}

export function initDataTables(root = document) {
    root.querySelectorAll('table[data-geni-datatable]').forEach(init);
}
