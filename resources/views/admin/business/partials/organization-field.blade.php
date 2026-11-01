{{-- Organization selector: only super administrators choose; others are bound to their organization. --}}
@if (! ($model->exists ?? false) && auth()->user()->isSuperAdmin())
    <x-admin.form.select name="organization_id" :label="__('admin.fields.organization_id')" :options="$organizations" :value="old('organization_id', array_key_first($organizations) )" required />
@elseif ($model->exists ?? false)
    @include('admin.business.partials.detail', ['label' => __('admin.fields.organization_id'), 'value' => e($model->organization?->name)])
    <div class="mb-7"></div>
@endif
