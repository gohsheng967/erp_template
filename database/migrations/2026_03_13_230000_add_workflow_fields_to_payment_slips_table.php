<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payment_slips', function (Blueprint $table) {
            $table->string('workflow_status')->nullable()->after('amount_due_label');
            $table->timestamp('approved_at')->nullable()->after('workflow_status');
            $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
            $table->timestamp('rejected_at')->nullable()->after('approved_by');
            $table->unsignedBigInteger('rejected_by')->nullable()->after('rejected_at');
            $table->string('rejected_reason')->nullable()->after('rejected_by');
        });
    }

    public function down(): void
    {
        Schema::table('payment_slips', function (Blueprint $table) {
            $table->dropColumn([
                'workflow_status',
                'approved_at',
                'approved_by',
                'rejected_at',
                'rejected_by',
                'rejected_reason',
            ]);
        });
    }
};

