<?php

namespace Tests\Feature\Api;

use App\Models\Brand;
use App\Models\Organization;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Concerns\BuildsBusinessData;
use Tests\TestCase;

/**
 * One response format for /api/v1 (docs/api-contracts.md §1–2): status codes, envelopes,
 * request IDs, pagination and allow-listed filtering/sorting.
 */
class ApiConventionsTest extends TestCase
{
    use BuildsBusinessData, RefreshDatabase;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::factory()->create();
    }

    public function test_paginated_lists_share_one_structure(): void
    {
        $this->apiAs($this->staffOf($this->org, ['catalog.view']));
        Brand::factory()->count(3)->create(['organization_id' => $this->org->id]);

        $this->getJson('/api/v1/brands?per_page=2&page=2&sort=-name')
            ->assertOk()
            ->assertJsonStructure(['success', 'data' => [['id', 'name', 'status']], 'links' => ['first', 'last', 'prev', 'next'], 'meta' => ['current_page', 'per_page', 'total', 'last_page'], 'message'])
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(1, 'data')
            ->assertHeader('X-Request-Id');
    }

    public function test_unknown_filters_sorts_and_bad_paging_are_validation_errors(): void
    {
        $this->apiAs($this->staffOf($this->org, ['catalog.view']));

        $this->getJson('/api/v1/brands?filter[colour]=red', ['Accept-Language' => 'en'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonPath('errors.filter.0', 'Unsupported filter: colour. Allowed filters: status, organization_id.');

        $this->getJson('/api/v1/brands?filter=active')->assertStatus(422)->assertJsonStructure(['errors' => ['filter']]);
        $this->getJson('/api/v1/brands?sort=-password')->assertStatus(422)->assertJsonStructure(['errors' => ['sort']]);

        foreach (['per_page=0', 'per_page=101', 'per_page=abc', 'per_page[]=1'] as $query) {
            $this->getJson("/api/v1/brands?{$query}")->assertStatus(422)->assertJsonStructure(['errors' => ['per_page']]);
        }
        $this->getJson('/api/v1/brands?page=0')->assertStatus(422)->assertJsonStructure(['errors' => ['page']]);

        $this->getJson('/api/v1/brands?filter[status]=active&sort=name&per_page=100&page=1')->assertOk();
    }

    public function test_error_codes_follow_the_contract(): void
    {
        $this->getJson('/api/v1/brands')->assertStatus(401)->assertJsonPath('success', false)->assertJsonPath('code', 'unauthenticated');

        $this->apiAs($this->staffOf($this->org, ['catalog.view']));
        $this->postJson('/api/v1/brands', ['name' => 'X'])->assertStatus(403)->assertJsonPath('code', 'forbidden');
        $this->getJson('/api/v1/brands/999999')->assertStatus(404)->assertJsonPath('code', 'not_found');
        $this->getJson('/api/v1/brands/'.Brand::factory()->create()->id)->assertStatus(404)->assertJsonPath('code', 'not_found');
        $this->patchJson('/api/v1/brands')->assertStatus(405)->assertJsonPath('code', 'method_not_allowed');
    }

    public function test_business_rule_refusals_use_the_same_envelope(): void
    {
        $this->apiAs($this->staffOf($this->org, ['catalog.view', 'catalog.delete']));
        $brand = Brand::factory()->create(['organization_id' => $this->org->id]);
        Product::factory()->create(['organization_id' => $this->org->id, 'brand_id' => $brand->id]);

        $this->deleteJson("/api/v1/brands/{$brand->id}")
            ->assertStatus(422)
            ->assertJsonPath('code', 'business_rule_violation')
            ->assertJsonStructure(['code', 'message', 'request_id'])
            ->assertJsonMissingPath('errors');
    }

    public function test_client_request_id_is_echoed_in_header_and_error_body(): void
    {
        $this->getJson('/api/v1/user', ['X-Request-Id' => 'client-req-123'])
            ->assertHeader('X-Request-Id', 'client-req-123')
            ->assertJsonPath('request_id', 'client-req-123');
    }

    public function test_response_builder_produces_the_lms_response_structure(): void
    {
        $request = Request::create('/api/v1/x');
        $request->attributes->set('request_id', 'rid-1');

        $this->assertSame(['success' => true, 'data' => ['a' => 1], 'message' => 'Done'], ApiResponse::success('Done', ['a' => 1])->getData(true));
        $this->assertSame(['success' => true, 'data' => null, 'message' => 'Done'], ApiResponse::success('Done')->getData(true));
        $this->assertSame(201, ApiResponse::success('Created', [], 201)->getStatusCode());

        $failure = ApiResponse::failure($request, 422, 'Invalid', null, ['x' => ['y']]);
        $this->assertSame(422, $failure->getStatusCode());
        $this->assertSame(['success' => false, 'message' => 'Invalid', 'code' => 'validation_failed', 'errors' => ['x' => ['y']], 'request_id' => 'rid-1'], $failure->getData(true));
        $this->assertSame(['success' => false, 'message' => 'Bad', 'code' => 'bad_request', 'request_id' => 'rid-1'], ApiResponse::failure($request, 400, 'Bad')->getData(true));

        foreach ([401 => 'unauthenticated', 403 => 'forbidden', 404 => 'not_found', 409 => 'conflict', 413 => 'payload_too_large', 415 => 'unsupported_media_type', 422 => 'validation_failed', 429 => 'rate_limited', 500 => 'server_error', 418 => 'http_error'] as $status => $code) {
            $this->assertSame($code, ApiResponse::codeForStatus($status));
        }
    }

    public function test_single_resources_actions_and_deletes_use_the_success_structure(): void
    {
        $this->apiAs($this->staffOf($this->org, ['catalog.view', 'catalog.create', 'catalog.update', 'catalog.delete']));

        $id = $this->postJson('/api/v1/brands', ['name' => 'Example'], ['Accept-Language' => 'en'])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Created successfully.')
            ->assertJsonPath('data.name', 'Example')
            ->json('data.id');

        $this->getJson("/api/v1/brands/{$id}")->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.id', $id);
        $this->deleteJson("/api/v1/brands/{$id}", [], ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertExactJson(['success' => true, 'data' => null, 'message' => 'تم الحذف بنجاح.']);
    }
}
