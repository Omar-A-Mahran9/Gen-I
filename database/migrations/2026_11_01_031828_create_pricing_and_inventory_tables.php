<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Blueprint S2 §9.4: price_lists, prices (effective dated), inventory_balances
 * and inventory_movements (immutable ledger). Order/repair links are omitted
 * until those modules exist (DV-19). MySQL CHECK constraints back the
 * service-level invariants (BR03, BR04).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            // Contract price lists are conditional advanced B2B scope: column kept, not exposed (DV-20).
            $table->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();
            $table->char('currency', 3);
            $table->string('status', 24)->default('active');
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('price_list_id')->constrained()->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->unsignedInteger('min_quantity')->default(1);
            $table->decimal('unit_price', 18, 2);
            $table->foreignId('tax_policy_id')->constrained('policy_versions')->restrictOnDelete();
            $table->timestamp('valid_from');
            $table->timestamp('valid_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['price_list_id', 'variant_id', 'min_quantity', 'valid_from'], 'prices_lookup_index');
            $table->index(['variant_id', 'valid_from']);
        });

        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->integer('on_hand')->default(0);
            $table->integer('reserved')->default(0);
            $table->timestamp('source_updated_at');
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'variant_id']);
            $table->index(['organization_id', 'variant_id']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->integer('quantity_delta');
            $table->integer('on_hand_after');
            $table->string('reason', 40);
            $table->string('source_event_id', 150);
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['organization_id', 'source_event_id']);
            $table->index(['branch_id', 'variant_id', 'created_at']);
            $table->index(['organization_id', 'created_at']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE prices ADD CONSTRAINT prices_unit_price_nonnegative CHECK (unit_price >= 0)');
            DB::statement('ALTER TABLE prices ADD CONSTRAINT prices_min_quantity_positive CHECK (min_quantity > 0)');
            DB::statement('ALTER TABLE prices ADD CONSTRAINT prices_valid_range CHECK (valid_to IS NULL OR valid_to > valid_from)');
            DB::statement('ALTER TABLE inventory_balances ADD CONSTRAINT inventory_on_hand_nonnegative CHECK (on_hand >= 0)');
            DB::statement('ALTER TABLE inventory_balances ADD CONSTRAINT inventory_reserved_valid CHECK (reserved >= 0 AND reserved <= on_hand)');
            DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movement_nonzero CHECK (quantity_delta <> 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('prices');
        Schema::dropIfExists('price_lists');
    }
};
