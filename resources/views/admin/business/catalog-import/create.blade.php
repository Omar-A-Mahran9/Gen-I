@extends('admin.layouts.app')

@section('title', __('admin.catalog_import.title'))

@section('toolbar')
    <x-admin.toolbar :title="__('admin.catalog_import.title')" :subtitle="__('admin.catalog_import.subtitle')"
        :breadcrumbs="[__('admin.products.title') => route('admin.products.index'), __('admin.catalog_import.title') => null]">
        <x-admin.button :href="route('admin.catalog.import.template')" variant="light-primary" icon="ki-file-down">{{ __('admin.catalog_import.download_template') }}</x-admin.button>
    </x-admin.toolbar>
@endsection

@section('content')
    @if ($report)
        <x-admin.card :title="__('admin.catalog_import.report')" icon="ki-chart-simple" class="mb-5"
            :subtitle="__('admin.catalog_import.report_file', ['name' => $report['file']['name']])">
            <x-slot:toolbar>
                <span class="badge fs-7 fw-bold badge-light-{{ \App\Enums\ImportStatus::from($report['status'])->badgeColor() }}">{{ \App\Enums\ImportStatus::from($report['status'])->label() }}</span>
            </x-slot:toolbar>
            <div class="row g-4 mb-6">
                @foreach (['total_rows', 'created_rows', 'updated_rows', 'unchanged_rows', 'failed_rows', 'duplicate_rows', 'products_created', 'specifications_written'] as $counter)
                    <div class="col-6 col-md-3">
                        <div class="border border-dashed border-gray-300 rounded p-4 h-100">
                            <div class="fs-2 fw-bold text-gray-900">{{ $report['summary'][$counter] }}</div>
                            <div class="fs-7 text-gray-600">{{ __('admin.catalog_import.summary.'.$counter) }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
            @if ($report['errors'] !== [])
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle fs-7 gy-3">
                        <thead>
                            <tr class="text-start text-gray-500 fw-bold text-uppercase gs-0">
                                <th>{{ __('admin.catalog_import.columns.row') }}</th>
                                <th>{{ __('admin.catalog_import.columns.column') }}</th>
                                <th>{{ __('admin.catalog_import.columns.code') }}</th>
                                <th>{{ __('admin.catalog_import.columns.message') }}</th>
                                <th>{{ __('admin.catalog_import.columns.value') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report['errors'] as $error)
                                <tr>
                                    <td>{{ $error['row'] ?? '—' }}</td>
                                    <td dir="ltr" class="text-start"><code>{{ $error['column'] ?? '—' }}</code></td>
                                    <td dir="ltr" class="text-start"><span class="badge badge-light-danger">{{ $error['code'] }}</span></td>
                                    <td>{{ $error['message'] }}</td>
                                    <td dir="ltr" class="text-start text-gray-600">{{ $error['value'] ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($report['errors_truncated'])
                    <x-admin.alert type="warning">{{ __('admin.catalog_import.errors_truncated', ['shown' => count($report['errors']), 'total' => $report['error_count']]) }}</x-admin.alert>
                @endif
            @endif
        </x-admin.card>
    @endif

    <div class="row g-5 g-xl-8">
        <div class="col-xl-5">
            <form method="POST" action="{{ route('admin.catalog.import.store') }}" enctype="multipart/form-data" data-geni-ajax-form novalidate>
                @csrf
                <x-admin.card :title="__('admin.catalog_import.upload')" icon="ki-file-up" class="mb-5">
                    @include('admin.business.partials.organization-field', ['model' => $product])
                    <div class="fv-row mb-7" data-geni-field="file">
                        <label for="catalog_import_file" class="form-label fw-semibold fs-6 required">{{ __('admin.fields.file') }}</label>
                        <input type="file" name="file" id="catalog_import_file" accept=".csv,text/csv" required
                            class="form-control form-control-solid @error('file') is-invalid @enderror" />
                        <div class="form-text">{{ __('admin.catalog_import.file_hint', ['max' => config('admin.catalog_import.max_rows'), 'kb' => config('admin.catalog_import.max_kb')]) }}</div>
                        <div class="invalid-feedback" data-geni-error-for="file">@error('file'){{ $message }}@enderror</div>
                    </div>
                    <x-admin.form.select name="mode" :label="__('admin.fields.mode')" :options="$modes" value="atomic" required :hint="__('admin.catalog_import.mode_hint')" />
                    <div class="mb-7">
                        <x-admin.form.checkbox name="dry_run" :label="__('admin.fields.dry_run')" :hint="__('admin.catalog_import.dry_run_hint')" :checked="(bool) old('dry_run')" />
                    </div>
                    <div class="d-flex justify-content-end">
                        <x-admin.button type="submit" icon="ki-check">{{ __('admin.catalog_import.submit') }}</x-admin.button>
                    </div>
                </x-admin.card>
            </form>
        </div>
        <div class="col-xl-7">
            <x-admin.card :title="__('admin.catalog_import.contract')" icon="ki-document" class="mb-5" :subtitle="__('admin.catalog_import.contract_hint')">
                <h4 class="fs-6 fw-bold mb-3">{{ __('admin.catalog_import.required_columns') }}</h4>
                <div class="d-flex flex-wrap gap-2 mb-6" dir="ltr">
                    @foreach ($requiredColumns as $column)
                        <code class="fs-7">{{ $column }}</code>
                    @endforeach
                </div>
                <h4 class="fs-6 fw-bold mb-3">{{ __('admin.catalog_import.optional_columns') }}</h4>
                <div class="d-flex flex-wrap gap-2 mb-6" dir="ltr">
                    @foreach ($optionalColumns as $column)
                        <code class="fs-7">{{ $column }}</code>
                    @endforeach
                    <code class="fs-7">spec:&lt;attribute_code&gt;</code>
                </div>
                <ul class="text-gray-700 fs-7 ps-5 mb-0">
                    @foreach (__('admin.catalog_import.rules') as $rule)
                        <li class="mb-2">{{ $rule }}</li>
                    @endforeach
                </ul>
            </x-admin.card>
        </div>
    </div>
@endsection
