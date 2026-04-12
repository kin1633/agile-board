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
        Schema::table('pull_requests', function (Blueprint $table) {
            // GitHub Checks の CI ステータス: pending / success / failure / skipped / null (不明)
            $table->string('ci_status')->nullable()->after('synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->dropColumn('ci_status');
        });
    }
};
