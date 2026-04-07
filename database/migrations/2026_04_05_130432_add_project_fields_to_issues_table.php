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
        Schema::table('issues', function (Blueprint $table) {
            // GitHub Projects v2 の Priority フィールド値を格納する
            $table->string('project_priority')->nullable()->after('project_status');
            // GitHub Projects v2 の Start date / Target date フィールド値を格納する
            $table->date('project_start_date')->nullable()->after('project_priority');
            $table->date('project_target_date')->nullable()->after('project_start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropColumn(['project_priority', 'project_start_date', 'project_target_date']);
        });
    }
};
