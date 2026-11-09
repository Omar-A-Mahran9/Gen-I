<x-admin.crud-modal id="geni_organization_modal" :create-title="__('admin.organizations.add')" :edit-title="__('admin.organizations.edit')"
    :action="route('admin.organizations.store')" :table="$table ?? null">
    <x-admin.form.input name="name" :label="__('admin.fields.name')" required maxlength="160" />
    <div class="row">
        <div class="col-md-7">
            <x-admin.form.select name="timezone" :label="__('admin.fields.timezone')" :value="config('admin.organization_defaults.timezone')" required select2
                :options="array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers())" :hint="__('admin.organizations.timezone_hint')" />
        </div>
        <div class="col-md-5">
            <x-admin.form.input name="currency" :label="__('admin.fields.currency')" :value="config('admin.organization_defaults.currency')" required maxlength="3" dir="ltr" :hint="__('admin.organizations.currency_hint')" />
        </div>
    </div>
</x-admin.crud-modal>
