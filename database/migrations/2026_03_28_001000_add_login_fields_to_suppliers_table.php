<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('login_identity_no')->nullable()->unique()->after('status');
            $table->string('login_email')->nullable()->unique()->after('login_identity_no');
            $table->string('login_password')->nullable()->after('login_email');
            $table->tinyInteger('login_status')->default(1)->after('login_password');
            $table->boolean('login_must_change_password')->default(true)->after('login_status');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropUnique('suppliers_login_identity_no_unique');
            $table->dropUnique('suppliers_login_email_unique');
            $table->dropColumn([
                'login_identity_no',
                'login_email',
                'login_password',
                'login_status',
                'login_must_change_password',
            ]);
        });
    }
};

