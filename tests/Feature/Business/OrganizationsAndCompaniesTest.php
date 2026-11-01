<?php

namespace Tests\Feature\Business;

use App\Models\Address;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBusinessData;
use Tests\TestCase;

class OrganizationsAndCompaniesTest extends TestCase
{
    use BuildsBusinessData, RefreshDatabase;

    public function test_super_admin_can_create_view_update_and_suspend_an_organization(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get(route('admin.organizations.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.organizations.create'))->assertOk()->assertSee('Africa/Cairo');

        $this->actingAs($admin)->postJson(route('admin.organizations.store'), ['name' => 'GEN-I Egypt', 'timezone' => 'Africa/Cairo', 'currency' => 'egp'])
            ->assertOk()->assertJsonPath('message', __('admin.messages.created'));

        $org = Organization::query()->where('name', 'GEN-I Egypt')->firstOrFail();
        $this->assertSame('EGP', $org->currency);
        $this->assertSame('active', $org->status->value);
        $this->assertDatabaseHas('audit_logs', ['event' => 'organization.created', 'auditable_id' => $org->id, 'user_id' => $admin->id]);

        $this->actingAs($admin)->get(route('admin.organizations.show', $org))->assertOk()->assertSee('GEN-I Egypt');
        $this->actingAs($admin)->putJson(route('admin.organizations.update', $org), ['name' => 'GEN-I EG', 'timezone' => 'UTC', 'currency' => 'USD'])->assertOk();
        $this->assertSame('UTC', $org->fresh()->timezone);

        $this->actingAs($admin)->patchJson(route('admin.organizations.status', $org).'?status=suspended')->assertOk();
        $this->assertSame('suspended', $org->fresh()->status->value);
        $this->assertDatabaseHas('audit_logs', ['event' => 'organization.status_changed', 'auditable_id' => $org->id]);
    }

    public function test_organization_validation_and_duplicates(): void
    {
        $admin = $this->superAdmin();
        Organization::factory()->create(['name' => 'Taken']);

        $this->actingAs($admin)->postJson(route('admin.organizations.store'), ['name' => 'Taken', 'timezone' => 'Mars/Base', 'currency' => 'EURO'])
            ->assertStatus(422)->assertJsonValidationErrors(['name', 'timezone', 'currency']);
    }

    public function test_organization_with_dependents_cannot_be_deleted(): void
    {
        $admin = $this->superAdmin();
        $org = Organization::factory()->create();
        Branch::factory()->create(['organization_id' => $org->id]);
        $empty = Organization::factory()->create();

        $this->actingAs($admin)->deleteJson(route('admin.organizations.destroy', $org))
            ->assertStatus(422)->assertJsonPath('message', __('admin.errors.has_dependents'));
        $this->actingAs($admin)->deleteJson(route('admin.organizations.destroy', $empty))->assertOk();

        $this->assertDatabaseHas('organizations', ['id' => $org->id]);
        $this->assertDatabaseMissing('organizations', ['id' => $empty->id]);
    }

    public function test_organization_bound_users_only_see_their_own_organization(): void
    {
        $mine = Organization::factory()->create(['name' => 'Mine']);
        $other = Organization::factory()->create(['name' => 'Other']);
        $user = $this->staffOf($mine, ['organizations.view', 'organizations.update', 'organizations.create']);

        $table = $this->actingAs($user)->getJson(route('admin.organizations.index', ['draw' => 1, 'start' => 0, 'length' => 10]));
        $table->assertOk()->assertJsonPath('recordsTotal', 1);

        $this->actingAs($user)->get(route('admin.organizations.show', $other))->assertNotFound();
        $this->actingAs($user)->putJson(route('admin.organizations.update', $other), ['name' => 'x', 'timezone' => 'UTC', 'currency' => 'EGP'])->assertNotFound();
        // Creating organizations is platform-level (super administrators only).
        $this->actingAs($user)->postJson(route('admin.organizations.store'), ['name' => 'New', 'timezone' => 'UTC', 'currency' => 'EGP'])->assertForbidden();
        // Nobody can suspend their own organization.
        $this->actingAs($user)->patchJson(route('admin.organizations.status', $mine).'?status=suspended')
            ->assertStatus(422)->assertJsonPath('message', __('admin.errors.cannot_suspend_own_organization'));
    }

