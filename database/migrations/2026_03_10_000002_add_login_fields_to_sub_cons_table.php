<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_cons', function (Blueprint $table) {
            $table->string('login_identity_no')->nullable()->unique()->after('bank');
            $table->string('login_email')->nullable()->unique()->after('login_identity_no');
            $table->string('login_password')->nullable()->after('login_email');
            $table->tinyInteger('login_status')->default(1)->after('login_password');
        });
    }

    public function down(): void
    {
        Schema::table('sub_cons', function (Blueprint $table) {
            $table->dropUnique('sub_cons_login_identity_no_unique');
            $table->dropUnique('sub_cons_login_email_unique');
            $table->dropColumn([
                'login_identity_no',
                'login_email',
                'login_password',
                'login_status',
            ]);
        });
    }
};

