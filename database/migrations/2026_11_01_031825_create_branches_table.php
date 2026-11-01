<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blueprint S2 §9.1 "branches": stores and service branches.
 * city/phone/email/latitude/longitude are nullable additions justified by
 * FR-20 (store location, hours and directions) — DV-18.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->text('address_text');
            $table->string('city', 100)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email', 254)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('opening_hours');
            $table->string('status', 24)->default('active');
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
