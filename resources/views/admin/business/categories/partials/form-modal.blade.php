<x-admin.crud-modal id="geni_category_modal" :create-title="__('admin.categories.add')" :edit-title="__('admin.categories.edit')"
    :action="route('admin.categories.store')" :table="$table ?? null">
    @include('admin.business.partials.organization-select')
    <x-admin.form.input name="code" :label="__('admin.fields.code')" required maxlength="50" dir="ltr" :hint="__('admin.categories.code_hint')" />
    @include('admin.business.partials.scoped-select', ['name' => 'parent_id', 'label' => __('admin.fields.parent_id'), 'options' => $parents,
        'text' => fn ($c) => $c->displayName(), 'placeholder' => __('admin.categories.root'), 'hint' => __('admin.categories.parent_hint'), 'excludeSelf' => true])
    <div class="row">
        <div class="col-md-6"><x-admin.form.input name="name_ar" :label="__('admin.fields.name_ar')" maxlength="120" dir="rtl" /></div>
        <div class="col-md-6"><x-admin.form.input name="name_en" :label="__('admin.fields.name_en')" required maxlength="120" dir="ltr" /></div>
    </div>
</x-admin.crud-modal>
