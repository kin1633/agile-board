<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sprints', function (Blueprint $table) {
            // CASCADE から SET NULL に変更:
            // マイルストーン削除時にスプリントまで巻き込んで削除されないようにするため
            $table->dropForeign(['milestone_id']);
            $table->foreign('milestone_id')->references('id')->on('milestones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sprints', function (Blueprint $table) {
            $table->dropForeign(['milestone_id']);
            $table->foreign('milestone_id')->references('id')->on('milestones')->cascadeOnDelete();
        });
    }
};
