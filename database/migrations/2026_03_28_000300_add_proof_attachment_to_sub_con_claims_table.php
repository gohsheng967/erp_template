<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sub_con_claims')) {
            return;
        }

        Schema::table('sub_con_claims', function (Blueprint $table) {
            if (!Schema::hasColumn('sub_con_claims', 'proof_attachment_path')) {
                $table->text('proof_attachment_path')->nullable()->after('proforma_invoice_name');
            }
            if (!Schema::hasColumn('sub_con_claims', 'proof_attachment_name')) {
                $table->string('proof_attachment_name')->nullable()->after('proof_attachment_path');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sub_con_claims')) {
            return;
        }

        Schema::table('sub_con_claims', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('sub_con_claims', 'proof_attachment_name')) {
                $drop[] = 'proof_attachment_name';
            }
            if (Schema::hasColumn('sub_con_claims', 'proof_attachment_path')) {
                $drop[] = 'proof_attachment_path';
            }
            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
