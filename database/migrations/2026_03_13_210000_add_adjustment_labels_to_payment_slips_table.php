<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payment_slips', function (Blueprint $table) {
            $table->string('less_retention_label')->nullable()->after('less_retention');
            $table->string('less_recoupment_label')->nullable()->after('less_recoupment');
            $table->string('less_material_ob_label')->nullable()->after('less_material_ob');
            $table->string('less_paid_previously_label')->nullable()->after('less_paid_previously');
            $table->string('remark_label')->nullable()->after('payment_slip_remark');
        });
    }

    public function down(): void
    {
        Schema::table('payment_slips', function (Blueprint $table) {
            $table->dropColumn([
                'less_retention_label',
                'less_recoupment_label',
                'less_material_ob_label',
                'less_paid_previously_label',
                'remark_label',
            ]);
        });
    }
};

