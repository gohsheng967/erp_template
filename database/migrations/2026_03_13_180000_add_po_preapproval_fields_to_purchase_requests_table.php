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
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->string('delivery_period')->nullable()->after('required_date');
            $table->text('payment_terms')->nullable()->after('delivery_period');
            $table->unsignedBigInteger('site_contact_user_id')->nullable()->after('payment_terms');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_period',
                'payment_terms',
                'site_contact_user_id',
            ]);
        });
    }
};
