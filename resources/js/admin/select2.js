/**
 * Select2 AJAX inputs: <select data-control="select2" data-geni-select2-ajax="/admin/users/search">.
 * The endpoint returns {results: [{id, text}]}. Plain [data-control="select2"] inputs are
 * initialised by Metronic itself.
 */
import { isRtl } from './ui';

export function initSelect2Ajax(root = document) {
    const $ = window.jQuery;
    if (!$?.fn?.select2) {
        return;
    }

    root.querySelectorAll('select[data-geni-select2-ajax]').forEach((element) => {
        if (element.dataset.geniSelect2Bound) {
            return;
        }
        element.dataset.geniSelect2Bound = '1';

        if ($(element).hasClass('select2-hidden-accessible')) {
            $(element).select2('destroy');
        }

        $(element).select2({
            width: '100%',
            dir: isRtl() ? 'rtl' : 'ltr',
            allowClear: element.dataset.allowClear === 'true',
            placeholder: element.dataset.placeholder || '',
            minimumInputLength: 0,
            ajax: {
                url: element.dataset.geniSelect2Ajax,
                dataType: 'json',
                delay: 250,
                headers: { Accept: 'application/json' },
                data: (params) => ({ q: params.term ?? '' }),
                processResults: (data) => ({ results: data.results ?? [] }),
                cache: true,
            },
        });

        // Tell Metronic's KTApp not to re-initialise this element as a plain Select2.
        element.setAttribute('data-kt-initialized', '1');
    });
}
