{{-- Shared create/edit user form. $user, $assignableRoles, $canAssignRoles --}}
@php($selectedRoles = old('roles', $user->exists ? $user->roles->pluck('name')->all() : []))
<div class="row g-5 g-xl-8">
    <div class="col-xl-7">
        <x-admin.card :title="__('admin.users.sections.account')" icon="ki-profile-circle" class="mb-5 mb-xl-8">
            <x-admin.form.input name="name" :label="__('admin.fields.name')" :value="$user->name" required autocomplete="name" maxlength="255" />
            <x-admin.form.input name="email" type="email" :label="__('admin.fields.email')" :value="$user->email" required autocomplete="email" dir="ltr" maxlength="255" />
            <x-admin.form.select name="locale" :label="__('admin.fields.locale')" :value="$user->locale" :placeholder="__('admin.common.optional')"
                :options="collect(app(\App\Support\Locale::class)->available())->mapWithKeys(fn ($code) => [$code => config('admin.locales.'.$code.'.name')])->all()" />

            @unless ($user->exists)
                <div class="row">
                    <div class="col-md-6">
                        <x-admin.form.input name="password" type="password" :label="__('admin.fields.password')" required autocomplete="new-password" dir="ltr" :hint="__('admin.users.password_hint')" />
                    </div>
                    <div class="col-md-6">
                        <x-admin.form.input name="password_confirmation" type="password" :label="__('admin.fields.password_confirmation')" required autocomplete="new-password" dir="ltr" />
                    </div>
                </div>
                <x-admin.form.switch name="is_active" :label="__('admin.fields.is_active')" :checked="true" />
            @endunless
        </x-admin.card>
    </div>

    <div class="col-xl-5">
        <x-admin.card :title="__('admin.users.sections.roles')" icon="ki-shield-tick" class="mb-5 mb-xl-8">
            @if (! $canAssignRoles)
                <x-admin.alert type="warning" class="mb-5">{{ __('admin.users.roles_self_notice') }}</x-admin.alert>
                @forelse ($user->roles as $role)
                    <x-admin.role-badge :name="$role->name" />
                @empty
                    <span class="text-muted">{{ __('admin.users.no_roles') }}</span>
                @endforelse
            @elseif ($assignableRoles->isEmpty())
                <x-admin.empty-state :title="__('admin.users.no_assignable_roles')" compact />
            @else
                <div class="form-text mb-5">{{ __('admin.users.roles_hint') }}</div>
                <input type="hidden" name="roles_present" value="1" />
                @php($lockedRoles = $user->exists ? $user->roles->pluck('name')->diff($assignableRoles->pluck('name')) : collect())
                @if ($lockedRoles->isNotEmpty())
                    <div class="mb-5">
                        @foreach ($lockedRoles as $lockedRole)
                            <x-admin.role-badge :name="$lockedRole" />
                        @endforeach
                        <i class="ki-outline ki-lock fs-6 text-muted"></i>
                    </div>
                @endif
                <div class="d-flex flex-column gap-5" data-geni-field="roles">
                    @foreach ($assignableRoles as $role)
                        <x-admin.form.checkbox name="roles[]" :value="$role->name"
                            :label="\Illuminate\Support\Facades\Lang::has('admin.roles.names.'.$role->name) ? __('admin.roles.names.'.$role->name) : $role->name"
                            :hint="trans_choice('admin.roles.permissions_count', $role->permissions->count(), ['count' => $role->permissions->count(), 'total' => array_sum(array_map('count', config('admin.permissions')))])"
                            :checked="in_array($role->name, $selectedRoles, true)" />
                    @endforeach
                    <div class="invalid-feedback d-block" data-geni-error-for="roles">
                        @error('roles'){{ $message }}@enderror
                        @error('roles.*'){{ $message }}@enderror
                    </div>
                </div>
            @endif
        </x-admin.card>
    </div>
</div>
