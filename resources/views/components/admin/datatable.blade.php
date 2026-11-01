{{--
    Server-side DataTable (initialised by resources/js/admin/datatable.js).
    columns: [['data' => 'name', 'title' => '…', 'orderable' => true, 'class' => '…'], …]
    filters: optional CSS selector of a form whose fields are sent as filters[…].
--}}
@props(['id', 'url', 'columns' => [], 'order' => null, 'filters' => null, 'search' => null, 'pageLength' => 10])
<x-admin.plugins :use="['datatables']" />
<div class="table-responsive">
    <table id="{{ $id }}"
           class="table align-middle table-row-dashed fs-6 gy-5 w-100"
           data-geni-datatable
           data-url="{{ $url }}"
           data-columns='@json($columns)'
           @if ($order) data-order='@json($order)' @endif
           @if ($filters) data-filters="{{ $filters }}" @endif
           @if ($search) data-search="{{ $search }}" @endif
           data-page-length="{{ $pageLength }}">
        <thead>
            <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                @foreach ($columns as $column)
                    <th class="{{ $column['class'] ?? '' }}">{{ $column['title'] ?? '' }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="text-gray-700 fw-semibold"></tbody>
    </table>
</div>
