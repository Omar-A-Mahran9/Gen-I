@extends('admin.layouts.auth')

@section('title', __('admin.auth.title'))

@section('content')
    <form method="POST" action="{{ route('admin.login.store') }}" class="form w-100" novalidate id="geni_login_form" data-geni-submit-indicator>
        @csrf

        <div class="text-center mb-11">
            <h1 class="text-gray-900 fw-bolder mb-3">{{ __('admin.auth.heading') }}</h1>
            <div class="text-gray-500 fw-semibold fs-6">{{ __('admin.auth.subheading', ['name' => settings('app_name')]) }}</div>
        </div>

        @if (session('success'))
            <x-admin.alert type="success" class="mb-8">{{ session('success') }}</x-admin.alert>
        @endif

        <x-admin.form.input name="email" type="email" :label="__('admin.fields.email')" :placeholder="__('admin.auth.email_placeholder')"
                            autocomplete="username" autofocus required dir="ltr" class="form-control-lg" />

        <x-admin.form.input name="password" type="password" :label="__('admin.fields.password')" :placeholder="__('admin.auth.password_placeholder')"
                            autocomplete="current-password" required dir="ltr" class="form-control-lg" />

        <div class="d-flex flex-stack flex-wrap gap-3 fs-base fw-semibold mb-8">
            <label class="form-check form-check-custom form-check-solid form-check-sm">
                <input class="form-check-input" type="checkbox" name="remember" value="1" @checked(old('remember')) />
                <span class="form-check-label text-gray-700">{{ __('admin.fields.remember') }}</span>
            </label>
        </div>

        <div class="d-grid mb-8">
            <x-admin.button type="submit" variant="primary" id="geni_login_submit">{{ __('admin.auth.submit') }}</x-admin.button>
        </div>

        <div class="text-gray-500 text-center fw-semibold fs-7">
            <i class="ki-outline ki-shield-tick fs-5 me-1"></i>{{ __('admin.auth.secure_notice') }}
        </div>
    </form>
@endsection
