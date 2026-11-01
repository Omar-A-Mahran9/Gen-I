<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blueprint S2 §9.2: companies (corporate accounts), company_members
 * (membership and spending authority) and addresses (company-owned only
 * until the customers table exists — see docs/database-design.md DV-17).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('legal_name', 200);
            $table->string('tax_reference', 80)->nullable();
            $table->string('status', 24)->default('pending');
            $table->foreignId('account_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'legal_name']);
            $table->unique(['organization_id', 'tax_reference']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('company_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('member_role', 40);
            $table->decimal('approval_limit', 18, 2)->nullable();
            $table->char('currency', 3);
            $table->string('status', 24)->default('active');
            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('label', 60);
            $table->text('address_text');
            $table->string('city', 100);
            $table->string('postal_code', 20)->nullable();
            $table->char('country_code', 2);
            $table->string('contact_phone', 32);
            $table->timestamps();

            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('company_members');
        Schema::dropIfExists('companies');
    }
};
