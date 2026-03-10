<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_con_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_con_id')->constrained('sub_cons')->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('account_name')->nullable();
            $table->string('account_no')->nullable();
            $table->timestamps();
        });

        $rows = DB::table('sub_cons')
            ->whereNotNull('bank')
            ->where('bank', '!=', '')
            ->select('id', 'bank')
            ->get();

        foreach ($rows as $row) {
            DB::table('sub_con_bank_accounts')->insert([
                'sub_con_id' => $row->id,
                'bank_name' => $row->bank,
                'account_name' => null,
                'account_no' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_con_bank_accounts');
    }
};