    public function test_company_lifecycle_members_and_addresses(): void
    {
        $org = Organization::factory()->create();
        $staff = $this->staffOf($org, ['companies.view', 'companies.create', 'companies.update', 'companies.delete']);
        $buyer = User::factory()->create();
        $buyer->forceFill(['organization_id' => $org->id])->save();

        $this->actingAs($staff)->postJson(route('admin.companies.store'), ['legal_name' => 'Acme Trading LLC', 'tax_reference' => 'TAX-1', 'account_owner_id' => $staff->id])->assertOk();
        $company = Company::query()->where('legal_name', 'Acme Trading LLC')->firstOrFail();
        $this->assertSame($org->id, $company->organization_id);
        $this->assertSame('pending', $company->status->value);

        // Status transitions follow the documented states.
        $this->actingAs($staff)->patchJson(route('admin.companies.status', $company).'?status=verified')->assertOk();
        $this->actingAs($staff)->patchJson(route('admin.companies.status', $company).'?status=pending')->assertStatus(422);
        $this->assertSame('verified', $company->fresh()->status->value);

        // Members must be users of the same organization; no duplicates.
        $this->actingAs($staff)->postJson(route('admin.companies.members.store', $company), ['user_id' => $buyer->id, 'member_role' => 'buyer', 'currency' => 'EGP', 'approval_limit' => '5000.50'])->assertOk();
        $this->actingAs($staff)->postJson(route('admin.companies.members.store', $company), ['user_id' => $buyer->id, 'member_role' => 'viewer', 'currency' => 'EGP'])
            ->assertStatus(422)->assertJsonValidationErrors(['user_id']);
        $outsider = User::factory()->create();
        $this->actingAs($staff)->postJson(route('admin.companies.members.store', $company), ['user_id' => $outsider->id, 'member_role' => 'buyer', 'currency' => 'EGP'])
            ->assertStatus(422)->assertJsonValidationErrors(['user_id']);
        $this->assertDatabaseHas('company_members', ['company_id' => $company->id, 'user_id' => $buyer->id, 'member_role' => 'buyer', 'approval_limit' => '5000.50']);

        // Addresses.
        $this->actingAs($staff)->postJson(route('admin.companies.addresses.store', $company), ['label' => 'HQ', 'address_text' => '1 Nile St', 'city' => 'Cairo', 'country_code' => 'eg', 'contact_phone' => '+20 100 000 0000'])->assertOk();
        $this->assertDatabaseHas('addresses', ['company_id' => $company->id, 'country_code' => 'EG']);
        $this->actingAs($staff)->postJson(route('admin.companies.addresses.store', $company), ['label' => 'X', 'address_text' => 'Y', 'city' => 'Z', 'country_code' => 'EGY', 'contact_phone' => 'abc'])
            ->assertStatus(422)->assertJsonValidationErrors(['country_code', 'contact_phone']);

        $this->actingAs($staff)->get(route('admin.companies.show', $company))->assertOk()->assertSee($buyer->email)->assertSee('1 Nile St');

        // Deleting a company with members is refused.
        $this->actingAs($staff)->deleteJson(route('admin.companies.destroy', $company))->assertStatus(422);
        $this->assertDatabaseHas('audit_logs', ['event' => 'company.member_added']);
    }

