<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_con_tasks', function (Blueprint $table) {
            $table->string('invoice_no')->nullable()->after('amount');
            $table->date('invoice_date')->nullable()->after('invoice_no');
            $table->decimal('invoice_amount', 12, 2)->nullable()->after('invoice_date');
            $table->text('invoice_remark')->nullable()->after('invoice_amount');
            $table->string('invoice_attachment_path')->nullable()->after('invoice_remark');
            $table->string('invoice_attachment_name')->nullable()->after('invoice_attachment_path');
            $table->timestamp('invoice_submitted_at')->nullable()->after('invoice_attachment_name');
        });
    }

    public function down(): void
    {
        Schema::table('sub_con_tasks', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_no',
                'invoice_date',
                'invoice_amount',
                'invoice_remark',
                'invoice_attachment_path',
                'invoice_attachment_name',
                'invoice_submitted_at',
            ]);
        });
    }
};
