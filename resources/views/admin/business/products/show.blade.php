@extends('admin.layouts.app')

@section('title', $product->displayName())

@section('toolbar')
    <x-admin.toolbar :title="$product->displayName()" :breadcrumbs="[__('admin.products.title') => route('admin.products.index'), $product->displayName() => null]">
        @can('approve', $product)
            @include('admin.business.partials.status-actions', ['url' => route('admin.products.status', $product), 'enum' => \App\Enums\ProductStatus::class, 'targets' => $transitions])
        @endcan
        @can('update', $product)
            <x-admin.button :href="route('admin.products.edit', $product)" icon="ki-pencil" size="sm">{{ __('admin.common.edit') }}</x-admin.button>
        @endcan
        @can('delete', $product)
            @if ($product->status === \App\Enums\ProductStatus::Draft)
                <button type="button" class="btn btn-sm btn-light-danger" data-geni-action="{{ route('admin.products.destroy', $product) }}" data-geni-method="DELETE"
                        data-geni-confirm="{{ __('admin.messages.confirm_delete') }}" data-geni-confirm-danger="true"><i class="ki-outline ki-trash fs-5"></i>{{ __('admin.common.delete') }}</button>
            @endif
        @endcan
    </x-admin.toolbar>
@endsection

@section('content')
    @php($canEdit = auth()->user()->can('update', $product))
    @php($canApprove = auth()->user()->can('approve', $product))
    <div class="row g-5 g-xl-8">
        <div class="col-xl-4">
            <x-admin.card :title="__('admin.products.details')" icon="ki-handcart" class="mb-5">
                @include('admin.business.partials.detail', ['label' => __('admin.fields.status'), 'value' => view('admin.business.partials.status-badge', ['status' => $product->status])->render()])
                @include('admin.business.partials.detail', ['label' => __('admin.fields.model_code'), 'value' => e($product->model_code), 'ltr' => true])
                @include('admin.business.partials.detail', ['label' => __('admin.fields.category_id'), 'value' => e($product->category->displayName())])
                @include('admin.business.partials.detail', ['label' => __('admin.fields.brand_id'), 'value' => e($product->brand->name)])
                @include('admin.business.partials.detail', ['label' => __('admin.fields.name_en'), 'value' => e($product->name_en), 'ltr' => true])
                @include('admin.business.partials.detail', ['label' => __('admin.fields.name_ar'), 'value' => $product->name_ar ? e($product->name_ar) : null])
                @include('admin.business.partials.detail', ['label' => __('admin.fields.organization_id'), 'value' => e($product->organization->name)])
                <div class="mt-5">
                    <div class="text-gray-500 fs-7 mb-1">{{ __('admin.fields.source_reference') }}</div>
                    <div class="text-gray-800 fs-7" dir="auto">{{ $product->source_reference }}</div>
                </div>
                @if ($product->description)
                    <div class="mt-5">
                        <div class="text-gray-500 fs-7 mb-1">{{ __('admin.fields.description') }}</div>
                        <div class="text-gray-800 fs-7" dir="auto">{!! nl2br(e($product->description)) !!}</div>
                    </div>
                @endif
            </x-admin.card>
            <x-admin.alert type="info" class="mb-5">{{ __('admin.products.lifecycle_hint') }}</x-admin.alert>
        </div>

        <div class="col-xl-8">
            {{-- Variants --}}
            <x-admin.card :title="__('admin.products.variants')" icon="ki-barcode" class="mb-5" :subtitle="__('admin.products.variants_hint')">
                @if ($canEdit)
                    <x-slot:toolbar>
                        <button type="button" class="btn btn-sm btn-light-primary" data-bs-toggle="modal" data-bs-target="#geni_variant_modal"><i class="ki-outline ki-plus fs-5"></i>{{ __('admin.products.add_variant') }}</button>
                    </x-slot:toolbar>
                @endif
                @forelse ($product->variants as $variant)
                    <div class="border border-dashed border-gray-300 rounded p-5 mb-5" data-geni-variant="{{ $variant->sku }}">
                        <div class="d-flex flex-wrap flex-stack gap-3 mb-3">
                            <div>
                                <span class="fw-bold fs-5" dir="ltr">{{ $variant->sku }}</span>
                                <span class="text-gray-700 ms-2">{{ $variant->variant_name }}</span>
                                <div class="mt-1">
                                    @include('admin.business.partials.status-badge', ['status' => $variant->status])
                                    @include('admin.business.partials.status-badge', ['status' => $variant->item_kind])
                                    @include('admin.business.partials.status-badge', ['status' => $variant->condition])
                                    @if ($variant->warrantyPolicy)
                                        <span class="badge badge-light fw-bold" dir="ltr">{{ __('admin.fields.warranty_policy_id') }}: {{ $variant->warrantyPolicy->displayName() }}</span>
                                    @endif
                                </div>
                            </div>
                            @if ($canEdit)
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($variantTransitions[$variant->status->value] as $target)
                                        <button type="button" class="btn btn-sm btn-light"
                                                data-geni-action="{{ route('admin.variants.status', $variant) }}?status={{ $target }}" data-geni-method="PATCH" data-geni-reload="true"
                                                data-geni-confirm="{{ __('admin.messages.confirm_status', ['status' => \App\Enums\VariantStatus::from($target)->label()]) }}">{{ \App\Enums\VariantStatus::from($target)->label() }}</button>
                                    @endforeach
                                    <button type="button" class="btn btn-sm btn-light-primary" data-bs-toggle="collapse" data-bs-target="#geni_specs_{{ $variant->id }}" aria-expanded="false">
                                        <i class="ki-outline ki-setting-4 fs-5"></i>{{ __('admin.products.specifications') }}
                                    </button>
                                    <button type="button" class="btn btn-icon btn-sm btn-light-danger" aria-label="{{ __('admin.common.delete') }}"
                                            data-geni-action="{{ route('admin.variants.destroy', $variant) }}" data-geni-method="DELETE" data-geni-reload="true"
                                            data-geni-confirm="{{ __('admin.messages.confirm_delete') }}" data-geni-confirm-danger="true"><i class="ki-outline ki-trash fs-5"></i></button>
                                </div>
                            @endif
                        </div>

                        {{-- Current specifications --}}
                        @if ($variant->attributeValues->isNotEmpty())
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($variant->attributeValues->sortBy(fn ($v) => $v->attributeDefinition?->comparison_order) as $value)
                                    @php($typed = $value->typedValue())
                                    <span class="badge badge-light-info fw-semibold py-2 px-3">
                                        {{ $value->attributeDefinition->displayLabel() }}:
                                        <span dir="auto">{{ is_bool($typed) ? ($typed ? __('admin.common.yes') : __('admin.common.no')) : $typed }}</span>
                                        {{ $value->attributeDefinition->unit }}
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <div class="text-muted fs-7">{{ __('admin.products.no_specifications') }}</div>
                        @endif

                        {{-- Specification editor --}}
                        @if ($canEdit)
                            <div class="collapse mt-5" id="geni_specs_{{ $variant->id }}">
                                @if ($attributes->isEmpty())
                                    <x-admin.alert type="warning">{{ __('admin.products.no_attribute_definitions') }}</x-admin.alert>
                                @else
                                    @php($existing = $variant->attributeValues->keyBy('attribute_id'))
                                    <form method="POST" action="{{ route('admin.variants.specifications', $variant) }}" data-geni-ajax-form novalidate>
                                        @csrf
                                        @method('PUT')
                                        <div class="table-responsive">
                                            <table class="table align-middle table-row-dashed fs-7 gy-2">
                                                <thead><tr class="text-gray-500 fw-bold fs-8"><th>{{ __('admin.fields.label') }}</th><th>{{ __('admin.fields.value') }}</th><th>{{ __('admin.fields.source_reference') }}</th></tr></thead>
                                                <tbody>
                                                    @foreach ($attributes as $i => $definition)
                                                        @php($current = $existing->get($definition->id))
                                                        <tr>
                                                            <td class="min-w-150px">
                                                                <input type="hidden" name="values[{{ $i }}][attribute_id]" value="{{ $definition->id }}" />
                                                                <div class="fw-bold">{{ $definition->displayLabel() }}</div>
                                                                <div class="text-muted fs-8">@include('admin.business.partials.status-badge', ['status' => $definition->value_type]) {{ $definition->unit }}</div>
                                                            </td>
                                                            <td class="min-w-150px">
                                                                @if ($definition->value_type === \App\Enums\AttributeValueType::Boolean)
                                                                    <select name="values[{{ $i }}][value]" class="form-select form-select-sm form-select-solid">
                                                                        <option value="">—</option>
                                                                        <option value="1" @selected($current && $current->boolean_value === true)>{{ __('admin.common.yes') }}</option>
                                                                        <option value="0" @selected($current && $current->boolean_value === false)>{{ __('admin.common.no') }}</option>
                                                                    </select>
                                                                @else
                                                                    <input type="{{ $definition->value_type === \App\Enums\AttributeValueType::Number ? 'number' : 'text' }}" step="any"
                                                                           name="values[{{ $i }}][value]" value="{{ $current?->typedValue() }}" class="form-control form-control-sm form-control-solid" dir="auto" />
                                                                @endif
                                                                <div class="invalid-feedback d-block" data-geni-error-for="values.{{ $i }}.value"></div>
                                                            </td>
                                                            <td class="min-w-200px">
                                                                <input type="text" name="values[{{ $i }}][source_reference]" value="{{ $current?->source_reference }}" class="form-control form-control-sm form-control-solid" maxlength="2000" dir="auto" />
                                                                <div class="invalid-feedback d-block" data-geni-error-for="values.{{ $i }}.source_reference"></div>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="d-flex justify-content-end"><x-admin.button type="submit" size="sm" icon="ki-check">{{ __('admin.common.save') }}</x-admin.button></div>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </div>
                @empty
                    <x-admin.empty-state :title="__('admin.products.no_variants')" :body="__('admin.products.no_variants_hint')" compact />
                @endforelse
            </x-admin.card>

            {{-- Media --}}
            <x-admin.card :title="__('admin.products.media')" icon="ki-picture" class="mb-5" :subtitle="__('admin.products.media_hint')">
                @if ($canEdit)
                    <x-slot:toolbar>
                        <button type="button" class="btn btn-sm btn-light-primary" data-bs-toggle="modal" data-bs-target="#geni_media_modal"><i class="ki-outline ki-cloud-add fs-5"></i>{{ __('admin.products.upload_image') }}</button>
                    </x-slot:toolbar>
                @endif
                <div class="row g-5">
                    @forelse ($product->media as $placement)
                        <div class="col-6 col-md-4">
                            <div class="border border-gray-300 rounded p-3 h-100 d-flex flex-column">
                                @if ($placement->asset->scan_status !== \App\Enums\ScanStatus::Blocked)
                                    <img src="{{ route('admin.products.media.preview', [$product, $placement]) }}" alt="{{ $placement->alt_text }}" class="w-100 h-125px rounded mb-3" style="object-fit: contain" loading="lazy" />
                                @else
                                    <div class="w-100 h-125px rounded mb-3 bg-light-danger d-flex flex-center"><i class="ki-outline ki-shield-cross fs-2x text-danger"></i></div>
                                @endif
                                <div class="fs-8 text-gray-700 mb-2" dir="auto">{{ $placement->alt_text }}</div>
                                <div class="mb-2">
                                    @include('admin.business.partials.status-badge', ['status' => $placement->asset->scan_status])
                                    @include('admin.business.partials.status-badge', ['status' => $placement->asset->classification])
                                    @if ($placement->variant)<span class="badge badge-light" dir="ltr">{{ $placement->variant->sku }}</span>@endif
                                </div>
                                <div class="mt-auto d-flex flex-wrap gap-1">
                                    @if ($canApprove && $placement->asset->scan_status !== \App\Enums\ScanStatus::Clean)
                                        <button type="button" class="btn btn-sm btn-light-success" data-geni-action="{{ route('admin.products.media.update', [$product, $placement]) }}?scan_status=clean" data-geni-method="PUT" data-geni-reload="true"
                                                data-geni-confirm="{{ __('admin.products.confirm_mark_clean') }}">{{ __('admin.products.mark_clean') }}</button>
                                    @endif
                                    @if ($canApprove && $placement->asset->scan_status !== \App\Enums\ScanStatus::Blocked)
                                        <button type="button" class="btn btn-sm btn-light-warning" data-geni-action="{{ route('admin.products.media.update', [$product, $placement]) }}?scan_status=blocked" data-geni-method="PUT" data-geni-reload="true"
                                                data-geni-confirm="{{ __('admin.products.confirm_block') }}">{{ __('admin.products.block') }}</button>
                                    @endif
                                    @if ($canEdit)
                                        <button type="button" class="btn btn-icon btn-sm btn-light-danger" aria-label="{{ __('admin.common.delete') }}"
                                                data-geni-action="{{ route('admin.products.media.destroy', [$product, $placement]) }}" data-geni-method="DELETE" data-geni-reload="true"
                                                data-geni-confirm="{{ __('admin.messages.confirm_delete') }}" data-geni-confirm-danger="true"><i class="ki-outline ki-trash fs-5"></i></button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12"><x-admin.empty-state :title="__('admin.products.no_media')" compact /></div>
                    @endforelse
                </div>
            </x-admin.card>
        </div>
    </div>

    @if ($canEdit)
        @push('modals')
            <x-admin.modal id="geni_variant_modal" :title="__('admin.products.add_variant')">
                <form method="POST" action="{{ route('admin.products.variants.store', $product) }}" data-geni-ajax-form novalidate>
                    @csrf
                    <div class="row">
                        <div class="col-md-6"><x-admin.form.input name="sku" :label="__('admin.fields.sku')" required maxlength="80" dir="ltr" :hint="__('admin.products.sku_hint')" /></div>
                        <div class="col-md-6"><x-admin.form.input name="variant_name" :label="__('admin.fields.variant_name')" required maxlength="160" /></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6"><x-admin.form.select name="item_kind" :label="__('admin.fields.item_kind')" required :options="\App\Enums\ItemKind::options()" /></div>
                        <div class="col-md-6"><x-admin.form.select name="condition" :label="__('admin.fields.condition')" required :options="\App\Enums\ItemCondition::options()" /></div>
                    </div>
                    <x-admin.form.select name="warranty_policy_id" :label="__('admin.fields.warranty_policy_id')" :placeholder="__('admin.common.none')" :options="$warrantyPolicies->all()" :hint="__('admin.products.warranty_hint')" />
                    <div class="d-flex justify-content-end gap-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('admin.common.cancel') }}</button>
                        <x-admin.button type="submit" icon="ki-check">{{ __('admin.common.save') }}</x-admin.button>
                    </div>
                </form>
            </x-admin.modal>

            <x-admin.modal id="geni_media_modal" :title="__('admin.products.upload_image')">
                <form method="POST" action="{{ route('admin.products.media.store', $product) }}" enctype="multipart/form-data" data-geni-ajax-form novalidate>
                    @csrf
                    <div class="fv-row mb-7" data-geni-field="image">
                        <label for="media_image_input" class="form-label fw-semibold fs-6 required">{{ __('admin.fields.image') }}</label>
                        <input type="file" name="image" id="media_image_input" accept="image/jpeg,image/png,image/webp" class="form-control form-control-solid" required />
                        <div class="form-text">{{ __('admin.products.image_hint', ['size' => config('admin.uploads.product_image_max_kb')]) }}</div>
                        <div class="invalid-feedback d-block" data-geni-error-for="image"></div>
                    </div>
                    <x-admin.form.input name="alt_text" :label="__('admin.fields.alt_text')" required maxlength="300" :hint="__('admin.products.alt_hint')" />
                    <div class="row">
                        <div class="col-md-6">
                            <x-admin.form.select name="variant_id" :label="__('admin.fields.variant_id')" :placeholder="__('admin.products.all_variants')"
                                :options="$product->variants->mapWithKeys(fn ($v) => [$v->id => $v->sku.' — '.$v->variant_name])->all()" />
                        </div>
                        <div class="col-md-6"><x-admin.form.input name="display_order" type="number" min="0" :label="__('admin.fields.display_order')" /></div>
                    </div>
                    <x-admin.form.input name="rights_reference" :label="__('admin.fields.rights_reference')" maxlength="2000" :hint="__('admin.products.rights_hint')" />
                    <div class="d-flex justify-content-end gap-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('admin.common.cancel') }}</button>
                        <x-admin.button type="submit" icon="ki-cloud-add">{{ __('admin.products.upload_image') }}</x-admin.button>
                    </div>
                </form>
            </x-admin.modal>
        @endpush
    @endif
@endsection
