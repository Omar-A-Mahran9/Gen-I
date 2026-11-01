@extends('admin.layouts.app')

@php($isEdit = $branch->exists)
@php($hours = old('opening_hours', $branch->opening_hours ?? []))
@section('title', $isEdit ? __('admin.branches.edit') : __('admin.branches.add'))

@section('toolbar')
    <x-admin.toolbar :title="$isEdit ? __('admin.branches.edit') : __('admin.branches.add')"
        :breadcrumbs="[__('admin.branches.title') => route('admin.branches.index'), ($isEdit ? $branch->name : __('admin.common.create')) => null]" />
@endsection

@section('content')
    <form method="POST" action="{{ $isEdit ? route('admin.branches.update', $branch) : route('admin.branches.store') }}" data-geni-ajax-form novalidate>
        @csrf
        @if ($isEdit) @method('PUT') @endif
        <div class="row g-5 g-xl-8">
            <div class="col-xl-6">
                <x-admin.card :title="__('admin.branches.details')" icon="ki-shop" class="mb-5">
                    @include('admin.business.partials.organization-field', ['model' => $branch])
                    <div class="row">
                        <div class="col-md-4"><x-admin.form.input name="code" :label="__('admin.fields.code')" :value="$branch->code" required maxlength="30" dir="ltr" :hint="__('admin.branches.code_hint')" /></div>
                        <div class="col-md-8"><x-admin.form.input name="name" :label="__('admin.fields.name')" :value="$branch->name" required maxlength="120" /></div>
                    </div>
                    <x-admin.form.textarea name="address_text" :label="__('admin.fields.address_text')" :value="$branch->address_text" required rows="2" />
                    <div class="row">
                        <div class="col-md-4"><x-admin.form.input name="city" :label="__('admin.fields.city')" :value="$branch->city" maxlength="100" /></div>
                        <div class="col-md-4"><x-admin.form.input name="phone" type="tel" :label="__('admin.fields.phone')" :value="$branch->phone" maxlength="32" dir="ltr" /></div>
                        <div class="col-md-4"><x-admin.form.input name="email" type="email" :label="__('admin.fields.email')" :value="$branch->email" maxlength="254" dir="ltr" /></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6"><x-admin.form.input name="latitude" type="number" step="0.0000001" :label="__('admin.fields.latitude')" :value="$branch->latitude" dir="ltr" /></div>
                        <div class="col-md-6"><x-admin.form.input name="longitude" type="number" step="0.0000001" :label="__('admin.fields.longitude')" :value="$branch->longitude" dir="ltr" /></div>
                    </div>
                    <div class="form-text">{{ __('admin.branches.location_hint') }}</div>
                </x-admin.card>
            </div>
            <div class="col-xl-6">
                <x-admin.card :title="__('admin.fields.opening_hours')" icon="ki-time" class="mb-5" :subtitle="__('admin.branches.hours_hint')">
                    <div data-geni-field="opening_hours">
                        @foreach (\App\Models\Branch::DAYS as $day)
                            <div class="d-flex flex-column flex-md-row align-items-md-center gap-3 py-3 border-bottom border-gray-200 border-bottom-dashed">
                                <div class="fw-bold w-md-100px">{{ __('admin.days.'.$day) }}</div>
                                @for ($i = 0; $i < 2; $i++)
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="time" name="opening_hours[{{ $day }}][{{ $i }}][open]" value="{{ $hours[$day][$i]['open'] ?? '' }}" class="form-control form-control-solid form-control-sm w-110px" dir="ltr" aria-label="{{ __('admin.days.'.$day) }} {{ __('admin.branches.open') }} {{ $i + 1 }}" />
                                        <span class="text-muted">–</span>
                                        <input type="time" name="opening_hours[{{ $day }}][{{ $i }}][close]" value="{{ $hours[$day][$i]['close'] ?? '' }}" class="form-control form-control-solid form-control-sm w-110px" dir="ltr" aria-label="{{ __('admin.days.'.$day) }} {{ __('admin.branches.close') }} {{ $i + 1 }}" />
                                    </div>
                                @endfor
                            </div>
                            <div class="invalid-feedback d-block" data-geni-error-for="opening_hours.{{ $day }}"></div>
                        @endforeach
                        <div class="invalid-feedback d-block" data-geni-error-for="opening_hours">@error('opening_hours'){{ $message }}@enderror</div>
                    </div>
                </x-admin.card>
            </div>
        </div>
        <div class="d-flex justify-content-end gap-3">
            <x-admin.button :href="$isEdit ? route('admin.branches.show', $branch) : route('admin.branches.index')" variant="light">{{ __('admin.common.cancel') }}</x-admin.button>
            <x-admin.button type="submit" icon="ki-check">{{ $isEdit ? __('admin.common.save') : __('admin.common.create') }}</x-admin.button>
        </div>
    </form>
@endsection
