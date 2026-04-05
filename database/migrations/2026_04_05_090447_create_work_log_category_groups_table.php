<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('work_log_category_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 既存の group_name で使われている値を初期グループとして投入する
        DB::table('work_log_category_groups')->insert([
            ['name' => 'PJ管理工数',     'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '保守・運用工数', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '工数管理外',     'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_log_category_groups');
    }
};
