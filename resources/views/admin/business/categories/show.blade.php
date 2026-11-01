@extends('admin.layouts.app')

@section('title', $category->displayName())

@section('toolbar')
    <x-admin.toolbar :title="$category->displayName()" :breadcrumbs="[__('admin.categories.title') => route('admin.categories.index'), $category->displayName() => null]">
        @can('update', $category)
            @include('admin.business.partials.status-actions', ['url' => route('admin.categories.status', $category), 'enum' => \App\Enums\ActiveStatus::class,
                'targets' => [$category->status === \App\Enums\ActiveStatus::Active ? 'inactive' : 'active']])
            <x-admin.button :href="route('admin.categories.edit', $category)" icon="ki-pencil" size="sm">{{ __('admin.common.edit') }}</x-admin.button>
        @endcan
        @can('delete', $category)
            <button type="button" class="btn btn-sm btn-light-danger" data-geni-action="{{ route('admin.categories.destroy', $category) }}" data-geni-method="DELETE"
                    data-geni-confirm="{{ __('admin.messages.confirm_delete') }}" data-geni-confirm-danger="true"><i class="ki-outline ki-trash fs-5"></i>{{ __('admin.common.delete') }}</button>
        @endcan
    </x-admin.toolbar>
@endsection

@section('content')
    @php($canEdit = auth()->user()->can('update', $category))
    <div class="row g-5 g-xl-8">
        <div class="col-xl-4">
            <x-admin.card :title="__('admin.categories.details')" icon="ki-category" class="mb-5">
                @include('admin.business.partials.detail', ['label' => __('admin.fields.code'), 'value' => e($category->code), 'ltr' => true])
                @include('admin.business.partials.detail', ['label' => __('admin.fields.name_ar'), 'value' => $category->name_ar ? e($category->name_ar) : null])
                @include('admin.business.partials.detail', ['label' => __('admin.fields.name_en'), 'value' => e($category->name_en), 'ltr' => true])
                @include('admin.business.partials.detail', ['label' => __('admin.fields.parent_id'), 'value' => $category->parent ? '<a href="'.e(route('admin.categories.show', $category->parent)).'">'.e($category->parent->displayName()).'</a>' : e(__('admin.categories.root'))])
                @include('admin.business.partials.detail', ['label' => __('admin.fields.status'), 'value' => view('admin.business.partials.status-badge', ['status' => $category->status])->render()])
                @include('admin.business.partials.detail', ['label' => __('admin.organizations.stat.products'), 'value' => (string) $category->products_count])
            </x-admin.card>
            @if ($category->children->isNotEmpty())
                <x-admin.card :title="__('admin.categories.children')" icon="ki-abstract-26" class="mb-5">
                    @foreach ($category->children as $child)
                        <a href="{{ route('admin.categories.show', $child) }}" class="d-block py-2 text-gray-800 text-hover-primary fw-semibold">{{ $child->displayName() }}</a>
                    @endforeach
                </x-admin.card>
            @endif
        </div>
        <div class="col-xl-8">
            <x-admin.card :title="__('admin.categories.attributes')" icon="ki-setting-4" :subtitle="__('admin.categories.attributes_hint')">
                @if ($canEdit)
                    <x-slot:toolbar>
                        <button type="button" class="btn btn-sm btn-light-primary" data-bs-toggle="modal" data-bs-target="#geni_attribute_modal"><i class="ki-outline ki-plus fs-5"></i>{{ __('admin.categories.add_attribute') }}</button>
                    </x-slot:toolbar>
                @endif
                @if ($category->attributeDefinitions->isEmpty() && $inherited->isEmpty())
                    <x-admin.empty-state :title="__('admin.categories.no_attributes')" compact />
                @else
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-7 gy-3">
                            <thead><tr class="text-gray-500 fw-bold text-uppercase fs-8">
                                <th>{{ __('admin.fields.code') }}</th><th>{{ __('admin.fields.label') }}</th><th>{{ __('admin.fields.value_type') }}</th><th>{{ __('admin.fields.unit') }}</th><th>{{ __('admin.fields.comparison_order') }}</th><th class="text-end">{{ __('admin.common.actions') }}</th>
                            </tr></thead>
                            <tbody>
                                @foreach ($category->attributeDefinitions as $attribute)
                                    <tr>
                                        <td dir="ltr" class="fw-bold">{{ $attribute->code }}</td>
                                        <td>{{ $attribute->displayLabel() }}</td>
                                        <td>@include('admin.business.partials.status-badge', ['status' => $attribute->value_type])</td>
                                        <td dir="ltr">{{ $attribute->unit ?? '—' }}</td>
                                        <td>{{ $attribute->comparison_order }}</td>
                                        <td class="text-end">
                                            @if ($canEdit)
                                                <button type="button" class="btn btn-icon btn-sm btn-light-danger" aria-label="{{ __('admin.common.delete') }}"
                                                        data-geni-action="{{ route('admin.categories.attributes.destroy', [$category, $attribute]) }}" data-geni-method="DELETE" data-geni-reload="true"
                                                        data-geni-confirm="{{ __('admin.messages.confirm_delete') }}" data-geni-confirm-danger="true"><i class="ki-outline ki-trash fs-5"></i></button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                @foreach ($inherited as $attribute)
                                    <tr class="opacity-75">
                                        <td dir="ltr">{{ $attribute->code }}</td>
                                        <td>{{ $attribute->displayLabel() }} <span class="badge badge-light fs-9">{{ __('admin.categories.inherited_from', ['category' => $attribute->category->displayName()]) }}</span></td>
                                        <td>@include('admin.business.partials.status-badge', ['status' => $attribute->value_type])</td>
                                        <td dir="ltr">{{ $attribute->unit ?? '—' }}</td>
                                        <td>{{ $attribute->comparison_order }}</td>
                                        <td></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-admin.card>
        </div>
    </div>

    @if ($canEdit)
        @push('modals')
            <x-admin.modal id="geni_attribute_modal" :title="__('admin.categories.add_attribute')">
                <form method="POST" action="{{ route('admin.categories.attributes.store', $category) }}" data-geni-ajax-form novalidate>
                    @csrf
                    <div class="row">
                        <div class="col-md-6"><x-admin.form.input name="code" id="attr_code_input" :label="__('admin.fields.code')" required maxlength="60" dir="ltr" :hint="__('admin.categories.attribute_code_hint')" /></div>
                        <div class="col-md-6"><x-admin.form.select name="value_type" :label="__('admin.fields.value_type')" required :options="\App\Enums\AttributeValueType::options()" /></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6"><x-admin.form.input name="label_ar" :label="__('admin.fields.label_ar')" maxlength="120" dir="rtl" /></div>
                        <div class="col-md-6"><x-admin.form.input name="label" :label="__('admin.fields.label')" required maxlength="120" dir="ltr" /></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6"><x-admin.form.input name="unit" :label="__('admin.fields.unit')" maxlength="30" dir="ltr" /></div>
                        <div class="col-md-6"><x-admin.form.input name="comparison_order" type="number" min="0" :label="__('admin.fields.comparison_order')" value="0" /></div>
                    </div>
                    <div class="d-flex justify-content-end gap-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('admin.common.cancel') }}</button>
                        <x-admin.button type="submit" icon="ki-check">{{ __('admin.common.save') }}</x-admin.button>
                    </div>
                </form>
            </x-admin.modal>
        @endpush
    @endif
@endsection