    public function test_company_duplicates_are_scoped_to_the_organization(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        Company::factory()->create(['organization_id' => $orgA->id, 'legal_name' => 'Same Name', 'tax_reference' => 'T-1']);
        $staffA = $this->staffOf($orgA, ['companies.view', 'companies.create']);
        $staffB = $this->staffOf($orgB, ['companies.view', 'companies.create']);

        $this->actingAs($staffA)->postJson(route('admin.companies.store'), ['legal_name' => 'Same Name'])->assertStatus(422)->assertJsonValidationErrors(['legal_name']);
        $this->actingAs($staffA)->postJson(route('admin.companies.store'), ['legal_name' => 'Other', 'tax_reference' => 'T-1'])->assertStatus(422)->assertJsonValidationErrors(['tax_reference']);
        $this->actingAs($staffB)->postJson(route('admin.companies.store'), ['legal_name' => 'Same Name', 'tax_reference' => 'T-1'])->assertOk();
    }

    public function test_submitted_organization_is_ignored_for_organization_bound_users(): void
    {
        $mine = Organization::factory()->create();
        $other = Organization::factory()->create();
        $staff = $this->staffOf($mine, ['companies.view', 'companies.create']);

        $this->actingAs($staff)->postJson(route('admin.companies.store'), ['legal_name' => 'Injected', 'organization_id' => $other->id])->assertOk();

        $this->assertSame($mine->id, Company::query()->where('legal_name', 'Injected')->value('organization_id'));
    }

    public function test_account_owner_must_belong_to_the_same_organization(): void
    {
        $org = Organization::factory()->create();
        $staff = $this->staffOf($org, ['companies.view', 'companies.create']);
        $foreign = $this->staffOf(Organization::factory()->create(), []);

        $this->actingAs($staff)->postJson(route('admin.companies.store'), ['legal_name' => 'X Co', 'account_owner_id' => $foreign->id])
            ->assertStatus(422)->assertJsonValidationErrors(['account_owner_id']);
    }

    public function test_company_api_crud_and_cross_organization_isolation(): void
    {
        $org = Organization::factory()->create();
        $foreignCompany = Company::factory()->create();
        $this->apiAs($this->staffOf($org, ['companies.view', 'companies.create', 'companies.update']));

        $created = $this->postJson('/api/v1/companies', ['legal_name' => 'API Corp'])
            ->assertCreated()->assertJsonPath('data.legal_name', 'API Corp')->assertJsonPath('data.status', 'pending');
        $id = $created->json('data.id');

        $this->postJson("/api/v1/companies/{$id}/addresses", ['label' => 'Main', 'address_text' => 'Street', 'city' => 'Giza', 'country_code' => 'EG', 'contact_phone' => '0100'])->assertCreated();
        $this->getJson("/api/v1/companies/{$id}")->assertOk()->assertJsonPath('data.addresses.0.city', 'Giza');
        $this->patchJson("/api/v1/companies/{$id}/status", ['status' => 'verified'])->assertOk()->assertJsonPath('data.status', 'verified');

        $this->getJson('/api/v1/companies?per_page=1')->assertOk()->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'per_page', 'total']])->assertJsonPath('meta.total', 1);
        $this->getJson("/api/v1/companies/{$foreignCompany->id}")->assertNotFound()->assertJsonPath('error.code', 'not_found');
        $this->deleteJson("/api/v1/companies/{$id}")->assertForbidden()->assertJsonPath('error.code', 'forbidden');
    }

    public function test_company_cascade_deletes_addresses_when_safe(): void
    {
        $org = Organization::factory()->create();
        $staff = $this->staffOf($org, ['companies.view', 'companies.delete']);
        $company = Company::factory()->create(['organization_id' => $org->id]);
        Address::query()->create(['organization_id' => $org->id, 'company_id' => $company->id, 'label' => 'A', 'address_text' => 'B', 'city' => 'C', 'country_code' => 'EG', 'contact_phone' => '1']);

        $this->actingAs($staff)->deleteJson(route('admin.companies.destroy', $company))->assertOk();

        $this->assertDatabaseMissing('companies', ['id' => $company->id]);
        $this->assertDatabaseMissing('addresses', ['company_id' => $company->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'company.deleted']);
    }
}
