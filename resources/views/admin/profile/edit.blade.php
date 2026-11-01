@extends('admin.layouts.app')

@section('title', __('admin.profile.title'))

@section('toolbar')
    <x-admin.toolbar :title="__('admin.profile.title')" :subtitle="__('admin.profile.subtitle')" :breadcrumbs="[__('admin.profile.title') => null]" />
@endsection

@section('content')
    <div class="row g-5 g-xl-8">
        <div class="col-xl-4">
            <x-admin.card :title="__('admin.profile.sections.account')" icon="ki-profile-circle" class="mb-5 mb-xl-8">
                <div class="d-flex flex-column align-items-center text-center mb-6">
                    <div class="symbol symbol-90px symbol-circle mb-4">
                        <span class="symbol-label fs-2x fw-bold bg-light-primary text-primary">{{ $user->initials() }}</span>
                    </div>
                    <div class="fs-4 fw-bold text-gray-900">{{ $user->name }}</div>
                    <div class="text-gray-500" dir="ltr">{{ $user->email }}</div>
                </div>
                <div class="d-flex flex-column gap-4 fs-7">
                    <div class="d-flex flex-stack">
                        <span class="text-gray-500">{{ __('admin.fields.status') }}</span>
                        <x-admin.status-badge :active="$user->is_active" />
                    </div>
                    <div class="d-flex flex-stack align-items-start">
                        <span class="text-gray-500">{{ __('admin.fields.roles') }}</span>
                        <div class="text-end">
                            @forelse ($user->roles as $role)
                                <x-admin.role-badge :name="$role->name" />
                            @empty
                                <span class="text-muted">{{ __('admin.users.no_roles') }}</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="d-flex flex-stack">
                        <span class="text-gray-500">{{ __('admin.dashboard.account.last_login') }}</span>
                        <span class="fw-bold">{{ $user->last_login_at ? format_datetime($user->last_login_at) : __('admin.common.never') }}</span>
                    </div>
                </div>
                <div class="form-text mt-6">{{ __('admin.profile.roles_notice') }}</div>
            </x-admin.card>
        </div>

        <div class="col-xl-8">
            <x-admin.card :title="__('admin.profile.sections.details')" icon="ki-user-edit" class="mb-5 mb-xl-8">
                <form method="POST" action="{{ route('admin.profile.update') }}" data-geni-ajax-form novalidate>
                    @csrf
                    @method('PUT')
                    <div class="row">
                        <div class="col-md-6">
                            <x-admin.form.input name="name" :label="__('admin.fields.name')" :value="$user->name" required autocomplete="name" />
                        </div>
                        <div class="col-md-6">
                            <x-admin.form.input name="email" type="email" :label="__('admin.fields.email')" :value="$user->email" required autocomplete="email" dir="ltr" />
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <x-admin.form.select name="locale" :label="__('admin.fields.locale')" :value="$user->locale" :placeholder="__('admin.common.optional')"
                                :options="collect(app(\App\Support\Locale::class)->available())->mapWithKeys(fn ($code) => [$code => config('admin.locales.'.$code.'.name')])->all()" />
                        </div>
                        <div class="col-md-6">
                            <x-admin.form.input name="current_password" type="password" :label="__('admin.fields.current_password')" autocomplete="current-password" dir="ltr"
                                :hint="__('admin.profile.email_change_notice')" id="profile_current_password_input" />
                        </div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <x-admin.button type="submit" icon="ki-check">{{ __('admin.common.save') }}</x-admin.button>
                    </div>
                </form>
            </x-admin.card>

            <x-admin.card :title="__('admin.profile.sections.password')" icon="ki-key" class="mb-5 mb-xl-8">
                <form method="POST" action="{{ route('admin.profile.password') }}" data-geni-ajax-form data-geni-reset-on-success novalidate>
                    @csrf
                    @method('PUT')
                    <x-admin.alert type="info" class="mb-7">{{ __('admin.profile.password_notice') }}</x-admin.alert>
                    <x-admin.form.input name="current_password" type="password" :label="__('admin.fields.current_password')" required autocomplete="current-password" dir="ltr" id="password_current_password_input" />
                    <div class="row">
                        <div class="col-md-6">
                            <x-admin.form.input name="password" type="password" :label="__('admin.fields.password')" required autocomplete="new-password" dir="ltr" :hint="__('admin.users.password_hint')" />
                        </div>
                        <div class="col-md-6">
                            <x-admin.form.input name="password_confirmation" type="password" :label="__('admin.fields.password_confirmation')" required autocomplete="new-password" dir="ltr" />
                        </div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <x-admin.button type="submit" icon="ki-key">{{ __('admin.profile.sections.password') }}</x-admin.button>
                    </div>
                </form>
            </x-admin.card>
        </div>
    </div>
@endsection
