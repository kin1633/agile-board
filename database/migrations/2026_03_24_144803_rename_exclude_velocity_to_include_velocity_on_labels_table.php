<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * exclude_velocity（除外フラグ）を include_velocity（管理対象フラグ）に変更する。
     * 意味を反転させ、デフォルトを true（管理対象）とする。
     */
    public function up(): void
    {
        Schema::table('labels', function (Blueprint $table) {
            $table->boolean('include_velocity')->default(true)->after('name');
        });

        // 既存データを反転して移行: exclude=false → include=true, exclude=true → include=false
        DB::table('labels')->update([
            'include_velocity' => DB::raw('NOT exclude_velocity'),
        ]);

        Schema::table('labels', function (Blueprint $table) {
            $table->dropColumn('exclude_velocity');
        });
    }

    /**
     * ロールバック: include_velocity → exclude_velocity に戻す。
     */
    public function down(): void
    {
        Schema::table('labels', function (Blueprint $table) {
            $table->boolean('exclude_velocity')->default(false)->after('name');
        });

        DB::table('labels')->update([
            'exclude_velocity' => DB::raw('NOT include_velocity'),
        ]);

        Schema::table('labels', function (Blueprint $table) {
            $table->dropColumn('include_velocity');
        });
    }
};
