<x-admin.crud-modal id="geni_company_modal" :create-title="__('admin.companies.add')" :edit-title="__('admin.companies.edit')"
    :action="route('admin.companies.store')" :table="$table ?? null">
    @include('admin.business.partials.organization-select')
    <x-admin.form.input name="legal_name" :label="__('admin.fields.legal_name')" required maxlength="200" />
    <x-admin.form.input name="tax_reference" :label="__('admin.fields.tax_reference')" maxlength="80" dir="ltr" :hint="__('admin.companies.tax_hint')" />
    @include('admin.business.partials.scoped-select', ['name' => 'account_owner_id', 'label' => __('admin.fields.account_owner_id'), 'options' => $owners,
        'text' => fn ($u) => $u->name.' ('.$u->email.')', 'placeholder' => __('admin.common.none'), 'hint' => __('admin.companies.owner_hint'), 'select2' => true])
    <div data-geni-create-only>
        <x-admin.alert type="info">{{ __('admin.companies.pending_notice') }}</x-admin.alert>
    </div>
</x-admin.crud-modal>
