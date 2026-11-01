@extends('admin.layouts.app')

@section('title', $company->legal_name)

@section('toolbar')
    <x-admin.toolbar :title="$company->legal_name" :breadcrumbs="[__('admin.companies.title') => route('admin.companies.index'), $company->legal_name => null]">
        @can('update', $company)
            @include('admin.business.partials.status-actions', ['url' => route('admin.companies.status', $company), 'enum' => \App\Enums\CompanyStatus::class, 'targets' => $transitions])
            <x-admin.button :href="route('admin.companies.edit', $company)" icon="ki-pencil" size="sm">{{ __('admin.common.edit') }}</x-admin.button>
        @endcan
        @can('delete', $company)
            <button type="button" class="btn btn-sm btn-light-danger" data-geni-action="{{ route('admin.companies.destroy', $company) }}" data-geni-method="DELETE"
                    data-geni-confirm="{{ __('admin.messages.confirm_delete') }}" data-geni-confirm-danger="true"><i class="ki-outline ki-trash fs-5"></i>{{ __('admin.common.delete') }}</button>
        @endcan
    </x-admin.toolbar>
@endsection

@section('content')
    @php($canEdit = auth()->user()->can('update', $company))
    <div class="row g-5 g-xl-8">
        <div class="col-xl-4">
            <x-admin.card :title="__('admin.companies.details')" icon="ki-briefcase" class="mb-5">
                @include('admin.business.partials.detail', ['label' => __('admin.fields.status'), 'value' => view('admin.business.partials.status-badge', ['status' => $company->status])->render()])
                @include('admin.business.partials.detail', ['label' => __('admin.fields.organization_id'), 'value' => e($company->organization->name)])
                @include('admin.business.partials.detail', ['label' => __('admin.fields.tax_reference'), 'value' => $company->tax_reference ? e($company->tax_reference) : null, 'ltr' => true])
                @include('admin.business.partials.detail', ['label' => __('admin.fields.account_owner_id'), 'value' => $company->accountOwner ? e($company->accountOwner->name) : null])
                @include('admin.business.partials.detail', ['label' => __('admin.common.created_at'), 'value' => e(format_datetime($company->created_at))])
            </x-admin.card>
        </div>
        <div class="col-xl-8">
            {{-- Members --}}
            <x-admin.card :title="__('admin.companies.members')" icon="ki-people" class="mb-5" :subtitle="__('admin.companies.members_hint')">
                @if ($canEdit)
                    <x-slot:toolbar>
                        <button type="button" class="btn btn-sm btn-light-primary" data-bs-toggle="modal" data-bs-target="#geni_member_modal"><i class="ki-outline ki-plus fs-5"></i>{{ __('admin.companies.add_member') }}</button>
                    </x-slot:toolbar>
                @endif
                @if ($company->members->isEmpty())
                    <x-admin.empty-state :title="__('admin.companies.no_members')" compact />
                @else
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-7 gy-3">
                            <thead><tr class="text-gray-500 fw-bold text-uppercase fs-8">
                                <th>{{ __('admin.fields.user_id') }}</th><th>{{ __('admin.fields.member_role') }}</th><th>{{ __('admin.fields.approval_limit') }}</th><th>{{ __('admin.fields.status') }}</th><th class="text-end">{{ __('admin.common.actions') }}</th>
                            </tr></thead>
                            <tbody>
                                @foreach ($company->members as $member)
                                    <tr>
                                        <td><div class="fw-bold">{{ $member->user->name }}</div><div class="text-muted" dir="ltr">{{ $member->user->email }}</div></td>
                                        <td>@include('admin.business.partials.status-badge', ['status' => $member->member_role])</td>
                                        <td dir="ltr">{{ $member->approval_limit !== null ? number_format((float) $member->approval_limit, 2).' '.$member->currency : '—' }}</td>
                                        <td>@include('admin.business.partials.status-badge', ['status' => $member->status])</td>
                                        <td class="text-end">
                                            @if ($canEdit)
                                                <button type="button" class="btn btn-icon btn-sm btn-light-danger" aria-label="{{ __('admin.common.delete') }}"
                                                        data-geni-action="{{ route('admin.companies.members.destroy', [$company, $member]) }}" data-geni-method="DELETE" data-geni-reload="true"
                                                        data-geni-confirm="{{ __('admin.companies.confirm_remove_member') }}" data-geni-confirm-danger="true"><i class="ki-outline ki-trash fs-5"></i></button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-admin.card>

            {{-- Addresses --}}
            <x-admin.card :title="__('admin.companies.addresses')" icon="ki-geolocation" class="mb-5">
                @if ($canEdit)
                    <x-slot:toolbar>
                        <button type="button" class="btn btn-sm btn-light-primary" data-bs-toggle="modal" data-bs-target="#geni_address_modal"><i class="ki-outline ki-plus fs-5"></i>{{ __('admin.companies.add_address') }}</button>
                    </x-slot:toolbar>
                @endif
                <div class="row g-4">
                    @forelse ($company->addresses as $address)
                        <div class="col-md-6">
                            <div class="border border-dashed border-gray-300 rounded p-5 h-100">
                                <div class="d-flex flex-stack mb-2">
                                    <span class="fw-bold fs-6">{{ $address->label }}</span>
                                    @if ($canEdit)
                                        <button type="button" class="btn btn-icon btn-sm btn-light-danger" aria-label="{{ __('admin.common.delete') }}"
                                                data-geni-action="{{ route('admin.companies.addresses.destroy', [$company, $address]) }}" data-geni-method="DELETE" data-geni-reload="true"
                                                data-geni-confirm="{{ __('admin.messages.confirm_delete') }}" data-geni-confirm-danger="true"><i class="ki-outline ki-trash fs-5"></i></button>
                                    @endif
                                </div>
                                <div class="text-gray-700 fs-7">{{ $address->address_text }}</div>
                                <div class="text-gray-600 fs-7">{{ $address->city }} {{ $address->postal_code }} · {{ $address->country_code }}</div>
                                <div class="text-gray-600 fs-7" dir="ltr">{{ $address->contact_phone }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12"><x-admin.empty-state :title="__('admin.companies.no_addresses')" compact /></div>
                    @endforelse
                </div>
            </x-admin.card>
        </div>
    </div>

    @if ($canEdit)
        @push('modals')
            <x-admin.modal id="geni_member_modal" :title="__('admin.companies.add_member')">
                <form method="POST" action="{{ route('admin.companies.members.store', $company) }}" data-geni-ajax-form novalidate>
                    @csrf
                    @if ($memberCandidates->isEmpty())
                        <x-admin.alert type="warning" class="mb-5">{{ __('admin.companies.no_member_candidates') }}</x-admin.alert>
                    @endif
                    <x-admin.form.select name="user_id" :label="__('admin.fields.user_id')" required id="member_user_input"
                        :placeholder="__('admin.common.select_option')" :options="$memberCandidates->mapWithKeys(fn ($u) => [$u->id => $u->name.' ('.$u->email.')'])->all()" />
                    <x-admin.form.select name="member_role" :label="__('admin.fields.member_role')" required :options="\App\Enums\MemberRole::options()" />
                    <div class="row">
                        <div class="col-md-8"><x-admin.form.input name="approval_limit" type="number" step="0.01" min="0" :label="__('admin.fields.approval_limit')" dir="ltr" :hint="__('admin.companies.approval_limit_hint')" /></div>
                        <div class="col-md-4"><x-admin.form.input name="currency" :label="__('admin.fields.currency')" :value="$company->organization->currency" required maxlength="3" dir="ltr" id="member_currency_input" /></div>
                    </div>
                    <div class="d-flex justify-content-end gap-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('admin.common.cancel') }}</button>
                        <x-admin.button type="submit" icon="ki-check">{{ __('admin.common.save') }}</x-admin.button>
                    </div>
                </form>
            </x-admin.modal>

            <x-admin.modal id="geni_address_modal" :title="__('admin.companies.add_address')">
                <form method="POST" action="{{ route('admin.companies.addresses.store', $company) }}" data-geni-ajax-form novalidate>
                    @csrf
                    <x-admin.form.input name="label" :label="__('admin.fields.label')" required maxlength="60" />
                    <x-admin.form.textarea name="address_text" :label="__('admin.fields.address_text')" required rows="2" />
                    <div class="row">
                        <div class="col-md-6"><x-admin.form.input name="city" :label="__('admin.fields.city')" required maxlength="100" /></div>
                        <div class="col-md-3"><x-admin.form.input name="postal_code" :label="__('admin.fields.postal_code')" maxlength="20" dir="ltr" /></div>
                        <div class="col-md-3"><x-admin.form.input name="country_code" :label="__('admin.fields.country_code')" required maxlength="2" dir="ltr" placeholder="EG" /></div>
                    </div>
                    <x-admin.form.input name="contact_phone" type="tel" :label="__('admin.fields.contact_phone')" required maxlength="32" dir="ltr" />
                    <div class="d-flex justify-content-end gap-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('admin.common.cancel') }}</button>
                        <x-admin.button type="submit" icon="ki-check">{{ __('admin.common.save') }}</x-admin.button>
                    </div>
                </form>
            </x-admin.modal>
        @endpush
    @endif
@endsection
