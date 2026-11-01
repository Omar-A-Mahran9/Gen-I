<?php

namespace App\Http\Requests\Business;

use App\Http\Requests\OrganizationScopedRequest;
use App\Models\PriceList;
use Illuminate\Validation\Rule;

/**
 * Retail price lists. company_id (contract lists) is conditional scope and not accepted.
 */
class PriceListRequest extends OrganizationScopedRequest
{
    protected ?string $routeModel = 'price_list';

    public function authorize(): bool
    {
        $list = $this->record();

        return $list ? $this->user()->can('update', $list) : $this->user()->can('create', PriceList::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->organizationRules() + [
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_\-]+$/',
                Rule::unique('price_lists', 'code')->where('organization_id', $this->organizationId() ?? 0)->ignore($this->record()?->getKey())],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'company_id' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'currency' => strtoupper(trim((string) $this->input('currency'))),
        ]);
    }
}
