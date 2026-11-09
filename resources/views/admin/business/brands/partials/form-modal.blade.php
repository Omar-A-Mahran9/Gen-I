<x-admin.crud-modal id="geni_brand_modal" :create-title="__('admin.brands.add')" :edit-title="__('admin.brands.edit')"
    :action="route('admin.brands.store')" table="#geni_brands_table">
    @include('admin.business.partials.organization-select')
    <x-admin.form.input name="name" :label="__('admin.fields.name')" required maxlength="100" :hint="__('admin.brands.name_hint')" />
</x-admin.crud-modal>
