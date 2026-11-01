{{-- Shared role form. $role, $groups (group => permissions), $selected, $grantable (null = all) --}}
@php($selectedPermissions = old('permissions', $selected))
<div class="row g-5 g-xl-8">
    <div class="col-xl-4">
        <x-admin.card :title="__('admin.roles.columns.name')" icon="ki-shield-tick" class="mb-5">
            <x-admin.form.input name="name" :label="__('admin.fields.name')" :value="$role->name" required maxlength="100" :hint="__('admin.roles.name_hint')" />
            @if ($grantable !== null)
                <x-admin.alert type="info">{{ __('admin.roles.grantable_notice') }}</x-admin.alert>
            @endif
        </x-admin.card>
    </div>
    <div class="col-xl-8">
        <x-admin.card :title="__('admin.fields.permissions')" icon="ki-key" class="mb-5">
            <div class="d-flex flex-column gap-6" data-geni-field="permissions">
                @foreach ($groups as $group => $permissions)
                    <div class="border border-dashed border-gray-300 rounded p-5" data-geni-permission-group>
                        <div class="d-flex flex-stack mb-4">
                            <div class="fw-bold fs-6 text-gray-800">{{ __('admin.permissions.groups.'.$group) }}</div>
                            <label class="form-check form-check-sm form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" data-geni-check-group aria-label="{{ __('admin.roles.select_all') }}" />
                                <span class="form-check-label text-gray-600 fs-7">{{ __('admin.roles.select_all') }}</span>
                            </label>
                        </div>
                        <div class="d-flex flex-wrap gap-6">
                            @foreach ($permissions as $permission)
                                @php($action = \Illuminate\Support\Str::after($permission->name, '.'))
                                @php($canGrant = $grantable === null || in_array($permission->name, $grantable, true))
                                @php($isChecked = in_array($permission->name, $selectedPermissions, true))
                                <x-admin.form.checkbox name="permissions[]" :value="$permission->name"
                                    :label="\Illuminate\Support\Facades\Lang::has('admin.permissions.actions.'.$action) ? __('admin.permissions.actions.'.$action) : $action"
                                    :hint="$permission->name"
                                    :checked="$isChecked"
                                    :disabled="! $canGrant && ! $isChecked" />
                            @endforeach
                        </div>
                    </div>
                @endforeach
                <div class="invalid-feedback d-block" data-geni-error-for="permissions">
                    @error('permissions'){{ $message }}@enderror
                    @error('permissions.*'){{ $message }}@enderror
                </div>
            </div>
        </x-admin.card>
    </div>
</div>
