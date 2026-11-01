<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blueprint S2 §9.3–9.4: policy_versions (needed by variants/prices),
 * categories, brands, products, product_variants, attribute_defs,
 * variant_attributes, media_assets and product_media.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('policy_code', 60);
            $table->unsignedInteger('version');
            $table->json('rule_body');
            $table->string('status', 24)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'policy_code', 'version']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name_en', 120);
            $table->string('name_ar', 120)->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'parent_id']);
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('name_normalized', 100);
            $table->string('status', 24)->default('active');
            $table->timestamps();

            $table->unique(['organization_id', 'name_normalized']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->string('model_code', 100);
            $table->string('name_en', 200);
            $table->string('name_ar', 200)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 24)->default('draft');
            $table->text('source_reference');
            $table->timestamps();

            $table->unique(['organization_id', 'brand_id', 'model_code']);
            $table->index(['organization_id', 'status']);
            $table->index(['category_id', 'status']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('sku', 80);
            $table->string('variant_name', 160);
            $table->string('item_kind', 24);
            $table->string('condition', 24);
            $table->foreignId('warranty_policy_id')->nullable()->constrained('policy_versions')->restrictOnDelete();
            $table->string('status', 24)->default('active');
            $table->timestamps();

            $table->unique(['organization_id', 'sku']);
            $table->index(['product_id', 'status']);
        });

        Schema::create('attribute_defs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('code', 60);
            $table->string('label', 120);
            $table->string('label_ar', 120)->nullable();
            $table->string('value_type', 20);
            $table->string('unit', 30)->nullable();
            $table->integer('comparison_order')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'code']);
        });

        Schema::create('variant_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained('attribute_defs')->restrictOnDelete();
            $table->text('text_value')->nullable();
            $table->decimal('number_value', 18, 6)->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->text('source_reference');
            $table->timestamp('verified_at');
            $table->timestamps();

            $table->unique(['variant_id', 'attribute_id']);
        });

        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('object_key', 500);
            $table->string('mime_type', 100);
            $table->char('sha256', 64);
            $table->unsignedBigInteger('byte_size');
            $table->string('classification', 24)->default('public');
            $table->string('scan_status', 24)->default('pending');
            $table->text('rights_reference')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'sha256']);
        });

        Schema::create('product_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('asset_id')->constrained('media_assets')->restrictOnDelete();
            $table->text('alt_text');
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'asset_id']);
            $table->index(['product_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_media');
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('variant_attributes');
        Schema::dropIfExists('attribute_defs');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('policy_versions');
    }
};
