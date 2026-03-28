<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sub_con_claims') || Schema::hasColumn('sub_con_claims', 'proof_attachments')) {
            return;
        }

        Schema::table('sub_con_claims', function (Blueprint $table) {
            $table->json('proof_attachments')->nullable()->after('proof_attachment_name');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sub_con_claims') || !Schema::hasColumn('sub_con_claims', 'proof_attachments')) {
            return;
        }

        Schema::table('sub_con_claims', function (Blueprint $table) {
            $table->dropColumn('proof_attachments');
        });
    }
};
