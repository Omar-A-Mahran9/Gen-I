<x-admin.crud-modal id="geni_price_list_modal" :create-title="__('admin.pricing.add')" :edit-title="__('admin.pricing.edit')"
    :action="route('admin.price-lists.store')" :table="$table ?? null">
    @include('admin.business.partials.organization-select')
    <div class="row">
        <div class="col-md-8"><x-admin.form.input name="code" :label="__('admin.fields.code')" required maxlength="40" dir="ltr" :hint="__('admin.pricing.code_hint')" /></div>
        <div class="col-md-4"><x-admin.form.input name="currency" :label="__('admin.fields.currency')" :value="config('admin.organization_defaults.currency')" required maxlength="3" dir="ltr" :hint="__('admin.pricing.currency_hint')" /></div>
    </div>
    <x-admin.alert type="info">{{ __('admin.pricing.contract_notice') }}</x-admin.alert>
</x-admin.crud-modal>
