{{-- Organization picker inside an add/edit modal: super administrators choose it when creating; it never changes on edit. --}}
@if (auth()->user()->isSuperAdmin())
    <div data-geni-create-only>
        <x-admin.form.select name="organization_id" :label="__('admin.fields.organization_id')" :options="$organizations" :value="array_key_first($organizations)" required />
    </div>
@endif
