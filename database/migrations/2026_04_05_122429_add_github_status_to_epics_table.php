<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('epics', function (Blueprint $table) {
            // GitHub Projects の Status フィールド値をそのまま格納する
            // プロジェクトごとにカスタム定義されるため nullable
            $table->string('github_status')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('epics', function (Blueprint $table) {
            $table->dropColumn('github_status');
        });
    }
};
