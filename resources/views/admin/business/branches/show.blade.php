@extends('admin.layouts.app')

@section('title', $branch->name)

@section('toolbar')
    <x-admin.toolbar :title="$branch->name" :breadcrumbs="[__('admin.branches.title') => route('admin.branches.index'), $branch->name => null]">
        @can('update', $branch)
            @include('admin.business.partials.status-actions', ['url' => route('admin.branches.status', $branch), 'enum' => \App\Enums\ActiveStatus::class,
                'targets' => [$branch->status === \App\Enums\ActiveStatus::Active ? 'inactive' : 'active']])
            <x-admin.button :href="route('admin.branches.edit', $branch)" icon="ki-pencil" size="sm">{{ __('admin.common.edit') }}</x-admin.button>
        @endcan
        @can('delete', $branch)
            <button type="button" class="btn btn-sm btn-light-danger" data-geni-action="{{ route('admin.branches.destroy', $branch) }}" data-geni-method="DELETE"
                    data-geni-confirm="{{ __('admin.messages.confirm_delete') }}" data-geni-confirm-danger="true"><i class="ki-outline ki-trash fs-5"></i>{{ __('admin.common.delete') }}</button>
        @endcan
    </x-admin.toolbar>
@endsection

@section('content')
    <div class="row g-5 g-xl-8">
        <div class="col-xl-4">
            <x-admin.card :title="__('admin.branches.details')" icon="ki-shop" class="mb-5">
                @include('admin.business.partials.detail', ['label' => __('admin.fields.code'), 'value' => e($branch->code), 'ltr' => true])
                @include('admin.business.partials.detail', ['label' => __('admin.fields.status'), 'value' => view('admin.business.partials.status-badge', ['status' => $branch->status])->render()])
                @include('admin.business.partials.detail', ['label' => __('admin.fields.organization_id'), 'value' => e($branch->organization->name)])
                @include('admin.business.partials.detail', ['label' => __('admin.fields.address_text'), 'value' => e($branch->address_text)])
                @include('admin.business.partials.detail', ['label' => __('admin.fields.city'), 'value' => $branch->city ? e($branch->city) : null])
                @include('admin.business.partials.detail', ['label' => __('admin.fields.phone'), 'value' => $branch->phone ? e($branch->phone) : null, 'ltr' => true])
                @include('admin.business.partials.detail', ['label' => __('admin.fields.email'), 'value' => $branch->email ? e($branch->email) : null, 'ltr' => true])
                @if ($branch->directionsUrl())
                    <a href="{{ $branch->directionsUrl() }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-light-primary w-100 mt-5"><i class="ki-outline ki-map fs-4"></i>{{ __('admin.branches.directions') }}</a>
                @endif
            </x-admin.card>
            <x-admin.card :title="__('admin.fields.opening_hours')" icon="ki-time" class="mb-5">
                @foreach (\App\Models\Branch::DAYS as $day)
                    @php($intervals = $branch->opening_hours[$day] ?? [])
                    @include('admin.business.partials.detail', [
                        'label' => __('admin.days.'.$day),
                        'value' => $intervals === [] ? '<span class="badge badge-light-danger">'.e(__('admin.branches.closed')).'</span>'
                            : collect($intervals)->map(fn ($i) => '<span dir="ltr">'.e($i['open']).' – '.e($i['close']).'</span>')->implode('<br>'),
                    ])
                @endforeach
            </x-admin.card>
        </div>
        <div class="col-xl-8">
            <div class="row g-5 mb-5">
                <div class="col-4"><x-admin.stat-card :label="__('admin.branches.stock_items')" :value="(int) $stockTotals->items" icon="ki-package" /></div>
                <div class="col-4"><x-admin.stat-card :label="__('admin.fields.on_hand')" :value="(int) $stockTotals->on_hand" icon="ki-cube-2" color="success" /></div>
                <div class="col-4"><x-admin.stat-card :label="__('admin.fields.reserved')" :value="(int) $stockTotals->reserved" icon="ki-lock-2" color="warning" /></div>
            </div>
            @if ($stock !== null)
                <x-admin.card :title="__('admin.branches.recent_stock')" icon="ki-package">
                    <x-slot:toolbar>
                        <a href="{{ route('admin.inventory.index') }}?branch={{ $branch->id }}" class="btn btn-sm btn-light">{{ __('admin.menu.stock') }}</a>
                    </x-slot:toolbar>
                    @if ($stock->isEmpty())
                        <x-admin.empty-state :title="__('admin.inventory.empty')" compact />
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-7 gy-3">
                                <thead><tr class="text-gray-500 fw-bold text-uppercase fs-8"><th>{{ __('admin.fields.sku') }}</th><th>{{ __('admin.fields.on_hand') }}</th><th>{{ __('admin.fields.reserved') }}</th><th>{{ __('admin.inventory.available') }}</th><th>{{ __('admin.common.updated_at') }}</th></tr></thead>
                                <tbody>
                                    @foreach ($stock as $row)
                                        <tr>
                                            <td><span class="fw-bold" dir="ltr">{{ $row->variant->sku }}</span><div class="text-muted">{{ $row->variant->product->displayName() }}</div></td>
                                            <td class="fw-bold">{{ $row->on_hand }}</td>
                                            <td>{{ $row->reserved }}</td>
                                            <td><span class="badge {{ $row->available() > 0 ? 'badge-light-success' : 'badge-light-danger' }}">{{ $row->available() }}</span></td>
                                            <td>{{ format_datetime($row->source_updated_at) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-admin.card>
            @endif
        </div>
    </div>
@endsection
