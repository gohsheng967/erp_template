<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_cons', function (Blueprint $table) {
            $table->boolean('login_must_change_password')
                ->default(false)
                ->after('login_status');
        });
    }

    public function down(): void
    {
        Schema::table('sub_cons', function (Blueprint $table) {
            $table->dropColumn('login_must_change_password');
        });
    }
};

