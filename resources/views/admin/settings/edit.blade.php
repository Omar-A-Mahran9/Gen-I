@extends('admin.layouts.app')

@section('title', __('admin.settings.title'))

@section('toolbar')
    <x-admin.toolbar :title="__('admin.settings.title')" :subtitle="__('admin.settings.subtitle')" :breadcrumbs="[__('admin.settings.title') => null]" />
@endsection

@section('content')
    @php($canUpdate = auth()->user()->can('settings.update'))
    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" data-geni-ajax-form novalidate>
        @csrf
        @method('PUT')
        <fieldset @disabled(! $canUpdate)>
            <div class="row g-5 g-xl-8">
                <div class="col-xl-6">
                    <x-admin.card :title="__('admin.settings.sections.general')" icon="ki-setting-2" class="mb-5 mb-xl-8">
                        <x-admin.form.input name="app_name" :label="__('admin.settings.fields.app_name')" :value="$settings['app_name']" required maxlength="100" />
                        <x-admin.form.input name="contact_email" type="email" :label="__('admin.settings.fields.contact_email')" :value="$settings['contact_email']" dir="ltr" :hint="__('admin.settings.help.contact')" />
                        <x-admin.form.input name="contact_phone" type="tel" :label="__('admin.settings.fields.contact_phone')" :value="$settings['contact_phone']" dir="ltr" maxlength="30" />
                    </x-admin.card>

                    <x-admin.card :title="__('admin.settings.sections.localization')" icon="ki-global" class="mb-5 mb-xl-8">
                        <div class="fv-row mb-7" data-geni-field="available_locales">
                            <div class="form-label fw-semibold fs-6 required">{{ __('admin.settings.fields.available_locales') }}</div>
                            <div class="d-flex flex-wrap gap-6">
                                @foreach (config('admin.locales') as $code => $meta)
                                    <x-admin.form.checkbox name="available_locales[]" :value="$code" :label="$meta['name']" :hint="$meta['dir'] === 'rtl' ? 'RTL' : 'LTR'"
                                        :checked="in_array($code, old('available_locales', $settings['available_locales']), true)" />
                                @endforeach
                            </div>
                            <div class="invalid-feedback d-block" data-geni-error-for="available_locales">
                                @error('available_locales'){{ $message }}@enderror @error('available_locales.*'){{ $message }}@enderror
                            </div>
                        </div>
                        <x-admin.form.select name="default_locale" :label="__('admin.settings.fields.default_locale')" :value="$settings['default_locale']" required
                            :options="collect(config('admin.locales'))->map(fn ($meta) => $meta['name'])->all()" />
                        <x-admin.form.select name="timezone" :label="__('admin.settings.fields.timezone')" :value="$settings['timezone']" required select2
                            :options="array_combine($timezones, $timezones)" :hint="__('admin.settings.help.timezone')" />
                        <div class="row">
                            <div class="col-md-6">
                                <x-admin.form.select name="date_format" :label="__('admin.settings.fields.date_format')" :value="$settings['date_format']" required
                                    :options="collect($dateFormats)->mapWithKeys(fn ($f) => [$f => now()->translatedFormat($f).'  ('.$f.')'])->all()" />
                            </div>
                            <div class="col-md-6">
                                <x-admin.form.select name="time_format" :label="__('admin.settings.fields.time_format')" :value="$settings['time_format']" required
                                    :options="collect($timeFormats)->mapWithKeys(fn ($f) => [$f => now()->translatedFormat($f).'  ('.$f.')'])->all()" />
                            </div>
                        </div>
                    </x-admin.card>
                </div>

                <div class="col-xl-6">
                    <x-admin.card :title="__('admin.settings.sections.branding')" icon="ki-picture" class="mb-5 mb-xl-8">
                        <x-admin.alert type="info" class="mb-7">{{ __('admin.settings.help.placeholder') }}</x-admin.alert>
                        <div class="row">
                            <div class="col-md-6">
                                <x-admin.form.image-upload name="logo" :label="__('admin.settings.fields.logo')" :url="$assetUrls['logo']" :custom="$customAssets['logo']"
                                    :hint="__('admin.settings.help.logo', ['size' => config('admin.uploads.logo_max_kb')])" />
                            </div>
                            <div class="col-md-6">
                                <x-admin.form.image-upload name="logo_dark" :label="__('admin.settings.fields.logo_dark')" :url="$assetUrls['logo_dark']" :custom="$customAssets['logo_dark']"
                                    :dark="true" :hint="__('admin.settings.help.logo', ['size' => config('admin.uploads.logo_max_kb')])" />
                            </div>
                        </div>
                        <x-admin.form.image-upload name="favicon" :label="__('admin.settings.fields.favicon')" :url="$assetUrls['favicon']" :custom="$customAssets['favicon']"
                            accept="image/png,image/x-icon,image/vnd.microsoft.icon,.ico" size="w-75px h-75px"
                            :hint="__('admin.settings.help.favicon', ['size' => config('admin.uploads.favicon_max_kb')])" />
                    </x-admin.card>

                    <x-admin.card :title="__('admin.settings.sections.appearance')" icon="ki-night-day" class="mb-5 mb-xl-8">
                        <x-admin.form.radio-group name="default_theme" :label="__('admin.settings.fields.default_theme')" :value="$settings['default_theme']" required
                            :hint="__('admin.settings.help.default_theme')"
                            :options="[
                                'light' => ['label' => __('admin.common.light'), 'icon' => 'ki-night-day'],
                                'dark' => ['label' => __('admin.common.dark'), 'icon' => 'ki-moon'],
                                'system' => ['label' => __('admin.common.system'), 'icon' => 'ki-screen'],
                            ]" />
                    </x-admin.card>
                </div>
            </div>
        </fieldset>

        @if ($canUpdate)
            <div class="d-flex justify-content-end gap-3">
                <x-admin.button type="submit" icon="ki-check">{{ __('admin.common.save') }}</x-admin.button>
            </div>
        @endif
    </form>
@endsection
