<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('company_name');
            $table->string('registration_no')->nullable();

            $table->string('contact_person')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('email')->nullable();

            $table->text('address')->nullable();

            $table->string('status')->default('active');
            // active | inactive | blacklisted

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
